<?php

namespace App\Console\Commands;

use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopPlanPayment;
use App\Services\PayMongoCheckout;
use App\Services\PayMongoRefunds;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReconcilePayMongoPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reconcile-paymongo-payments {--limit=25 : Maximum payments per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover missed PayMongo checkout and refund confirmations without a browser.';

    /**
     * Execute the console command.
     */
    public function handle(PayMongoCheckout $checkout, PayMongoWebhookController $webhook, PayMongoRefunds $refunds): int
    {
        if (! $checkout->configured()) {
            $this->warn('PayMongo reconciliation skipped: complete the API key and webhook secret configuration.');

            return self::SUCCESS;
        }
        $limit = max(1, min(100, (int) $this->option('limit')));
        $candidates = collect();
        foreach ([Payment::class, ShopPlanPayment::class] as $model) {
            $candidates = $candidates->concat($model::query()->where(function (Builder $query): void {
                $query->where(fn (Builder $query): Builder => $query->where('status', 'checkout_pending')->whereNotNull('paymongo_session_id'))
                    ->orWhere('refund_status', 'submitting')
                    ->orWhere(fn (Builder $query): Builder => $query->whereIn('refund_status', ['needs_review', 'pending', 'processing'])->whereNotNull('paymongo_refund_id'));
            })->where(fn (Builder $query): Builder => $query->whereNull('paymongo_checked_at')->orWhere('paymongo_checked_at', '<=', now()->subMinutes(5)))
                ->orderBy('paymongo_checked_at')->orderBy('id')->limit($limit)->get());
        }
        $started = microtime(true);
        $checked = 0;
        foreach ($candidates->sortBy(fn ($payment): int => $payment->paymongo_checked_at?->getTimestamp() ?? 0)->take($limit) as $payment) {
            if (microtime(true) - $started >= 45) {
                break;
            }
            $claimed = $payment->newQuery()->whereKey($payment->id)
                ->where(fn (Builder $query): Builder => $query->whereNull('paymongo_checked_at')->orWhere('paymongo_checked_at', '<=', now()->subMinutes(5)))
                ->update(['paymongo_checked_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $checked++;
            if ($payment->status === 'checkout_pending') {
                $session = $checkout->retrieve($payment->paymongo_session_id);
                if (! $session) {
                    continue;
                }
                if (collect($session['attributes']['payments'] ?? [])->contains(fn ($paid): bool => ($paid['attributes']['status'] ?? null) === 'paid')) {
                    $webhook->processSession($session);
                } elseif (($session['attributes']['status'] ?? null) === 'expired') {
                    DB::transaction(function () use ($payment): void {
                        $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
                        if ($payment->status === 'checkout_pending') {
                            $payment->update(['status' => 'cancelled']);
                            if ($payment instanceof ShopPlanPayment) {
                                Shop::query()->whereKey($payment->shop_id)->update(['requested_plan' => null]);
                            }
                        }
                    });
                }
            } elseif ($payment->paymongo_refund_id) {
                $refunds->check($payment);
            } elseif ($payment->refund_status === 'submitting' && $payment->refund_reviewed_at?->lt(now()->subMinutes(10))) {
                DB::transaction(function () use ($payment): void {
                    $payment = $payment->newQuery()->lockForUpdate()->findOrFail($payment->id);
                    if ($payment->refund_status === 'submitting') {
                        $payment->update(['refund_status' => 'needs_review']);
                        ActivityLog::query()->create(['user_id' => $payment->refund_reviewed_by, 'action' => 'refund_needs_review', 'module' => 'payments', 'description' => $payment->getTable().' #'.$payment->id.': interrupted refund submission requires PayMongo matching.']);
                    }
                });
            }
        }
        $this->info("Checked {$checked} PayMongo payment(s).");

        return self::SUCCESS;
    }
}
