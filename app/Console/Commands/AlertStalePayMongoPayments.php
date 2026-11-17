<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\ShopPlanPayment;
use App\Models\User;
use Illuminate\Console\Command;

class AlertStalePayMongoPayments extends Command
{
    protected $signature = 'app:alert-stale-paymongo-payments';

    protected $description = 'Alert administrators about failed or long-pending PayMongo checkouts';

    public function handle(): int
    {
        $admins = User::query()->where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return self::SUCCESS;
        }
        foreach ([Payment::class, ShopPlanPayment::class] as $model) {
            $model::query()->whereNull('payment_alerted_at')->where(function ($query): void {
                $query->where('status', 'failed')->orWhere(fn ($query) => $query->where('status', 'checkout_pending')->where('created_at', '<', now()->subMinutes(30)));
            })->chunkById(100, function ($payments) use ($admins): void {
                foreach ($payments as $payment) {
                    foreach ($admins as $admin) {
                        $admin->notifications()->create(['type' => 'paymongo_alert', 'title' => 'Payment needs attention', 'message' => class_basename($payment).' #'.$payment->id.' is '.$payment->status.'.', 'data' => ['payment_id' => $payment->id]]);
                    }
                    $payment->update(['payment_alerted_at' => now()]);
                }
            });
        }

        return self::SUCCESS;
    }
}
