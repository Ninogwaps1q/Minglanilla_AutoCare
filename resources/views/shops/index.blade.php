<x-layouts.app title="Find vehicle care">
    <div class="page-heading">
        <h1>{{ __('ui.' . 'Find shops & mechanics') }}</h1>
        <p>Compare verified providers, check their service areas, and choose where to book.</p>
    </div>

    <div class="surface p-4 mb-4">
        <form id="shop-filters" class="row g-3 align-items-end" method="GET" action="{{ route('shops.index') }}" data-nearby-search>
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
            <div class="col-md-6"><label class="form-label" for="shop-search">Provider or city</label><input id="shop-search" type="search" maxlength="100" class="form-control" name="search" placeholder="Search by shop, city, or province" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-6"><label class="form-label" for="shop-service">Service</label><select id="shop-service" class="form-select" name="service"><option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service'] ?? null) == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-12">
                <details class="workspace-disclosure" @if (isset($filters['region_id']) || isset($filters['provider']) || isset($filters['rating']) || ($filters['open_now'] ?? false) || $filters['sort'] === 'rating') open @endif>
                    <summary class="fw-semibold">{{ __('directory.more_filters') }}</summary>
                    <div class="row g-3 align-items-end pt-2">
                        <div class="col-12"><x-address-picker prefix="directory-area" area-only :area="$filters" /></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="provider-filter">Provider type</label><select id="provider-filter" class="form-select" name="provider"><option value="">Shops and mechanics</option><option value="shop" @selected(($filters['provider'] ?? '') === 'shop')>Service shops</option><option value="independent_mechanic" @selected(($filters['provider'] ?? '') === 'independent_mechanic')>Mobile mechanics</option></select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-sort">Sort by</label><select id="shop-sort" class="form-select" name="sort"><option value="name" @selected($filters['sort'] === 'name')>{{ __('directory.featured_sort') }}</option><option value="rating" @selected($filters['sort'] === 'rating')>Highest rating</option><option value="distance" @selected($filters['sort'] === 'distance') @disabled(! isset($filters['latitude'], $filters['longitude']))>Nearest to me</option></select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="minimum-rating">{{ __('directory.rating') }}</label><select id="minimum-rating" class="form-select" name="rating"><option value="">{{ __('directory.any_rating') }}</option>@foreach (['3', '4', '4.5'] as $rating)<option value="{{ $rating }}" @selected(($filters['rating'] ?? null) === $rating)>{{ __('directory.rating_option', ['rating' => $rating]) }}</option>@endforeach</select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-check provider-availability-toggle d-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" name="open_now" value="1" @checked($filters['open_now'] ?? false)>{{ __('directory.open_now') }}</label></div>
                    </div>
                </details>
            </div>
            <input id="latitude" type="hidden" name="latitude" value="{{ $filters['latitude'] ?? '' }}"><input id="longitude" type="hidden" name="longitude" value="{{ $filters['longitude'] ?? '' }}">
            <div class="col-12 d-flex flex-wrap align-items-center gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-2" aria-hidden="true"></i>{{ __('ui.' . 'Find providers') }}</button><button id="nearby-button" class="btn btn-outline-primary" type="button" data-nearby-button><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>{{ __('ui.' . 'Use my location') }}</button><a class="btn btn-link" href="{{ route('shops.index') }}">Clear filters</a></div>
            <p id="location-status" class="col-12 small text-secondary mb-0" role="status" aria-live="polite" data-nearby-status>{{ isset($filters['latitude'], $filters['longitude']) ? 'Showing distances from your location.' : 'Location is optional. Use it to see the nearest providers.' }}</p>
        </form>
        <x-form-errors />
    </div>

    <nav class="d-flex gap-2 mb-3" aria-label="{{ __('directory.result_view') }}">
        @foreach (['list', 'map'] as $mode)
            <a class="btn {{ $filters['view'] === $mode ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('shops.index', [...$filters, 'view' => $mode]) }}" data-directory-view="{{ $mode }}" @if ($filters['view'] === $mode) aria-current="true" @endif>{{ __('directory.'.$mode) }}</a>
        @endforeach
    </nav>

    <section class="mb-4" id="shop-map-panel" aria-labelledby="shop-map-heading" @if ($filters['view'] !== 'map') hidden @endif>
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2"><h2 id="shop-map-heading" class="h4 mb-0">Matching providers on the map</h2><span class="small text-secondary">{{ $mapShops->count() }} {{ $mapShops->count() === 1 ? 'provider has' : 'providers have' }} a map location</span></div>
        <div id="shop-map" data-google-key="{{ config('services.google.maps_api_key') }}" data-shops="{{ $mapShops->toJson() }}" data-selected-shop-id="{{ $filters['selected'] ?? '' }}" class="surface shop-directory-map overflow-hidden d-flex align-items-center justify-content-center p-4 text-secondary" role="region" aria-label="Map of matching shops and mechanic service areas">Loading map…</div>
        <p class="small text-secondary mt-2 mb-0">{{ __('directory.mechanic_pin') }}</p>
    </section>

    <section id="shop-results" aria-labelledby="shop-results-heading" @if ($filters['view'] !== 'list') hidden @endif>
        <h2 id="shop-results-heading" class="h4 mb-1">{{ $shops->count() }} {{ $shops->count() === 1 ? 'provider found' : 'providers found' }}</h2>
        @if ($shops->count() > 1)
            <p id="shop-scroll-hint" class="small text-secondary mb-3">Swipe or scroll sideways to compare shops and mechanics. Use the arrow keys when the results are focused.</p>
        @endif
        <div class="shop-directory-grid {{ $shops->isEmpty() ? 'shop-directory-grid-empty' : '' }}" role="region" aria-label="Shop and mechanic results" @if ($shops->isNotEmpty()) tabindex="0" @endif @if ($shops->count() > 1) aria-describedby="shop-scroll-hint" @endif>
            @forelse ($shops as $shop)
                <div>
                    <article class="surface h-100 d-flex flex-column overflow-hidden">
                        <x-uploaded-image class="shop-directory-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="shop-directory-identity mb-2">
                                <x-uploaded-image class="shop-directory-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" />
                                <div class="shop-directory-identity-content">
                                    <h3 class="h5 mb-0">{{ $shop->name }}</h3>
                                    <span class="small text-secondary">{{ $shop->providerLabel() }}</span>
                                    @if ($shop->activePlanSlug() !== 'free')<span class="badge text-bg-primary mt-1">{{ ucfirst($shop->activePlanSlug()) }} listing</span>@endif
                                    <div class="shop-directory-rating mt-2">
                                        @if ($shop->total_reviews > 0)
                                            <x-star-rating class="small" :rating="$shop->average_rating" />
                                            <span class="small text-secondary">{{ $shop->total_reviews }} {{ (int) $shop->total_reviews === 1 ? 'review' : 'reviews' }}</span>
                                        @else
                                            <span class="small text-secondary">Not yet rated</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <p class="small mb-2">
                                <i class="bi bi-geo-alt me-1 text-secondary" aria-hidden="true"></i>
                                @if ($shop->publicMapPosition() !== null)
                                    <a class="shop-directory-location-link" href="{{ route('shops.index', [...$filters, 'view' => 'map', 'selected' => $shop->id]) }}#shop-map" aria-label="Show {{ $shop->name }} on map">{{ $shop->barangay->areaLabel() }} &middot; {{ $shop->isIndependentMechanic() ? 'Approximate service area' : 'Show on map' }}</a>
                                @else
                                    <span class="text-secondary">{{ $shop->barangay->areaLabel() }}</span>
                                @endif
                                @if ($distances->has($shop->id))<span class="text-secondary"> &middot; {{ $shop->isIndependentMechanic() ? 'Approx. ' : '' }}{{ number_format($distances->get($shop->id), 1) }} km {{ $shop->isIndependentMechanic() ? 'to service area' : 'away' }}</span>@endif
                            </p>
                            @if (isset($openShopIds[$shop->id]))
                                <p class="small mb-2 fw-semibold">{{ __('directory.open_now') }}</p>
                            @endif
                            <p class="small">{{ $shop->services->where('availability', true)->pluck('service.name')->implode(', ') ?: 'Services not listed yet.' }}</p>
                            <a class="btn btn-outline-primary mt-auto align-self-start" href="{{ route('shops.show', $shop) }}">View {{ $shop->isIndependentMechanic() ? 'mechanic' : 'shop' }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="shop-directory-empty"><div class="surface p-4"><h3 class="h4">No providers match these filters</h3><p class="text-secondary">Try another city or service, or clear your search to see all approved providers.</p><a class="btn btn-primary" href="{{ route('shops.index') }}">Show all providers</a></div></div>
            @endforelse
        </div>
    </section>


</x-layouts.app>
