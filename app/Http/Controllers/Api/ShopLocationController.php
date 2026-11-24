<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ShopLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate($this->filterRules());

        return response()->json(['data' => $this->shops($filters)->map(fn (Shop $shop) => $this->data($shop))]);
    }

    public function nearby(Request $request): JsonResponse
    {
        $location = $request->validate([...$this->filterRules(), 'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $shops = $this->shops($location)->map(function (Shop $shop) use ($location): array {
            $data = $this->data($shop);
            $data['distance_km'] = round($this->distance($location['latitude'], $location['longitude'], $data['latitude'], $data['longitude']), 2);

            return $data;
        })->sortBy('distance_km')->values();

        return response()->json(['data' => $shops]);
    }

    private function filterRules(): array
    {
        return [
            ...Barangay::AREA_FILTER_RULES,
            'search' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
            'provider' => ['nullable', 'in:shop,independent_mechanic'],
        ];
    }

    private function shops(array $filters = []): Collection
    {
        return Shop::query()->publicListing()->whereNotNull(['latitude', 'longitude'])->with(['barangay:id,name', 'services.service:id,name'])
            ->inArea($filters)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->matchingPlace($search))
            ->when($filters['provider'] ?? null, fn ($query, $provider) => $query->where('provider_type', $provider))
            ->when($filters['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay_id', $barangay))
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->whereHas('services', fn ($query) => $query->where('service_id', $service)->where('availability', true)))
            ->orderBy('name')->get();
    }

    private function data(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            ...$shop->publicMapPosition(),
            'provider_type' => $shop->provider_type,
            'barangay' => $shop->barangay->name,
            'rating' => (float) $shop->average_rating,
            'services' => $shop->services->where('availability', true)->pluck('service.name')->values(),
            'logo_url' => $shop->logo && Storage::disk('public')->exists($shop->logo)
                ? route('media.image', ['directory' => 'shop-logos', 'filename' => basename($shop->logo)])
                : null,
        ];
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $radius = 6371;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $radius * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
