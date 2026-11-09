<x-layouts.app title="Roadside requests">
    <div class="page-heading">
        <h1>Roadside requests</h1>
        <p>Review customers assigned to your shop and locate where they requested help.</p>
    </div>
    <x-form-errors />
    <section class="roadside-request-list" aria-label="Assigned roadside requests">
        @forelse ($requests as $emergencyRequest)
            @php
                $shopHasLocation = $shop->latitude !== null && $shop->longitude !== null;
            @endphp
            <article class="surface roadside-request p-4" id="roadside-request-{{ $emergencyRequest->id }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $emergencyRequest->customer->name }}</h2>
                        <p class="text-secondary mb-0">{{ ucfirst(str_replace('_', ' ', $emergencyRequest->emergency_type)) }} · {{ $emergencyRequest->vehicle?->plate_number ?: 'Vehicle not specified' }}</p>
                    </div>
                    <span class="badge text-bg-{{ $emergencyRequest->status === 'completed' ? 'success' : ($emergencyRequest->status === 'cancelled' ? 'secondary' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $emergencyRequest->status) }}</span>
                </div>
                <div class="roadside-request-main">
                    <div>
                        <dl class="roadside-request-details">
                            <div><dt>Shared pickup location</dt><dd>{{ $emergencyRequest->address ?: 'GPS location shared by customer' }}</dd></div>
                            <div><dt>GPS coordinates</dt><dd>{{ $emergencyRequest->latitude }}, {{ $emergencyRequest->longitude }}</dd></div>
                            <div><dt>Requested</dt><dd>{{ $emergencyRequest->requested_at?->format('M j, Y · g:i A') ?? 'Time unavailable' }}</dd></div>
                            <div><dt>Contact</dt><dd>@if ($emergencyRequest->customer->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $emergencyRequest->customer->phone) }}">{{ $emergencyRequest->customer->phone }}</a>@else No phone number provided @endif</dd></div>
                        </dl>
                        @if ($emergencyRequest->allowedStatuses() !== [])
                            <form method="POST" action="{{ route('shop.emergency-requests.update', $emergencyRequest) }}" class="roadside-request-action d-flex flex-wrap align-items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="form-label" for="emergency-status-{{ $emergencyRequest->id }}">Next request status</label>
                                    <select id="emergency-status-{{ $emergencyRequest->id }}" class="form-select" name="status" required>
                                        @foreach ($emergencyRequest->allowedStatuses() as $status)
                                            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-primary" type="submit">Update status</button>
                            </form>
                        @else
                            <p class="small text-secondary mb-0">Request closed</p>
                        @endif
                    </div>
                    <div class="roadside-location-overview">
                        <h3 class="h6 mb-2">Customer pickup map</h3>
                        <div class="roadside-street-map" role="region" aria-label="Street map of {{ $emergencyRequest->customer->name }}'s pickup location" data-latitude="{{ $emergencyRequest->latitude }}" data-longitude="{{ $emergencyRequest->longitude }}" data-shop-latitude="{{ $shop->latitude }}" data-shop-longitude="{{ $shop->longitude }}">Loading street map…</div>
                        <div class="roadside-location-legend">
                            @if ($shopHasLocation)<span><i class="roadside-location-key shop" aria-hidden="true"></i>Your shop</span>@endif
                            <span><i class="roadside-location-key customer" aria-hidden="true"></i>Customer pickup</span>
                        </div>
                        <p class="small text-secondary mb-0">
                            @if ($shopHasLocation)
                                Orange: the customer's shared pickup point. Blue: your shop. This is not live tracking.
                            @else
                                Orange marks the customer's shared pickup point. Add your shop's map coordinates to show your shop too.
                            @endif
                        </p>
                    </div>
                </div>
            </article>
        @empty
            <div class="surface p-4">
                <h2 class="h5">No assigned roadside requests</h2>
                <p class="text-secondary mb-0">Requests sent to your shop will appear here with the customer's shared pickup location.</p>
            </div>
        @endforelse
    </section>
    @push('scripts')
        <script>
            (() => {
                const mapElements = [...document.querySelectorAll('.roadside-street-map')];
                if (!mapElements.length) return;
                let leafletPromise;
                const loadLeaflet = () => {
                    if (window.L) return Promise.resolve(window.L);
                    if (leafletPromise) return leafletPromise;
                    leafletPromise = new Promise((resolve, reject) => {
                        let cssReady = false;
                        let jsReady = false;
                        const timeout = window.setTimeout(() => reject(new Error('Map timed out')), 8000);
                        const ready = () => {
                            if (cssReady && jsReady) {
                                window.clearTimeout(timeout);
                                resolve(window.L);
                            }
                        };
                        const css = document.createElement('link');
                        css.rel = 'stylesheet';
                        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                        css.integrity = 'sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==';
                        css.crossOrigin = 'anonymous';
                        css.onload = () => { cssReady = true; ready(); };
                        css.onerror = () => reject(new Error('Map styles unavailable'));
                        document.head.append(css);
                        const js = document.createElement('script');
                        js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        js.integrity = 'sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==';
                        js.crossOrigin = 'anonymous';
                        js.onload = () => { jsReady = true; ready(); };
                        js.onerror = () => reject(new Error('Map script unavailable'));
                        document.body.append(js);
                    }).catch(error => { leafletPromise = null; throw error; });
                    return leafletPromise;
                };
                const renderMap = async element => {
                    const customer = [Number.parseFloat(element.dataset.latitude), Number.parseFloat(element.dataset.longitude)];
                    const shop = [Number.parseFloat(element.dataset.shopLatitude), Number.parseFloat(element.dataset.shopLongitude)];
                    if (!Number.isFinite(customer[0]) || !Number.isFinite(customer[1])) {
                        element.textContent = 'Pickup coordinates unavailable. Please contact the customer.';
                        return;
                    }
                    try {
                        const leaflet = await loadLeaflet();
                        element.textContent = '';
                        const map = leaflet.map(element, { scrollWheelZoom: false }).setView(customer, 15);
                        leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(map);
                        leaflet.circleMarker(customer, {
                            radius: 9, color: '#b45309', fillColor: '#f97316', fillOpacity: 1, weight: 2,
                        }).addTo(map).bindPopup('Customer pickup location').openPopup();
                        if (Number.isFinite(shop[0]) && Number.isFinite(shop[1])) {
                            leaflet.circleMarker(shop, {
                                radius: 8, color: '#1e40af', fillColor: '#2563eb', fillOpacity: 1, weight: 2,
                            }).addTo(map).bindPopup('Your shop');
                            map.fitBounds([customer, shop], { padding: [30, 30], maxZoom: 15 });
                        }
                        window.setTimeout(() => map.invalidateSize(), 100);
                    } catch {
                        element.textContent = 'Street map unavailable. Use the GPS coordinates shown beside it.';
                    }
                };
                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
                        if (!entry.isIntersecting) return;
                        observer.unobserve(entry.target);
                        renderMap(entry.target);
                    }), { rootMargin: '150px' });
                    mapElements.forEach(element => observer.observe(element));
                } else {
                    mapElements.forEach(renderMap);
                }
            })();
        </script>
    @endpush
</x-layouts.app>
