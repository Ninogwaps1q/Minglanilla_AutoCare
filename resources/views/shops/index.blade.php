<x-layouts.app :title="__('ui.Find shops & mechanics')">
    <div class="page-heading">
        <h1>{{ $locationLabel ? __('directory.near_city', ['city' => $locationLabel]) : __('ui.Find shops & mechanics') }}</h1>
    </div>

    <div class="surface directory-filter-panel p-3 mb-3">
        <form id="shop-filters" class="row g-3 align-items-end" method="GET" action="{{ route('shops.index') }}" data-nearby-search>
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
            <div class="col-md-7"><label class="form-label" for="shop-search">{{ __('directory.search') }}</label><input id="shop-search" type="search" maxlength="100" class="form-control" name="search" placeholder="{{ __('directory.search_hint') }}" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-5 col-lg-3"><label class="form-label" for="shop-service">Service</label><select id="shop-service" class="form-select" name="service"><option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service'] ?? null) == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-md-5 col-lg-2"><label class="form-label" for="shop-radius">{{ __('directory.distance' ) }}</label><select id="shop-radius" class="form-select" name="radius"><option value="">{{ __('directory.any_distance') }}</option>@foreach ([5, 10, 25, 50, 100] as $radius)<option value="{{ $radius }}" @selected(($filters['radius'] ?? null) == $radius)>{{ __('directory.radius', ['km' => $radius]) }}</option>@endforeach</select></div>
            <div class="col-12">
                <details class="workspace-disclosure" @if (isset($filters['region_id']) || isset($filters['provider']) || isset($filters['rating']) || isset($filters['max_price']) || isset($filters['vehicle_type']) || ($filters['home_service'] ?? false) || ($filters['open_now'] ?? false) || $filters['sort'] === 'rating') open @endif>
                    <summary class="fw-semibold">{{ __('directory.more_filters') }}</summary>
                    <div class="row g-3 align-items-end pt-2">
                        <div class="col-12"><x-address-picker prefix="directory-area" area-only :area="$filters" /></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="provider-filter">Provider type</label><select id="provider-filter" class="form-select" name="provider"><option value="">Shops and mechanics</option><option value="shop" @selected(($filters['provider'] ?? '') === 'shop')>Service shops</option><option value="independent_mechanic" @selected(($filters['provider'] ?? '') === 'independent_mechanic')>Mobile mechanics</option></select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-sort">Sort by</label><select id="shop-sort" class="form-select" name="sort"><option value="name" @selected($filters['sort'] === 'name')>{{ __('directory.featured_sort') }}</option><option value="rating" @selected($filters['sort'] === 'rating')>Highest rating</option><option value="distance" @selected($filters['sort'] === 'distance') @disabled(! isset($filters['latitude'], $filters['longitude']))>Nearest to me</option></select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="minimum-rating">{{ __('directory.rating') }}</label><select id="minimum-rating" class="form-select" name="rating"><option value="">{{ __('directory.any_rating') }}</option>@foreach (['3', '4', '4.5'] as $rating)<option value="{{ $rating }}" @selected(($filters['rating'] ?? null) === $rating)>{{ __('directory.rating_option', ['rating' => $rating]) }}</option>@endforeach</select></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="max-price">Maximum listed price (PHP)</label><input id="max-price" class="form-control" name="max_price" type="number" min="0" max="1000000" step="0.01" value="{{ $filters['max_price'] ?? '' }}"></div>
                        <div class="col-md-6 col-xl-3"><label class="form-label" for="vehicle-type">{{ __('mechanic.vehicle_types') }}</label><select id="vehicle-type" class="form-select" name="vehicle_type"><option value="">Any listed vehicle type</option>@foreach (['car', 'motorcycle', 'truck'] as $type)<option value="{{ $type }}" @selected(($filters['vehicle_type'] ?? null) === $type)>{{ __('mechanic.'.$type) }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="home_service" value="1" @checked($filters['home_service'] ?? false)> {{ __('mechanic.home_service') }}</label></div>
                    </div>
                </details>
            </div>
            <div class="directory-chips col-12" aria-label="{{ __('directory.quick_filters') }}">
                @foreach (['open_now', 'motorcycle', 'roadside'] as $filter)
                    <label class="directory-chip"><input type="checkbox" class="visually-hidden" name="{{ $filter }}" value="1" @checked($filters[$filter] ?? false)><span>{{ __('directory.'.$filter) }}</span></label>
                @endforeach
                @foreach ($quickServices as $quickService)
                    <a class="directory-chip" href="{{ route('shops.index', [...$filters, 'service' => ($filters['service'] ?? null) == $quickService->id ? null : $quickService->id, 'selected' => null]) }}" @if (($filters['service'] ?? null) == $quickService->id) aria-current="true" @endif><span>{{ $quickService->name }}</span></a>
                @endforeach
            </div>
            <input id="latitude" type="hidden" name="latitude" value="{{ $filters['latitude'] ?? '' }}"><input id="longitude" type="hidden" name="longitude" value="{{ $filters['longitude'] ?? '' }}">
            <div class="col-12 d-flex flex-wrap align-items-center gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-2" aria-hidden="true"></i>{{ __('ui.' . 'Find providers') }}</button><button id="nearby-button" class="btn btn-outline-primary" type="button" data-nearby-button><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>{{ __('ui.' . 'Use my location') }}</button><a class="btn btn-link" href="{{ route('shops.index') }}">{{ __('directory.clear') }}</a></div>
            <p id="location-status" class="col-12 small text-secondary mb-0" role="status" aria-live="polite" data-nearby-status>{{ isset($filters['latitude'], $filters['longitude']) ? __('directory.location_used') : (isset($filters['radius']) ? __('directory.radius_needs_location') : __('directory.location_optional')) }}</p>
        </form>
        <x-form-errors />
    </div>

    <div class="directory-results-bar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 id="shop-results-heading" class="task-section-title mb-0">
            {{ trans_choice('directory.results', $shops->count()) }}
            @if ($filters['sort'] === 'distance') <span class="text-secondary fw-normal">· {{ __('directory.distance_sort') }}</span>@endif
        </h2>
        <nav class="d-flex gap-1" aria-label="{{ __('directory.result_view') }}">
            @foreach (['list', 'map'] as $mode)
                <a class="btn {{ $filters['view'] === $mode ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('shops.index', [...$filters, 'view' => $mode]) }}" data-directory-view="{{ $mode }}" @if ($filters['view'] === $mode) aria-current="true" @endif>{{ __('directory.'.$mode) }}</a>
            @endforeach
        </nav>
    </div>

    <div class="directory-split" data-directory-mode="{{ $filters['view'] }}">
        <section id="shop-results" aria-labelledby="shop-results-heading">
            <div class="directory-rows divided-rows" role="region" aria-label="{{ __('directory.results_region') }}" @if ($shops->isNotEmpty()) tabindex="0" @endif>
                @forelse ($shops as $shop)
                    @php
                        $listing = $listings[$shop->id];
                        $status = $hours[$shop->id];
                        $selected = ($filters['selected'] ?? null) == $shop->id;
                    @endphp
                    <article @class(['directory-row', 'is-selected' => $selected]) data-provider-row="{{ $shop->id }}">
                        <div class="directory-row-logo" aria-hidden="true">
                            <span>{{ collect(preg_split('/\s+/', trim($shop->name)))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') }}</span>
                            @if ($shop->logo)
                                <img src="{{ url('media/'.ltrim($shop->logo, '/')) }}" width="48" height="48" loading="lazy" decoding="async" data-directory-logo alt="">
                            @endif
                        </div>
                        <div class="directory-row-main">
                            <div class="d-flex flex-wrap align-items-baseline gap-2">
                                <h3 class="task-section-title mb-0"><a href="{{ route('shops.show', $shop) }}">{{ $shop->name }}</a></h3>
                                <span class="badge {{ $shop->isIndependentMechanic() ? 'text-bg-info' : 'text-bg-success' }}">{{ __($shop->isIndependentMechanic() ? 'directory.independent' : 'directory.verified') }}</span>
                            </div>
                            <div class="directory-row-meta small mt-2">
                                @if ($shop->total_reviews > 0)
                                    <x-star-rating :rating="$shop->average_rating" />
                                    <span class="text-secondary">{{ trans_choice('directory.reviews', $shop->total_reviews) }}</span>
                                @else
                                    <span class="text-secondary">{{ __('directory.not_rated') }}</span>
                                @endif
                                @if ($distances->has($shop->id))
                                    <span class="text-secondary">{{ __($shop->isIndependentMechanic() ? 'directory.approx_distance' : 'directory.shop_distance', ['distance' => number_format($distances->get($shop->id), 1)]) }}</span>
                                @endif
                                @if ($status)
                                    <span class="badge {{ $status['open'] ? 'text-bg-success' : 'text-bg-warning' }}">{{ $status['time'] ? __($status['open'] ? 'directory.open_until' : 'directory.opens_at', ['time' => $status['time']]) : __('directory.closed') }}</span>
                                @endif
                            </div>
                            <p class="small text-secondary mt-2 mb-0">{{ $shop->barangay->areaLabel() }}</p>
                            @if ($shop->isIndependentMechanic())
                                <p class="small text-secondary mb-0">
                                    {{ __($shop->acceptsNewBookings() ? 'mechanic.online' : 'mechanic.offline') }}
                                    @if ($shop->offersHomeService()) · {{ __('mechanic.home_service') }} @endif
                                    @if (isset($shop->mechanic_settings['radius_km'])) · {{ $shop->mechanic_settings['radius_km'] }} km @endif
                                </p>
                                @if (! empty($shop->mechanic_profile['specializations']))
                                    <p class="small text-secondary text-break mb-0">{{ $shop->mechanic_profile['specializations'] }}</p>
                                @endif
                            @endif
                            @if ($shop->services->isNotEmpty())
                                <p class="small text-secondary mb-0">{{ $shop->services->pluck('service.name')->unique()->take(4)->implode(', ') }}</p>
                            @endif
                            @if ($shop->publicMapPosition())
                                <a class="directory-map-link small" href="{{ route('shops.index', [...$filters, 'view' => 'map', 'selected' => $shop->id]) }}#shop-map" data-select-provider="{{ $shop->id }}" @if ($selected) aria-current="location" @endif aria-label="{{ __('directory.show_on_map_named', ['name' => $shop->name]) }}">{{ __('directory.show_on_map') }}</a>
                            @endif
                        </div>
                        <div class="directory-row-action">
                            @if ($listing['price'] !== null)
                                <span class="small text-secondary">{{ __('directory.starting_price', ['service' => $listing['service_name']]) }}</span>
                                <strong class="price">{{ $listing['price'] }}</strong>
                            @endif
                            <a class="btn btn-outline-primary" data-provider-action href="{{ $listing['booking_url'] ?? route('shops.show', $shop) }}">{{ __($listing['booking_url'] ? ($shop->isIndependentMechanic() ? 'directory.book' : 'directory.book_shop') : ($shop->isIndependentMechanic() ? 'directory.view_mechanic' : 'directory.view_shop')) }}</a>
                        </div>
                    </article>
                @empty
                    <div class="p-4">
                        <h3 class="task-section-title">{{ __('directory.no_results') }}</h3>
                        <p class="text-secondary">{{ __('directory.no_results_help') }}</p>
                        <a class="btn btn-primary" href="{{ route('shops.index') }}">{{ __('directory.show_all') }}</a>
                    </div>
                @endforelse
            </div>
        </section>
        <section id="shop-map-panel" aria-labelledby="shop-map-heading">
            <h2 id="shop-map-heading" class="visually-hidden">{{ __('directory.map_heading') }}</h2>
            <div id="shop-map" data-google-key="{{ config('services.google.maps_api_key') }}" data-shops="{{ $mapShops->toJson() }}" data-selected-shop-id="{{ $filters['selected'] ?? '' }}" data-copy="{{ json_encode(\Illuminate\Support\Arr::only(trans('directory'), ['map_empty', 'map_failure', 'directions', 'book_short', 'view_mechanic', 'view_shop', 'approx_area', 'my_location'])) }}" class="surface shop-directory-map overflow-hidden d-flex align-items-center justify-content-center p-4 text-secondary" role="region" aria-label="{{ __('directory.map_heading') }}" tabindex="-1">{{ __('directory.map_loading') }}</div>
            <p class="small text-secondary mt-2 mb-0">{{ __('directory.mechanic_pin') }}</p>
        </section>
    </div>
</x-layouts.app>
