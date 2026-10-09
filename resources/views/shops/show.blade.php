<x-layouts.app :title="$shop->name">
    <div class="shop-public-hero surface overflow-hidden mb-4">
        <x-uploaded-image class="shop-public-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
        <div class="shop-public-heading p-4">
            <x-uploaded-image class="shop-public-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" />
            <div><h1 class="mb-1">{{ $shop->name }}</h1><p class="text-secondary mb-0">{{ $shop->address }}, {{ $shop->barangay->name }}, Minglanilla, Cebu</p></div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-8"><div class="surface p-4"><h2 class="h4">About this shop</h2><p>{{ $shop->description ?: 'No description provided.' }}</p><h2 class="h4 mt-4">Services and prices</h2><div class="list-group list-group-flush">@forelse($shop->services->where('availability', true) as $offer)<div class="list-group-item px-0 d-flex justify-content-between"><span>{{ $offer->service->name }}</span><strong>PHP {{ number_format($offer->price, 2) }}</strong></div>@empty<div class="text-secondary">No active services listed.</div>@endforelse</div></div></div>
        <aside class="col-lg-4"><div class="surface p-4"><h2 class="h5">Contact</h2><p>{{ $shop->phone }}<br>{{ $shop->email }}</p><a class="btn btn-outline-primary w-100" href="{{ route('map.index') }}">View on map</a></div></aside>
    </div>
</x-layouts.app>
