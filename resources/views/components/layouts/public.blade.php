@props(['title' => null])
@php($onLandingPage = request()->routeIs('home'))

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-init />
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Minglanilla AutoCare Hub') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="public-shell d-flex flex-column{{ $onLandingPage ? ' has-fixed-public-nav' : '' }}">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg public-navbar{{ $onLandingPage ? ' public-navbar-fixed' : '' }}" aria-label="Primary navigation">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('home') }}">
                <img class="brand-logo" src="{{ asset('images/Minglanilla AutoCare Hub Logo.png') }}" alt="" width="44" height="44">
                <span>Minglanilla AutoCare Hub</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div id="publicNavigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-lg-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item public-nav-home d-flex align-items-center"><x-theme-toggle /><a class="nav-link" href="{{ $onLandingPage ? '#home' : route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $onLandingPage ? '#find-shops' : route('shops.index') }}">Find a shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ $onLandingPage ? '#about' : route('home').'#about' }}">About</a></li>
                    @auth
                        <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="{{ auth()->user()->hasVerifiedEmail() ? route(auth()->user()->role === 'shop_owner' ? 'shop.dashboard' : (auth()->user()->role === 'admin' ? 'admin.dashboard' : 'dashboard')) : route('verification.notice') }}">{{ auth()->user()->hasVerifiedEmail() ? 'Open dashboard' : 'Verify email' }}</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Sign in</a></li>
                        <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="{{ route('register') }}">Create account</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>
    <main class="flex-grow-1" id="main-content" tabindex="-1">
        @if (session('status'))
            <div class="container pt-3"><div class="alert alert-success" role="status">{{ session('status') }}</div></div>
        @endif
        {{ $slot }}
    </main>
    <footer class="public-footer mt-auto" aria-label="Site footer">
        <div class="container public-footer-grid">
            <div class="public-footer-brand">
                <img class="brand-logo" src="{{ asset('images/Minglanilla AutoCare Hub Logo.png') }}" alt="" width="48" height="48">
                <div><strong>Minglanilla AutoCare Hub</strong><span>Trusted local vehicle care, made easier.</span></div>
            </div>
            <nav class="public-footer-links" aria-label="Footer navigation">
                <strong>Explore</strong>
                <a href="{{ route('shops.index') }}">Shop directory &amp; map</a>
                <a href="{{ route('home') }}#about">About the platform</a>
            </nav>
            <div class="public-footer-action">
                <strong>Ready to get started?</strong>
                <span>Manage bookings, repairs, and vehicle records in one place.</span>
                @auth
                    <a class="btn btn-accent" href="{{ auth()->user()->hasVerifiedEmail() ? route(auth()->user()->role === 'shop_owner' ? 'shop.dashboard' : (auth()->user()->role === 'admin' ? 'admin.dashboard' : 'dashboard')) : route('verification.notice') }}">{{ auth()->user()->hasVerifiedEmail() ? 'Open dashboard' : 'Verify email' }}</a>
                @else
                    <a class="btn btn-accent" href="{{ route('register') }}">Create an account</a>
                @endauth
            </div>
        </div>
        <div class="container public-footer-bottom"><span>&copy; {{ now()->year }} Minglanilla AutoCare Hub</span><span>Serving drivers and service shops in Minglanilla, Cebu.</span></div>
    </footer>
    @stack('scripts')
</body>

</html>
