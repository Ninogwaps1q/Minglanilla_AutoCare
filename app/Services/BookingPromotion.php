<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Shop;
use App\Models\ShopPromotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingPromotion
{
    /** @return array{shop_promotion_id: int, promotion_discount: float}|array{} */
    public function forBooking(Shop $shop, Collection $offers, int $customerId, ?string $code): array
    {
        $selection = [];
        $bestCents = 0;
        if ($shop->hasPlanAtLeast('premium')) {
            $promos = ShopPromotion::query()->where('shop_id', $shop->id)->available()
                ->when($code !== null, fn ($query) => $query->where('code', Str::upper($code)), fn ($query) => $query->whereNull('code'))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($promos as $promo) {
                $eligible = $offers->filter(fn ($offer): bool => ! $promo->service_ids
                    || in_array((int) $offer->service_id, $promo->service_ids, true));
                $eligibleCents = (int) $eligible->sum(fn ($offer): int => (int) round((float) $offer->price * 100));
                if ($eligibleCents <= 0 || $eligibleCents < (int) round((float) $promo->minimum * 100)) {
                    continue;
                }
                $discount = $promo->kind === 'percent' ? (int) round($eligibleCents * (float) $promo->value / 100) : (int) round((float) $promo->value * 100);
                $discount = min($eligibleCents, $discount);
                if ($discount <= $bestCents) {
                    continue;
                }
                $used = Appointment::withTrashed()->where('shop_promotion_id', $promo->id)->whereNotIn('status', ['cancelled', 'no_show']);
                if (($promo->usage_limit && (clone $used)->count() >= $promo->usage_limit)
                    || ($promo->customer_limit && $used->where('customer_id', $customerId)->count() >= $promo->customer_limit)) {
                    continue;
                }
                $bestCents = $discount;
                $selection = ['shop_promotion_id' => $promo->id, 'promotion_discount' => $discount / 100];
            }
        }

        if ($code !== null && $selection === []) {
            throw ValidationException::withMessages(['promo_code' => __('extensions.promo.invalid')]);
        }

        return $selection;
    }

    public function discountCents(?Appointment $appointment, int $subtotal): int
    {
        return min($subtotal, (int) round((float) $appointment?->promotion_discount * 100));
    }
}
