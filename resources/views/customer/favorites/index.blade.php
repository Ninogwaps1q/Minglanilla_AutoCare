<x-layouts.app title="Favorite shops">
    <div class="page-heading">
        <h1>Favorite shops</h1>
        <p>Keep trusted service providers close for your next visit.</p>
    </div>
    <div class="row g-3">
        @forelse ($favorites as $favorite)
            <div class="col-md-6">
                <article class="surface p-4 h-100">
                    <h2 class="h4">{{ $favorite->shop->name }}</h2>
                    <p class="text-secondary">{{ $favorite->shop->barangay->name }} · {{ $favorite->shop->phone }}</p>
                    @if ($favorite->shop->verification_status === 'approved' && $favorite->shop->shop_status === 'active')
                        <a class="btn btn-outline-primary me-2" href="{{ route('shops.show', $favorite->shop) }}">View shop</a>
                    @else
                        <p class="small text-secondary">This shop is currently unavailable for new bookings.</p>
                    @endif
                    <form method="POST" action="{{ route('customer.favorites.destroy', $favorite->shop) }}" class="d-inline">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger">Remove favorite</button>
                    </form>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-4 text-secondary">No saved shops yet. <a href="{{ route('shops.index') }}">Explore verified shops</a>.</div></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $favorites->links() }}</div>
</x-layouts.app>
