<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\RepairJobStatusHistory;
use App\Models\ServiceCase;
use App\Models\VehicleInspection;
use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiServiceStatus;
use App\Services\AI\GeminiProvider;
use App\Services\AI\OpenAiProvider;
use App\Services\CustomerWorkflowAlerts;
use App\Services\Messaging\MessageStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, fn (): AiProviderInterface => match (config('ai.provider')) {
            'openai' => new OpenAiProvider,
            default => new GeminiProvider(new AiServiceStatus),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        foreach ([RepairJobStatusHistory::class, VehicleInspection::class, Quotation::class, Invoice::class, Payment::class] as $model) {
            $model::observe(CustomerWorkflowAlerts::class);
        }
        RateLimiter::for('review-posts', fn (Request $request): Limit => Limit::perHour(5)->by($request->user()->id));
        RateLimiter::for('review-reports', fn (Request $request): Limit => Limit::perHour(10)->by($request->user()->id));

        ViewFacade::composer(['components.layouts.app', 'customer.notifications.index', 'shop.notifications.index', 'customer.messages.index', 'shop.messages.index'], function (View $view): void {
            $user = auth()->user();
            $view->with('unreadNotificationCount', $user && in_array($user->role, ['customer', 'shop_owner', 'mechanic'], true)
                ? $user->notifications()->whereNull('read_at')->count()
                : 0);

            $unreadMessageCount = 0;
            $messagesUnavailable = false;
            try {
                if ($user?->role === 'customer') {
                    $unreadMessageCount = Message::query()
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereHas('thread', fn ($query) => $query->where('customer_id', $user->id))
                        ->count();
                } elseif ($user?->isProviderOwner()) {
                    $shopId = $user->shops()->value('id');
                    if ($shopId !== null) {
                        $unreadMessageCount = Message::query()
                            ->whereNull('read_at')
                            ->where('sender_id', '!=', $user->id)
                            ->whereHas('thread', fn ($query) => $query->where('shop_id', $shopId))
                            ->count();
                    }
                }
            } catch (QueryException $exception) {
                if (! MessageStorage::isUnavailable($exception)) {
                    throw $exception;
                }

                $messagesUnavailable = true;
            }

            $view->with('unreadMessageCount', $unreadMessageCount);
            $view->with('messagesUnavailable', $messagesUnavailable);
            $view->with('openComplaintCount', $user?->isAdmin()
                ? ServiceCase::query()->where('type', 'complaint')->whereIn('status', ['open', 'reviewing'])->count()
                : 0);
        });
    }
}
