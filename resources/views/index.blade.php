<x-layouts.public>
    <section class="hero-section" id="home" aria-labelledby="landing-title">
        <div class="container py-5 py-lg-6">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <h1 id="landing-title">Find Trusted Auto Repair Services in Minglanilla.</h1>
                    <p class="hero-copy">Discover local automotive service providers, compare services, book
                        appointments, and manage your vehicle maintenance in one convenient platform.</p>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <a class="btn btn-accent btn-lg" href="#find-shops">Find a shop <i
                                class="bi bi-arrow-down ms-2" aria-hidden="true"></i></a>
                        <a class="link-light fw-semibold" href="#about">How it works</a>
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
    <section class="landing-find-section py-5 py-lg-6" id="find-shops" aria-labelledby="find-shops-title">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 id="find-shops-title">Find a shop that fits your needs</h2>
                    <p class="text-secondary landing-section-intro">Search approved local shops by name, or explore the
                        directory to compare services and locations.</p>
                    <form action="{{ route('shops.index') }}" method="GET" class="landing-shop-search">
                        <label for="home-search" class="form-label fw-semibold">Shop name</label>
                        <div class="input-group"><input id="home-search" class="form-control" name="search"
                                type="search" maxlength="100" placeholder="Search by shop name"><button
                                class="btn btn-primary" type="submit">Search shops</button></div>
                    </form>
                </div>
                <div class="col-lg-4 offset-lg-1">
                    <h3 class="h5">Prefer to browse nearby?</h3>
                    <p class="text-secondary">See approved shops on a map. Share your location only if you want nearby
                        results.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-outline-primary" href="{{ route('shops.index') }}#shop-map">Explore the map</a>
                        <a class="btn btn-link" href="{{ route('shops.index') }}">Browse all shops <i
                                class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="container py-5 py-lg-6" id="about" aria-labelledby="about-title">
        <h2 id="about-title" class="mb-4">Vehicle care, made easier</h2>
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
    <section class="container pb-5" aria-labelledby="before-booking-title">
        <div class="row">
            <div class="col-lg-8">
                <h2 id="before-booking-title" class="h4 mb-3">Before you book</h2>
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
