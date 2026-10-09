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
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'barangay' => ['nullable', 'integer', 'exists:barangays,id'], 'service' => ['nullable', 'integer', 'exists:services,id'], 'sort' => ['nullable', 'in:name,rating']]);
        $shops = $this->publicShops()->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay_id', $barangay))
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->whereHas('services', fn ($query) => $query->where('service_id', $service)->where('availability', true)))
            ->when(($filters['sort'] ?? 'name') === 'rating', fn ($query) => $query->orderByDesc('average_rating'), fn ($query) => $query->orderBy('name'))->get();

        return view('shops.index', ['shops' => $shops, 'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'services' => Service::query()->orderBy('name')->get(['id', 'name']), 'filters' => $filters]);
    }

    public function show(Shop $shop): View
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 404);

        return view('shops.show', ['shop' => $shop->load(['barangay', 'services.service.category', 'reviews' => fn ($query) => $query->where('status', 'published')->with('customer')->latest()])]);
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
}
