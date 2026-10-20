<x-layouts.app title="Find a service shop">
    <div class="page-heading">
        <h1>Find a service shop</h1>
        <p>Search approved shops, check the map, and choose where to book—all in one place.</p>
    </div>

    <div class="surface p-4 mb-4">
        <form id="shop-filters" class="row g-3 align-items-end" method="GET" action="{{ route('shops.index') }}">
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-search">Shop name</label><input id="shop-search" type="search" maxlength="100" class="form-control" name="search" placeholder="Search by name" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-barangay">Barangay</label><select id="shop-barangay" class="form-select" name="barangay"><option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected(($filters['barangay'] ?? null) == $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-service">Service</label><select id="shop-service" class="form-select" name="service"><option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service'] ?? null) == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-sort">Sort by</label><select id="shop-sort" class="form-select" name="sort"><option value="name" @selected($filters['sort'] === 'name')>Name: A to Z</option><option value="rating" @selected($filters['sort'] === 'rating')>Highest rating</option><option value="distance" @selected($filters['sort'] === 'distance') @disabled(! isset($filters['latitude'], $filters['longitude']))>Nearest to me</option></select></div>
            <input id="latitude" type="hidden" name="latitude" value="{{ $filters['latitude'] ?? '' }}"><input id="longitude" type="hidden" name="longitude" value="{{ $filters['longitude'] ?? '' }}">
            <div class="col-12 d-flex flex-wrap align-items-center gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-2" aria-hidden="true"></i>Find shops</button><button id="nearby-button" class="btn btn-outline-primary" type="button"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Use my location</button><a class="btn btn-link" href="{{ route('shops.index') }}">Clear filters</a></div>
            <p id="location-status" class="col-12 small text-secondary mb-0" role="status" aria-live="polite">{{ isset($filters['latitude'], $filters['longitude']) ? 'Showing distances from your location.' : 'Location is optional. Use it to see the nearest shops.' }}</p>
        </form>
        <x-form-errors />
    </div>

    <section class="mb-4" aria-labelledby="shop-map-heading">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2"><h2 id="shop-map-heading" class="h4 mb-0">Matching shops on the map</h2><span class="small text-secondary">{{ $mapShops->count() }} {{ $mapShops->count() === 1 ? 'shop has' : 'shops have' }} a map location</span></div>
        <div id="shop-map" class="surface shop-directory-map overflow-hidden d-flex align-items-center justify-content-center p-4 text-secondary" role="region" aria-label="Map of matching service shops">Loading map…</div>
        <p class="small text-secondary mt-2 mb-0">Shops without a map location still appear in the results below.</p>
    </section>

    <section id="shop-results" aria-labelledby="shop-results-heading">
        <h2 id="shop-results-heading" class="h4 mb-3">{{ $shops->count() }} {{ $shops->count() === 1 ? 'shop found' : 'shops found' }}</h2>
        <div class="row g-4">
            @forelse ($shops as $shop)
                <div class="col-md-6 col-xl-4">
                    <article class="surface h-100 d-flex flex-column overflow-hidden">
                        <x-uploaded-image class="shop-directory-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="badge text-bg-light"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>Approved shop</span>@if ($shop->total_reviews > 0)<x-star-rating class="small" :rating="$shop->average_rating" />@else<span class="small text-secondary">Not yet rated</span>@endif</div>
                            <div class="d-flex align-items-center gap-3 mb-2"><x-uploaded-image class="shop-directory-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" /><h3 class="h5 mb-0">{{ $shop->name }}</h3></div>
                            <p class="text-secondary small mb-2"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $shop->barangay->name }}@if ($distances->has($shop->id)) · {{ number_format($distances->get($shop->id), 1) }} km away @endif</p>
                            <p class="small">{{ $shop->services->where('availability', true)->pluck('service.name')->implode(', ') ?: 'Services not listed yet.' }}</p>
                            <a class="btn btn-outline-primary mt-auto align-self-start" href="{{ route('shops.show', $shop) }}">View shop <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="surface p-5 text-center"><i class="bi bi-search fs-2 text-secondary" aria-hidden="true"></i><h3 class="h4 mt-3">No shops match these filters</h3><p class="text-secondary">Try another service or barangay, or clear your search to see all approved shops.</p><a class="btn btn-primary" href="{{ route('shops.index') }}">Show all shops</a></div></div>
            @endforelse
        </div>
    </section>

    @push('scripts')
        <script>
            const mapShops = {{ Illuminate\Support\Js::from($mapShops) }};
            const mapElement = document.querySelector('#shop-map');
            const latitudeInput = document.querySelector('#latitude');
            const longitudeInput = document.querySelector('#longitude');
            const savedLocation = latitudeInput.value !== '' && longitudeInput.value !== ''
                ? [Number(latitudeInput.value), Number(longitudeInput.value)] : null;

            const showMapMessage = message => { mapElement.textContent = message; };
            const renderMap = () => {
                mapElement.textContent = '';
                mapElement.classList.remove('d-flex', 'align-items-center', 'justify-content-center', 'p-4', 'text-secondary');
                const map = L.map(mapElement).setView([10.2443, 123.7898], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                const bounds = [];
                mapShops.forEach(shop => {
                    const point = [shop.latitude, shop.longitude];
                    bounds.push(point);
                    const popup = document.createElement('div');
                    const name = document.createElement('strong');
                    name.textContent = shop.name;
                    const link = document.createElement('a');
                    link.href = shop.url;
                    link.textContent = 'View shop';
                    popup.append(name, document.createElement('br'), link);
                    L.marker(point).addTo(map).bindPopup(popup);
                });
                if (savedLocation) {
                    bounds.push(savedLocation);
                    L.circleMarker(savedLocation, { radius: 7, color: '#f97316', fillOpacity: 1 }).addTo(map).bindPopup('Your approximate location');
                }
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            };

            if (!mapShops.length) {
                showMapMessage('No matching shops have a map location yet. Browse the shop cards below.');
            } else {
                const loadMap = () => {
                    let stylesheetReady = false;
                    let scriptReady = false;
                    const timeout = window.setTimeout(() => showMapMessage('The map could not load. You can still browse and choose a shop below.'), 8000);
                    const maybeRenderMap = () => {
                        if (stylesheetReady && scriptReady) {
                            window.clearTimeout(timeout);
                            renderMap();
                        }
                    };
                    const stylesheet = document.createElement('link');
                    stylesheet.rel = 'stylesheet';
                    stylesheet.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    stylesheet.integrity = 'sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==';
                    stylesheet.crossOrigin = 'anonymous';
                    stylesheet.onload = () => { stylesheetReady = true; maybeRenderMap(); };
                    stylesheet.onerror = () => { window.clearTimeout(timeout); showMapMessage('The map could not load. You can still browse and choose a shop below.'); };
                    document.head.append(stylesheet);
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.integrity = 'sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==';
                    script.crossOrigin = 'anonymous';
                    script.onload = () => { scriptReady = true; maybeRenderMap(); };
                    script.onerror = () => { window.clearTimeout(timeout); showMapMessage('The map could not load. You can still browse and choose a shop below.'); };
                    document.body.append(script);
                };
                if (document.readyState === 'complete') {
                    loadMap();
                } else {
                    window.addEventListener('load', loadMap, { once: true });
                }
            }

            const nearbyButton = document.querySelector('#nearby-button');
            const locationStatus = document.querySelector('#location-status');
            nearbyButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    locationStatus.textContent = 'Location is not supported by this browser. You can still browse all shops.';
                    return;
                }
                nearbyButton.disabled = true;
                locationStatus.textContent = 'Finding your location…';
                navigator.geolocation.getCurrentPosition(position => {
                    latitudeInput.value = position.coords.latitude;
                    longitudeInput.value = position.coords.longitude;
                    document.querySelector('#shop-sort').querySelector('[value="distance"]').disabled = false;
                    document.querySelector('#shop-sort').value = 'distance';
                    document.querySelector('#shop-filters').requestSubmit();
                }, () => {
                    nearbyButton.disabled = false;
                    locationStatus.textContent = 'We could not access your location. You can still browse all shops.';
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
            });
        </script>
    @endpush
</x-layouts.app>
