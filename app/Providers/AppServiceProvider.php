<?php

namespace App\Providers;

use App\Models\Message;
use App\Services\AI\AiProviderInterface;
use App\Services\AI\GeminiProvider;
use App\Services\AI\OpenAiProvider;
use Illuminate\Pagination\Paginator;
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
            default => new GeminiProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        ViewFacade::composer(['components.layouts.app', 'customer.notifications.index', 'shop.notifications.index', 'customer.messages.index', 'shop.messages.index'], function (View $view): void {
            $user = auth()->user();
            $view->with('unreadNotificationCount', $user && in_array($user->role, ['customer', 'shop_owner'], true)
                ? $user->notifications()->whereNull('read_at')->count()
                : 0);

            $unreadMessageCount = 0;
            if ($user?->role === 'customer') {
                $unreadMessageCount = Message::query()
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id)
                    ->whereHas('thread', fn ($query) => $query->where('customer_id', $user->id))
                    ->count();
            } elseif ($user?->role === 'shop_owner') {
                $shopId = $user->shops()->value('id');
                if ($shopId !== null) {
                    $unreadMessageCount = Message::query()
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereHas('thread', fn ($query) => $query->where('shop_id', $shopId))
                        ->count();
                }
            }

            $view->with('unreadMessageCount', $unreadMessageCount);
        });
    }
}
