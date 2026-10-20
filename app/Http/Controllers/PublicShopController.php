<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
            'sort' => ['nullable', 'in:name,rating,distance'],
            'selected' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);
        $location = isset($filters['latitude'], $filters['longitude'])
            ? ['latitude' => (float) $filters['latitude'], 'longitude' => (float) $filters['longitude']]
            : null;
        $filters['sort'] = $filters['sort'] ?? ($location ? 'distance' : 'name');
        if ($filters['sort'] === 'distance' && $location === null) {
            $filters['sort'] = 'name';
        }

        $shops = $this->publicShops()->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay_id', $barangay))
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->whereHas('services', fn ($query) => $query->where('service_id', $service)->where('availability', true)))
            ->when($filters['sort'] === 'rating', fn ($query) => $query->orderByDesc('average_rating'), fn ($query) => $query->orderBy('name'))->get();

        $distances = collect();
        if ($location !== null) {
            $distances = $shops->filter(fn (Shop $shop): bool => $shop->latitude !== null && $shop->longitude !== null)
                ->mapWithKeys(fn (Shop $shop): array => [$shop->id => round($this->distance($location['latitude'], $location['longitude'], (float) $shop->latitude, (float) $shop->longitude), 1)]);
            if ($filters['sort'] === 'distance') {
                $shops = $shops->sortBy(fn (Shop $shop): float => $distances->get($shop->id, PHP_FLOAT_MAX))->values();
            }
        }

        $mapShops = $shops->filter(fn (Shop $shop): bool => $shop->latitude !== null && $shop->longitude !== null)
            ->map(fn (Shop $shop): array => [
                'id' => $shop->id,
                'name' => $shop->name,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'logo_url' => $shop->logo ? route('media.image', ['directory' => 'shop-logos', 'filename' => basename($shop->logo)]) : null,
                'url' => route('shops.show', $shop),
            ])->values();

        return view('shops.index', [
            'shops' => $shops,
            'mapShops' => $mapShops,
            'distances' => $distances,
            'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function show(Shop $shop): View
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 404);

        return view('shops.show', ['shop' => $shop->load(['barangay', 'services.service.category', 'reviews' => fn ($query) => $query->where('status', 'published')->with('customer:id,name')->latest()->limit(5)])]);
    }

    public function compare(Service $service): View
    {
        $offers = ShopService::query()->with(['shop.barangay', 'service'])->where('service_id', $service->id)->where('availability', true)->whereHas('shop', fn ($query) => $query->where('verification_status', 'approved')->where('shop_status', 'active'))->orderBy('price')->get();

        return view('shops.compare', ['service' => $service, 'offers' => $offers]);
    }

    private function publicShops(): Builder
    {
        return Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')->with(['barangay:id,name', 'services.service:id,name']);
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
