<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $shops = Shop::query()
            ->where('verification_status', 'approved')
            ->where('shop_status', 'active')
            ->whereNotNull(['latitude', 'longitude'])
            ->with(['barangay:id,name', 'services.service:id,name'])
            ->when($filters['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay_id', $barangay))
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->whereHas('services', fn ($query) => $query->where('service_id', $service)->where('availability', true)))
            ->orderBy('name')
            ->get()
            ->map(fn (Shop $shop) => [
                'name' => $shop->name,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'address' => $shop->address.', '.$shop->barangay->name.', Minglanilla, Cebu',
                'rating' => (float) $shop->average_rating,
                'services' => $shop->services->where('availability', true)->pluck('service.name')->values(),
            ]);

        return view('map.index', [
            'shops' => $shops,
            'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
