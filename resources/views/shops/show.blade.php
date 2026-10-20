<x-layouts.app :title="$shop->name">
    <div class="shop-public-hero surface overflow-hidden mb-4">
        <x-uploaded-image class="shop-public-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
        <div class="shop-public-heading p-4">
            <x-uploaded-image class="shop-public-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" />
            <div>
                <h1 class="mb-1">{{ $shop->name }}</h1>
                <p class="text-secondary mb-2">{{ $shop->address }}, {{ $shop->barangay->name }}, Minglanilla, Cebu</p>
                @if ($shop->total_reviews > 0)
                    <div class="d-flex flex-wrap align-items-center gap-2"><x-star-rating :rating="$shop->average_rating" /><span class="text-secondary small">from {{ $shop->total_reviews }} {{ $shop->total_reviews === 1 ? 'review' : 'reviews' }}</span></div>
                @else
                    <span class="text-secondary small">Not yet rated</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4">
            <section class="surface p-4" aria-labelledby="shop-about-heading">
                <h2 id="shop-about-heading" class="h4">About this shop</h2>
                <p>{{ $shop->description ?: 'No description provided.' }}</p>
                <h3 class="h5 mt-4">Services and listed prices</h3>
                <div class="list-group list-group-flush">
                    @forelse ($shop->services->where('availability', true) as $offer)
                        <div class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                            <span>{{ $offer->service->name }}</span><strong>PHP {{ number_format($offer->price, 2) }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary">No active services listed. Message the shop to ask what is available.</div>
                    @endforelse
                </div>
                <p class="text-secondary small mb-0 mt-3">The listed service price may not include additional repair work. The shop must send an estimate for your approval before extra work begins.</p>
            </section>

            <section class="surface p-4" aria-labelledby="shop-reviews-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
                    <h2 id="shop-reviews-heading" class="h4 mb-0">Customer reviews</h2>
                    <span class="text-secondary small">Most recent published reviews</span>
                </div>
                @forelse ($shop->reviews as $review)
                    <article class="border-top py-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div><strong>{{ $review->customer->name }}</strong><span class="ms-2"><x-star-rating class="small" :rating="$review->rating" /></span></div>
                            <time class="text-secondary small" datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time>
                        </div>
                        <p class="mb-0">{{ $review->comment }}</p>
                        @if ($review->response)
                            <div class="mt-3 ps-3 border-start"><strong>Shop response</strong><p class="mb-0">{{ $review->response }}</p></div>
                        @endif
                    </article>
                @empty
                    <p class="text-secondary mb-0">No published reviews yet. Be the first to share your experience after a completed repair.</p>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="surface p-4">
                <h2 class="h5">Ready to get started?</h2>
                <p class="text-secondary small">Choose a service or ask the shop a question before booking.</p>
                <div class="d-grid gap-2 mb-4">
                    @auth
                        @if (auth()->user()->role === 'customer')
                            @if ($shop->services->contains('availability', true))
                                <a class="btn btn-primary" href="{{ route('customer.appointments.create', ['shop_id' => $shop->id]) }}">Book a service</a>
                            @endif
                            <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $shop) }}">Message shop</a>
                        @endif
                    @else
                        <a class="btn btn-primary" href="{{ route('login') }}">Sign in to book or message</a>
                    @endauth
                    @if ($shop->latitude !== null && $shop->longitude !== null)
                        <a class="btn btn-outline-secondary" href="{{ route('shops.index', ['search' => $shop->name, 'selected' => $shop->id]) }}#shop-map">View on map</a>
                    @else
                        <span class="text-secondary small">Map location is not available for this shop yet.</span>
                    @endif
                </div>
                <h3 class="h6">Contact</h3>
                <p class="mb-0">{{ $shop->phone }}<br>{{ $shop->email }}</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
