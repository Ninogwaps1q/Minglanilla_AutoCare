<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopPlanPayment;

class ShopSubscriptionBilling
{
    /**
     * The caller must hold the shop row lock throughout approval and synchronization.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approve(Shop $shop, ShopPlanPayment $payment, array $attributes): void
    {
        $startsAt = now();
        $lastPeriod = $shop->planPayments()->validPeriods()->latest('period_ends_at')->first();
        foreach ([$shop->plan_expires_at, $shop->trialIsActive() ? $shop->trialEndsAt() : null, $lastPeriod?->period_ends_at] as $end) {
            if ($end && $end->greaterThan($startsAt)) {
                $startsAt = $end->copy();
            }
        }
        $payment->update(array_merge($attributes, ['status' => 'approved', 'period_starts_at' => $startsAt, 'period_ends_at' => $startsAt->copy()->addMonthNoOverflow()]));
        $shop->requested_plan = null;
        $this->sync($shop);
    }

    public function sync(Shop $shop): void
    {
        $periods = $shop->planPayments()->validPeriods();
        $current = (clone $periods)->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now())->latest('period_starts_at')->first();
        if ($current) {
            $shop->fill(['plan_slug' => $current->plan_slug, 'plan_expires_at' => $current->period_ends_at, 'plan_payment_id' => $current->id]);
        } elseif ($shop->plan_payment_id !== null) {
            $shop->fill(['plan_slug' => 'free', 'plan_expires_at' => null, 'plan_payment_id' => null]);
        }
        $shop->next_plan_starts_at = (clone $periods)->where('period_starts_at', '>', now())->min('period_starts_at');
        $shop->save();
    }
}
