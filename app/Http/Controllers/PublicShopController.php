<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopPromotion;
use App\Models\ShopService;
use App\Services\AppointmentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    public function index(Request $request, AppointmentSchedule $schedule): View
    {
        $filters = $request->validate([
            ...Barangay::AREA_FILTER_RULES,
            'search' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
            'provider' => ['nullable', 'in:shop,independent_mechanic'],
            'sort' => ['nullable', 'in:name,rating,distance'],
            'rating' => ['nullable', 'in:3,4,4.5'],
            'open_now' => ['nullable', 'boolean'],
            'roadside' => ['nullable', 'boolean'],
            'motorcycle' => ['nullable', 'boolean'],
            'home_service' => ['nullable', 'boolean'],
            'max_price' => ['nullable', 'numeric', 'between:0,1000000'],
            'vehicle_type' => ['nullable', 'in:car,motorcycle,truck'],
            'radius' => ['nullable', 'in:5,10,25,50,100'],
            'view' => ['nullable', 'in:list,map'],
            'selected' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);
        $location = isset($filters['latitude'], $filters['longitude'])
            ? ['latitude' => (float) $filters['latitude'], 'longitude' => (float) $filters['longitude']]
            : null;
        $filters['sort'] = $filters['sort'] ?? ($location ? 'distance' : 'name');
        $filters['view'] ??= isset($filters['selected']) ? 'map' : 'list';
        if ($filters['sort'] === 'distance' && $location === null) {
            $filters['sort'] = 'name';
        }

        $now = now(Appointment::TIMEZONE);
        $shops = Shop::query()->publicListing()->with(['barangay.municipality.province.region', 'services' => fn ($query) => $query->where('availability', true), 'services.service:id,name'])
            ->inArea($filters)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where(
                fn (Builder $query): Builder => $query->matchingPlace($search)
                    ->orWhereHas('services', fn (Builder $offers): Builder => $offers->where('availability', true)
                        ->whereHas('service', fn (Builder $service): Builder => $service->where('name', 'like', '%'.$search.'%'))),
            ))
            ->when($filters['roadside'] ?? false, fn (Builder $query): Builder => $query->where('roadside_available', true))
            ->when($filters['motorcycle'] ?? false, fn (Builder $query): Builder => $query->whereHas('services', fn (Builder $offers): Builder => $offers->where('availability', true)
                ->whereHas('service', fn (Builder $service): Builder => $service->where('name', 'like', '%motorcycle%')
                    ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', '%motorcycle%')))))
            ->when($filters['provider'] ?? null, fn ($query, $provider) => $query->where('provider_type', $provider))
            ->when($filters['home_service'] ?? false, fn (Builder $query) => $query->where('provider_type', 'independent_mechanic')
                ->where(fn (Builder $home) => $home->whereNull('mechanic_settings->home_service')->orWhere('mechanic_settings->home_service', true)))
            ->when($filters['vehicle_type'] ?? null, fn (Builder $query, string $type) => $query->whereJsonContains('mechanic_profile->vehicle_types', $type))
            ->when(isset($filters['max_price']), fn (Builder $query) => $query->whereHas('services', fn (Builder $offers) => $offers->where('availability', true)
                ->where('price', '<=', $filters['max_price'])->when($filters['service'] ?? null, fn (Builder $offer, $id) => $offer->where('service_id', $id))))
            ->when($filters['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay_id', $barangay))
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->whereHas('services', fn ($query) => $query->where('service_id', $service)->where('availability', true)))
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('total_reviews', '>', 0)->where('average_rating', '>=', $rating))
            ->when($filters['sort'] === 'rating', fn ($query) => $query->orderByDesc('average_rating'), fn ($query) => $query->orderBy('name'))->get();
        $hours = $shops->mapWithKeys(fn (Shop $shop): array => [$shop->id => $schedule->directoryHours($shop, $now)]);
        $openShopIds = $hours->filter(fn (?array $state): bool => $state['open'] ?? false)->keys()->all();
        $plans = $shops->mapWithKeys(fn (Shop $shop): array => [$shop->id => $shop->activePlanSlug()]);

        if ($filters['open_now'] ?? false) {
            $shops = $shops->filter(fn (Shop $shop): bool => in_array($shop->id, $openShopIds, true) && (! $shop->isIndependentMechanic() || $shop->mechanicOnline()))->values();
        }

        if ($filters['sort'] === 'name') {
            $shops = $shops->sortBy(fn (Shop $shop): string => match ($plans[$shop->id]) {
                'premium' => '0',
                'basic' => '1',
                default => '2',
            }.$shop->name)->values();
        }

        $distances = collect();
        if ($location !== null) {
            $distances = $shops->filter(fn (Shop $shop): bool => $shop->publicMapPosition() !== null)
                ->mapWithKeys(function (Shop $shop) use ($location): array {
                    $position = $shop->publicMapPosition();

                    return [$shop->id => $this->distance($location['latitude'], $location['longitude'], $position['latitude'], $position['longitude'])];
                });
            if (isset($filters['radius'])) {
                $shops = $shops->filter(fn (Shop $shop): bool => $distances->get($shop->id, PHP_FLOAT_MAX) <= (int) $filters['radius'])->values();
            }
            if ($filters['sort'] === 'distance') {
                $shops = $shops->sortBy(fn (Shop $shop): float => $distances->get($shop->id, PHP_FLOAT_MAX))->values();
            }
        }

        $canBook = $request->user() === null || $request->user()->role === 'customer';
        $listings = $shops->mapWithKeys(function (Shop $shop) use ($filters, $canBook): array {
            $pricedOffers = $shop->services->whereNotNull('price');
            if (isset($filters['service'])) {
                $pricedOffers = $pricedOffers->where('service_id', $filters['service']);
            }
            $offer = $pricedOffers->sortBy('price')->first();

            return [$shop->id => [
                'price' => $offer ? '₱'.number_format($offer->price, 2) : null,
                'service_name' => $offer?->service->name,
                'booking_url' => $canBook && $shop->acceptsNewBookings() && $shop->services->isNotEmpty()
                    ? route('customer.appointments.create', ['shop_id' => $shop->id])
                    : null,
            ]];
        });
        $mapShops = $shops->filter(fn (Shop $shop): bool => $shop->publicMapPosition() !== null)
            ->map(fn (Shop $shop): array => [
                'id' => $shop->id,
                'name' => $shop->name,
                'provider_type' => $shop->provider_type,
                ...$shop->publicMapPosition(),
                'logo_url' => $shop->logo && Storage::disk('public')->exists($shop->logo)
                    ? route('media.image', ['directory' => 'shop-logos', 'filename' => basename($shop->logo)])
                    : null,
                'url' => route('shops.show', $shop),
                'address' => $shop->barangay->areaLabel(),
                'price' => $listings[$shop->id]['price'],
                'booking_url' => $listings[$shop->id]['booking_url'],
                'directions_url' => $shop->isIndependentMechanic() ? null : route('shops.show', $shop).'#shop-directions',
            ])->values();

        $services = Service::query()->orderBy('name')->get(['id', 'name']);
        $quickServices = collect(['/oil change/i', '/brak/i', '/air\s?(con|condition)/i', '/tires?|tyres?/i'])
            ->map(fn (string $pattern) => $services->first(fn (Service $service): bool => preg_match($pattern, $service->name) === 1))
            ->filter()->unique('id');

        return view('shops.index', [
            'shops' => $shops,
            'mapShops' => $mapShops,
            'distances' => $distances,
            'services' => $services,
            'quickServices' => $quickServices,
            'filters' => $filters,
            'locationLabel' => isset($filters['municipality_id']) ? Municipality::query()->find($filters['municipality_id'])?->name : null,
            'hours' => $hours,
            'listings' => $listings,
        ]);
    }

    public function show(Shop $shop): View
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active' && $shop->owner->status === 'active', 404);
        if ($shop->isIndependentMechanic()) {
            $shop->loadCount(['repairJobs as completed_services_count' => fn ($query) => $query->whereIn('status', ['completed', 'released'])]);
        }

        return view('shops.show', [
            'promotions' => $shop->hasPlanAtLeast('premium') ? ShopPromotion::query()->where('shop_id', $shop->id)->available()->latest()->limit(10)->get() : collect(),
            'isFavorite' => request()->user()?->role === 'customer' && request()->user()->favorites()->where('shop_id', $shop->id)->exists(),
            'shop' => $shop->load(['barangay.municipality.province.region', 'services.service.category', 'reviews' => fn ($query) => $query->where('status', 'published')->with('customer:id,name')->latest()->limit(5)])]);
    }

    public function compare(Service $service): View
    {
        $offers = ShopService::query()->with(['shop.barangay.municipality.province.region', 'service'])->where('service_id', $service->id)->where('availability', true)->whereHas('shop', fn ($query) => $query->publicListing())->orderBy('price')->get();

        return view('shops.compare', ['service' => $service, 'offers' => $offers]);
    }

    private function distance(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $haversine = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * asin(sqrt(min(1, $haversine)));
    }
}
