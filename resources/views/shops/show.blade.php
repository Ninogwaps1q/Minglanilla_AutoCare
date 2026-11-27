<x-layouts.app :title="$shop->name">
    <div class="shop-public-hero surface overflow-hidden mb-4">
        <x-uploaded-image class="shop-public-cover" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" />
        <div class="shop-public-heading p-4">
            <x-uploaded-image class="shop-public-logo" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" />
            <div>
                <h1 class="mb-1">{{ $shop->name }}</h1>
                <p class="text-secondary mb-2">{{ $shop->providerLabel() }} · {{ $shop->isIndependentMechanic() ? 'Mobile service around '.$shop->barangay->areaLabel() : $shop->address.', '.$shop->barangay->areaLabel() }}</p>
                @if ($shop->isIndependentMechanic())<p class="small text-secondary mb-2">The mechanic travels to your service address. Confirm any travel details before the appointment.</p>@endif
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
            @if ($promotions->isNotEmpty())
                <section class="surface p-4" aria-labelledby="shop-promotions-heading">
                    <h2 class="h4" id="shop-promotions-heading">{{ __('extensions.promo.title') }}</h2>
                    <ul class="mb-0">
                        @foreach ($promotions as $promotion)
                            <li>{{ $promotion->name }} · {{ $promotion->discountLabel() }} · {{ $promotion->code ? __('extensions.promo.use_code', ['code' => $promotion->code]) : __('extensions.promo.automatic') }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
            <section class="surface p-4" aria-labelledby="shop-about-heading">
                <h2 id="shop-about-heading" class="h4">About this {{ $shop->isIndependentMechanic() ? 'mechanic' : 'shop' }}</h2>
                <p>{{ $shop->description ?: 'No description provided.' }}</p>
                <h3 class="h5 mt-4">Services and listed prices</h3>
                <div class="list-group list-group-flush">
                    @forelse ($shop->services->where('availability', true) as $offer)
                        <div class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <span>{{ $offer->service->name }}</span>
                                <a class="small d-block" href="{{ route('services.compare', $offer->service) }}">{{ __('directory.compare_prices') }}</a>
                            </div>
                            <strong class="price">₱{{ number_format($offer->price, 2) }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary">No active services listed. Message the provider to ask what is available.</div>
                    @endforelse
                </div>
                <p class="text-secondary small mb-0 mt-3">The listed service price may not include additional repair work. The provider must send an estimate for your approval before extra work begins.</p>
            </section>

            @if ($shop->isIndependentMechanic())
                @php($profile = $shop->mechanic_profile ?? [])
                <section class="surface p-4" aria-labelledby="mechanic-profile-heading">
                    <h2 id="mechanic-profile-heading" class="h5">{{ __('mechanic.portfolio') }}</h2>
                    <p class="mb-2">{{ __($shop->acceptsNewBookings() ? 'mechanic.online' : 'mechanic.offline') }} @if ($shop->offersHomeService()) · {{ __('mechanic.home_service') }} @endif</p>
                    <dl class="mb-0">
                        @foreach (['experience_years', 'specializations', 'certifications'] as $field)
                            @if (isset($profile[$field]))
                                <dt>{{ __('mechanic.'.$field) }}</dt><dd class="text-break">{{ $profile[$field] }}</dd>
                            @endif
                        @endforeach
                        @if (! empty($profile['vehicle_types']))
                            <dt>{{ __('mechanic.vehicle_types') }}</dt><dd>{{ collect($profile['vehicle_types'])->map(fn ($type) => __('mechanic.'.$type))->implode(', ') }}</dd>
                        @endif
                        <dt>{{ __('mechanic.completed') }}</dt><dd>{{ $shop->completed_services_count }}</dd>
                    </dl>
                    @foreach ($shop->mechanic_portfolio ?? [] as $id => $project)
                        @continue(! ($project['permission'] ?? false))
                        <article class="border-top mt-3 pt-3 text-break">
                            <h3 class="h6">{{ $project['title'] }}</h3>
                            <p class="small text-secondary">{{ $project['vehicle'] }}</p>
                            <p>{{ $project['description'] }}</p>
                            <div class="row g-2">
                                @foreach (['before', 'after'] as $phase)
                                    @if (! empty($project[$phase]))
                                        <div class="col-6"><figure class="mb-0"><img class="img-fluid rounded" loading="lazy" src="{{ route('shops.portfolio.photo', [$shop, $id, $phase]) }}" alt="{{ ucfirst($phase) }} {{ $project['title'] }}"><figcaption class="small text-secondary">{{ ucfirst($phase) }}</figcaption></figure></div>
                                    @endif
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </section>
            @endif
            <section class="surface p-4" aria-labelledby="shop-reviews-heading">
                <x-form-errors />
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
                            <div class="mt-3 ps-3 border-start"><strong>Provider response</strong><p class="mb-0">{{ $review->response }}</p></div>
                        @endif
                        @auth
                            <x-report-review :review="$review" />
                        @endauth
                    </article>
                @empty
                    <p class="text-secondary mb-0">No published reviews yet. Be the first to share your experience after a completed repair.</p>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="surface p-4">
                <h2 class="h5">Ready to get started?</h2>
                <p class="text-secondary small">Choose a service or ask the provider a question before booking.</p>
                <div class="d-grid gap-2 mb-4">
                    @auth
                        @if (auth()->user()->role === 'customer')
                            @if ($shop->services->contains('availability', true) && $shop->acceptsNewBookings())
                                <a class="btn btn-primary" href="{{ route('customer.appointments.create', ['shop_id' => $shop->id]) }}">Book a service</a>
                            @elseif (! $shop->acceptsNewBookings())
                                <p class="small text-secondary mb-0">This provider is listed for discovery and is not accepting online bookings right now.</p>
                            @endif
                            <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $shop) }}">Message {{ $shop->isIndependentMechanic() ? 'mechanic' : 'shop' }}</a>
                            <form method="POST" action="{{ route($isFavorite ? 'customer.favorites.destroy' : 'customer.favorites.store', $shop) }}">
                                @csrf
                                @if ($isFavorite) @method('DELETE') @endif
                                <button class="btn btn-link" type="submit">{{ __($isFavorite ? 'directory.remove_saved_provider' : 'directory.save_provider') }}</button>
                            </form>
                        @endif
                    @else
                        @if ($shop->services->contains('availability', true) && $shop->acceptsNewBookings())
                            <a class="btn btn-primary" href="{{ route('customer.appointments.create', ['shop_id' => $shop->id]) }}">{{ __('booking.sign_in_to_book') }}</a>
                            <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $shop) }}">{{ __('booking.sign_in_to_message') }}</a>
                        @else
                            <a class="btn btn-primary" href="{{ route('customer.messages.show', $shop) }}">{{ __('booking.sign_in_to_message') }}</a>
                        @endif
                    @endauth
                    @if (! $shop->isIndependentMechanic() && ($shop->latitude === null || $shop->longitude === null))
                        <span class="text-secondary small">Map location is not available for this provider yet.</span>
                    @endif
                </div>
                <h3 class="h6">Contact</h3>
                <p class="mb-0">{{ $shop->phone }}<br>{{ $shop->email }}</p>
                @if (! $shop->isIndependentMechanic() && $shop->latitude !== null && $shop->longitude !== null)
                    <x-driving-directions id="shop-directions" class="mt-4 pt-4 border-top" :latitude="$shop->latitude" :longitude="$shop->longitude" :destination-label="$shop->name" title="Route to this shop" />
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
