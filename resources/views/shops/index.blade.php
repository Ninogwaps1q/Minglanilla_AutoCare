<x-layouts.app title="Shop directory">
    <div class="page-heading d-flex justify-content-between align-items-start gap-3">
        <div><h1>Find your next service shop</h1><p>Browse approved automotive shops in Minglanilla. Compare available services before you book.</p></div>
        <a class="btn btn-outline-primary flex-shrink-0" href="{{ route('map.index', array_filter(['barangay' => $filters['barangay'] ?? null, 'service' => $filters['service'] ?? null])) }}"><i class="bi bi-map me-2" aria-hidden="true"></i>Map view</a>
    </div>
    <div class="surface p-4 mb-4">
        <form class="row g-3 align-items-end" method="GET" action="{{ route('shops.index') }}">
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-search">Shop name</label><input id="shop-search" type="search" maxlength="100" class="form-control" name="search" placeholder="Search by name" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-barangay">Barangay</label><select id="shop-barangay" class="form-select" name="barangay"><option value="">All barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected(($filters['barangay'] ?? null) == $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-service">Service</label><select id="shop-service" class="form-select" name="service"><option value="">All services</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service'] ?? null) == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-md-6 col-xl-3"><label class="form-label" for="shop-sort">Sort by</label><select id="shop-sort" class="form-select" name="sort"><option value="name">Name: A to Z</option><option value="rating" @selected(($filters['sort'] ?? null) === 'rating')>Highest rating</option></select></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search me-2" aria-hidden="true"></i>Find shops</button><a class="btn btn-outline-secondary" href="{{ route('shops.index') }}">Clear filters</a></div>
        </form>
        <x-form-errors />
    </div>
    <p class="text-secondary small">{{ $shops->count() }} {{ $shops->count() === 1 ? 'shop matches' : 'shops match' }} your search</p>
    <div class="row g-4">
        @forelse ($shops as $shop)
            <div class="col-md-6 col-xl-4">
                <article class="surface h-100 d-flex flex-column overflow-hidden">
                    <x-uploaded-image class="shop-directory-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
                    <div class="p-4 d-flex flex-column flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="badge text-bg-light"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>Approved shop</span>@if ($shop->total_reviews > 0)<x-star-rating class="small" :rating="$shop->average_rating" />@else<span class="small text-secondary">Not yet rated</span>@endif</div>
                    <div class="d-flex align-items-center gap-3 mb-2"><x-uploaded-image class="shop-directory-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" /><h2 class="h4 mb-0">{{ $shop->name }}</h2></div>
                    <p class="text-secondary small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $shop->barangay->name }}</p>
                    <p class="small">{{ $shop->services->where('availability', true)->pluck('service.name')->implode(', ') ?: 'Services not listed yet.' }}</p>
                    <a class="btn btn-outline-primary mt-auto align-self-start" href="{{ route('shops.show', $shop) }}">View shop <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a></div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-5 text-center"><i class="bi bi-search fs-2 text-secondary" aria-hidden="true"></i><h2 class="h4 mt-3">No shops match these filters</h2><p class="text-secondary">Try another service or barangay, or clear your search to see all approved shops.</p><a class="btn btn-primary" href="{{ route('shops.index') }}">Show all shops</a></div></div>
        @endforelse
    </div>
</x-layouts.app>
