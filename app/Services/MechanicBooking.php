<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Shop;
use Illuminate\Validation\ValidationException;

class MechanicBooking
{
    public const FEES = ['inspection_fee', 'home_fee', 'travel_fee'];

    public function checkNewBooking(Shop $shop): void
    {
        $month = now(Appointment::TIMEZONE)->startOfMonth();
        $limit = $shop->mechanicEntitlements()['monthly_limit'];
        if ($limit !== null && $shop->appointments()->withTrashed()
            ->where('created_at', '>=', $month->copy()->utc())
            ->where('created_at', '<', $month->copy()->addMonth()->utc())
            ->where('status', '!=', 'cancelled')->count() >= $limit) {
            throw ValidationException::withMessages(['shop_id' => __('mechanic.booking_limit')]);
        }
    }

    public function quote(Shop $shop, Barangay $barangay, array $attributes, int $serviceCents): array
    {
        $settings = $shop->mechanic_settings ?? [];
        $cities = $settings['coverage_cities'] ?? [];
        $provinces = $settings['coverage_provinces'] ?? [];
        $barangays = $settings['coverage_barangays'] ?? [];
        if (($cities || $provinces || $barangays)
            && ! in_array($barangay->id, $barangays, true)
            && ! in_array($barangay->municipality_id, $cities, true)
            && ! in_array($barangay->municipality?->province_id, $provinces, true)) {
            throw ValidationException::withMessages(['service_barangay_id' => __('mechanic.outside_coverage')]);
        }
        $distance = null;
        if (isset($settings['radius_km'])) {
            $areaCenter = $shop->publicMapPosition();
            if (! isset($attributes['latitude'], $attributes['longitude']) || $areaCenter === null) {
                throw ValidationException::withMessages(['latitude' => __('mechanic.pin_required')]);
            }
            $latitude = deg2rad((float) $attributes['latitude']);
            $baseLatitude = deg2rad($areaCenter['latitude']);
            $longitudeDifference = deg2rad((float) $attributes['longitude'] - $areaCenter['longitude']);
            $haversine = sin(($latitude - $baseLatitude) / 2) ** 2 + cos($latitude) * cos($baseLatitude) * sin($longitudeDifference / 2) ** 2;
            $distance = 12742 * asin(sqrt(min(1, $haversine)));
            if ($distance > $settings['radius_km']) {
                throw ValidationException::withMessages(['latitude' => __('mechanic.outside_coverage')]);
            }
        }
        $paidPricing = $shop->mechanicEntitlements()['travel_pricing'];
        $fees = [
            'inspection_fee' => $paidPricing ? (int) round(($settings['inspection_fee'] ?? 0) * 100) : 0,
            'home_fee' => $paidPricing ? (int) round(($settings['home_fee'] ?? 0) * 100) : 0,
            'travel_fee' => $paidPricing ? (int) round(($distance ?? 0) * ($settings['travel_rate'] ?? 0) * 100) : 0,
        ];

        return ['service' => $serviceCents, ...$fees, 'total' => $serviceCents + array_sum($fees)];
    }
}
