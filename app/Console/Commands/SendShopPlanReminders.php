<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendShopPlanReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-shop-plan-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify shop owners before a trial or paid plan ends.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;

        Shop::query()->whereNotNull('trial_started_at')->orWhereNotNull('plan_expires_at')
            ->orderBy('id')->chunkById(100, function ($shops) use (&$sent): void {
                foreach ($shops as $shop) {
                    $sent += DB::transaction(function () use ($shop): int {
                        $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
                        $paidEnd = $shop->plan_slug !== 'free' ? $shop->plan_expires_at : null;
                        $trialEnd = $shop->trial_revoked_at === null ? $shop->trialEndsAt() : null;

                        if ($paidEnd?->isFuture()) {
                            $end = $paidEnd;
                            $kind = 'paid plan';
                        } elseif ($trialEnd && $trialEnd->greaterThanOrEqualTo(today())) {
                            $end = $trialEnd;
                            $kind = 'Premium trial';
                        } else {
                            $end = $paidEnd;
                            $kind = 'paid plan';
                        }
                        if (! $end) {
                            return 0;
                        }

                        if ($shop->planPayments()->validPeriods()->where('period_starts_at', '<=', $end)->where('period_ends_at', '>', $end)->exists()) {
                            return 0;
                        }

                        $days = (int) today()->diffInDays($end->copy()->startOfDay(), false);
                        if (! in_array($days, [7, 1, 0], true)) {
                            return 0;
                        }

                        $owner = $shop->owner;
                        if ($owner->notifications()->where('type', 'shop_plan_reminder')
                            ->where('data->shop_id', $shop->id)
                            ->where('data->ends_on', $end->toDateString())
                            ->where('data->days_remaining', $days)->exists()) {
                            return 0;
                        }

                        $owner->notifications()->create([
                            'type' => 'shop_plan_reminder',
                            'title' => $days === 0 ? 'Shop plan access ends today' : 'Shop plan renewal reminder',
                            'message' => $days === 0
                                ? "Your {$kind} ends today. Your shop listing stays visible; renew to keep online booking access."
                                : "Your {$kind} ends in {$days} ".($days === 1 ? 'day' : 'days')." on {$end->format('M j, Y')}.",
                            'data' => ['shop_id' => $shop->id, 'ends_on' => $end->toDateString(), 'days_remaining' => $days],
                        ]);

                        return 1;
                    });
                }
            });

        $this->info("Sent {$sent} shop plan reminder notification(s).");

        return self::SUCCESS;
    }
}
