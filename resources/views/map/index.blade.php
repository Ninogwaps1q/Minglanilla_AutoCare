<x-layouts.app title="Find a shop">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="">
    <div class="page-heading">
        <h1>Find a service shop</h1>
        <p>Browse verified, active service providers in Minglanilla, Cebu. Share your location only to sort the current results by distance.</p>
    </div>
    <form class="surface p-3 row g-3 align-items-end mb-4" method="GET" action="{{ route('map.index') }}">
        <div class="col-md-4"><label for="barangay" class="form-label">Barangay</label><select id="barangay" class="form-select" name="barangay">
                <option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected(($filters['barangay'] ?? null)==$barangay->id)>{{ $barangay->name }}</option>@endforeach
            </select></div>
        <div class="col-md-4"><label for="service" class="form-label">Service</label><select id="service" class="form-select" name="service">
                <option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service'] ?? null)==$service->id)>{{ $service->name }}</option>@endforeach
            </select></div>
        <input id="latitude" type="hidden" name="latitude" value="{{ $filters['latitude'] ?? '' }}"><input id="longitude" type="hidden" name="longitude" value="{{ $filters['longitude'] ?? '' }}">
        <div class="col-md-4 d-flex flex-wrap gap-2"><button class="btn btn-primary">Apply filters</button><button id="nearby-button" class="btn btn-outline-primary" type="button">Use my location</button><span id="location-status" class="small text-secondary align-self-center" role="status" aria-live="polite"></span></div>
    </form>
    <div id="shop-map" class="surface mb-4" style="height: 460px"></div>
    <div id="shop-results" class="row g-3"></div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==" crossorigin=""></script>
    <script>
        const shops = {{ Illuminate\Support\Js::from($shops) }};
        const map = window.L ? L.map('shop-map').setView([10.2443, 123.7898], 13) : null;
        const markers = map ? L.layerGroup().addTo(map) : null;
        if (map) L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        if (!map) document.querySelector('#shop-map').textContent = 'The map could not load. You can still browse and sort shops below. Check your connection and reload to see the map.';
        const toRadians = value => value * Math.PI / 180;
        const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
        const distance = (from, to) => {
            const radius = 6371;
            const dLat = toRadians(to.latitude - from.latitude);
            const dLng = toRadians(to.longitude - from.longitude);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRadians(from.latitude)) * Math.cos(toRadians(to.latitude)) * Math.sin(dLng / 2) ** 2;
            return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        };
        const render = visitor => {
            const results = shops.map(shop => ({
                ...shop,
                distance: visitor ? distance(visitor, shop) : null
            })).sort((a, b) => a.distance === null ? a.name.localeCompare(b.name) : a.distance - b.distance);
            document.querySelector('#shop-results').innerHTML = results.length ? results.map(shop => `<div class="col-md-6 col-xl-4"><article class="surface p-4 h-100"><h2 class="h5">${escapeHtml(shop.name)}</h2><p class="text-secondary mb-2">${escapeHtml(shop.address)}</p><p class="mb-2">Rating: ${shop.rating.toFixed(1)}${shop.distance !== null ? ` · ${shop.distance.toFixed(1)} km away` : ''}</p><p class="small mb-0">${escapeHtml(shop.services.join(', ') || 'Services not listed')}</p></article></div>`).join('') : '<div class="col-12"><div class="surface p-4 text-secondary">No approved shops match these filters.</div></div>';
            if (!map) return;
            markers.clearLayers();
            results.forEach(shop => L.marker([shop.latitude, shop.longitude]).addTo(markers).bindPopup(`<strong>${escapeHtml(shop.name)}</strong><br>${escapeHtml(shop.address)}`));
            if (results.length) map.fitBounds(results.map(shop => [shop.latitude, shop.longitude]), {
                padding: [32, 32], maxZoom: 15
            });
        };
        const savedLatitude = document.querySelector('#latitude').value;
        const savedLongitude = document.querySelector('#longitude').value;
        const savedLocation = savedLatitude !== '' && savedLongitude !== ''
            ? { latitude: Number(savedLatitude), longitude: Number(savedLongitude) } : null;
        render(savedLocation);
        const nearbyButton = document.querySelector('#nearby-button');
        const locationStatus = document.querySelector('#location-status');
        if (savedLocation) locationStatus.textContent = 'Results are sorted by distance.';
        nearbyButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                locationStatus.textContent = 'Location is not supported by this browser.';
                return;
            }
            nearbyButton.disabled = true;
            locationStatus.textContent = 'Finding your location…';
            navigator.geolocation.getCurrentPosition(position => {
            document.querySelector('#latitude').value = position.coords.latitude;
            document.querySelector('#longitude').value = position.coords.longitude;
            render({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            });
            locationStatus.textContent = 'Results are sorted by distance.';
            nearbyButton.disabled = false;
        }, () => {
            locationStatus.textContent = 'We could not access your location. You can still browse all shops.';
            nearbyButton.disabled = false;
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
        });
    </script>
</x-layouts.app>
