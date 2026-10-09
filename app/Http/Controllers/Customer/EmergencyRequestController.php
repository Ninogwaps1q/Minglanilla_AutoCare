<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmergencyRequest;
use App\Models\EmergencyRequest;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmergencyRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.emergency-requests.index', ['requests' => $request->user()->emergencyRequests()->with(['vehicle', 'assignedShop'])->latest('requested_at')->get(), 'vehicles' => $request->user()->vehicles()->orderBy('plate_number')->get()]);
    }

    public function store(StoreEmergencyRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        if (isset($attributes['vehicle_id']) && ! $request->user()->vehicles()->whereKey($attributes['vehicle_id'])->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'Select one of your own vehicles.']);
        }
        $emergencyRequest = DB::transaction(function () use ($attributes, $request): EmergencyRequest {
            $shop = Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')->whereNotNull(['latitude', 'longitude'])->get()->sortBy(fn (Shop $shop): float => $this->distance((float) $attributes['latitude'], (float) $attributes['longitude'], (float) $shop->latitude, (float) $shop->longitude))->first();
            $emergencyRequest = $request->user()->emergencyRequests()->create([...$attributes, 'assigned_shop_id' => $shop?->id, 'status' => 'pending', 'requested_at' => now()]);
            if ($shop !== null) {
                $shop->owner->notifications()->create(['type' => 'emergency_request', 'title' => 'New roadside assistance request', 'message' => 'A nearby customer requires roadside assistance.', 'data' => ['emergency_request_id' => $emergencyRequest->id]]);
            }

            return $emergencyRequest;
        });

        return back()->with('status', $emergencyRequest->assigned_shop_id === null ? 'Your request was recorded. No eligible nearby shop is currently available.' : 'Your request was sent to the nearest eligible shop.');
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
