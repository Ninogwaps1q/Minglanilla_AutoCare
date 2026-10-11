<x-layouts.public>
    <section class="hero-section">
        <div class="container py-5 py-lg-6">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <h1>Find Trusted Auto Repair Services in Minglanilla.</h1>
                    <p class="hero-copy">Discover local automotive service providers, compare services, book
                        appointments, and manage your vehicle maintenance in one convenient platform.</p>
                    <form action="{{ route('shops.index') }}" method="GET" class="mb-4">
                        <label for="home-search" class="form-label text-white">Find a local shop</label>
                        <div class="input-group"><input id="home-search" class="form-control" name="search"
                                type="search" maxlength="100" placeholder="Search by shop name"><button
                                class="btn btn-primary" type="submit">Search shops</button></div>
                    </form>
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <a class="btn btn-accent btn-lg" href="{{ route('shops.index') }}">Explore service shops <i
                                class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                        <a class="btn btn-outline-light btn-lg" href="{{ route('map.index') }}">Find nearby shops</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-route-card">
                        <div class="hero-route-icon"><i class="bi bi-car-front-fill" aria-hidden="true"></i></div>
                        <h2 class="h3">From booking to service history</h2>
                        <p>One clear path for customers and local automotive service shops.</p>
                        <ol class="hero-steps mb-0">
                            <li>Choose a verified shop and service.</li>
                            <li>Track appointments, quotations, and repairs.</li>
                            <li>Keep maintenance records ready for the next visit.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="container py-5 py-lg-6" id="about">
        <div class="row g-4">
            <div class="col-md-4">
                <article class="feature-panel h-100"><i class="bi bi-geo-alt feature-icon" aria-hidden="true"></i>
                    <h2 class="h4">Local by design</h2>
                    <p class="mb-0">Browse approved shops across Minglanilla with barangay and service filters.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="feature-panel h-100"><i class="bi bi-calendar2-check feature-icon"
                        aria-hidden="true"></i>
                    <h2 class="h4">Book with confidence</h2>
                    <p class="mb-0">Request appointments without competing for an already-active time slot.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="feature-panel h-100"><i class="bi bi-clipboard2-pulse feature-icon"
                        aria-hidden="true"></i>
                    <h2 class="h4">Stay service-ready</h2>
                    <p class="mb-0">Review repair history, invoices, and maintenance reminders whenever you need them.
                    </p>
                </article>
            </div>
        </div>
    </section>
    <section class="container pb-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <h2 class="h2">Your next service starts here.</h2>
                <p class="text-secondary">Explore the map to find approved shops. Share your location only when you want
                    nearby results.</p>
                <a class="btn btn-primary" href="{{ route('map.index') }}"><i class="bi bi-geo-alt me-2"
                        aria-hidden="true"></i>Explore the map</a>
            </div>
            <div class="col-lg-6 offset-lg-1">
                <h2 class="h4 mb-3">Before you book</h2>
                <details class="border-bottom py-3">
                    <summary class="fw-semibold">Do I need an account?</summary>
                    <p class="text-secondary mt-3 mb-0">You can browse shops and the map without signing in. Create a
                        customer account to add a vehicle and request an appointment.</p>
                </details>
                <details class="border-bottom py-3">
                    <summary class="fw-semibold">Is an appointment request confirmed immediately?</summary>
                    <p class="text-secondary mt-3 mb-0">A request needs the shop's confirmation. Check your appointment
                        status before visiting.</p>
                </details>
                <details class="border-bottom py-3">
                    <summary class="fw-semibold">Why is my location unavailable?</summary>
                    <p class="text-secondary mt-3 mb-0">Your browser needs location permission and a secure connection.
                        You can still browse the map using barangay and service filters.</p>
                </details>
            </div>
        </div>
    </section>
</x-layouts.public>