<?php

namespace App\Services;

use App\Models\EmergencyRequest;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class EmergencyDispatchService
{
    public function assignNext(EmergencyRequest $emergencyRequest): ?Shop
    {
        $attemptedShopIds = DB::table('emergency_dispatch_attempts')
            ->where('emergency_request_id', $emergencyRequest->id)
            ->pluck('shop_id')
            ->all();

        $shop = Shop::query()
            ->where('verification_status', 'approved')
            ->where('shop_status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('id', $attemptedShopIds)
            ->with('owner')
            ->get()
            ->sortBy(fn (Shop $candidate): float => $this->distance(
                (float) $emergencyRequest->latitude,
                (float) $emergencyRequest->longitude,
                (float) $candidate->latitude,
                (float) $candidate->longitude,
            ))
            ->first();

        if ($shop === null) {
            return null;
        }

        DB::table('emergency_dispatch_attempts')->insert([
            'emergency_request_id' => $emergencyRequest->id,
            'shop_id' => $shop->id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $emergencyRequest->update(['assigned_shop_id' => $shop->id, 'status' => 'pending', 'responded_at' => null]);
        $shop->owner->notifications()->create([
            'type' => 'emergency_request',
            'title' => 'New roadside assistance request',
            'message' => 'A nearby customer requires roadside assistance.',
            'data' => ['emergency_request_id' => $emergencyRequest->id],
        ]);

        return $shop;
    }

    public function reassign(EmergencyRequest $emergencyRequest, string $reason): ?Shop
    {
        $shopId = $emergencyRequest->assigned_shop_id;
        if ($shopId !== null) {
            $attempt = DB::table('emergency_dispatch_attempts')
                ->where('emergency_request_id', $emergencyRequest->id)
                ->where('shop_id', $shopId);
            if ($attempt->exists()) {
                $attempt->update(['status' => $reason, 'ended_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('emergency_dispatch_attempts')->insert([
                    'emergency_request_id' => $emergencyRequest->id,
                    'shop_id' => $shopId,
                    'status' => $reason,
                    'assigned_at' => $emergencyRequest->updated_at ?? now(),
                    'ended_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $emergencyRequest->update(['assigned_shop_id' => null, 'responded_at' => null]);
        $nextShop = $this->assignNext($emergencyRequest);
        if ($nextShop === null) {
            $emergencyRequest->update(['status' => 'cancelled']);
        }

        $emergencyRequest->customer->notifications()->create([
            'type' => 'emergency_status',
            'title' => $nextShop === null ? 'No shop available for roadside assistance' : 'Roadside request sent to another shop',
            'message' => $nextShop === null
                ? 'The assigned shop could not assist and no other eligible shop is currently available. Please seek immediate local emergency help if needed.'
                : "The previous shop could not assist. Your request has been sent to {$nextShop->name}.",
            'data' => ['emergency_request_id' => $emergencyRequest->id],
        ]);

        return $nextShop;
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
