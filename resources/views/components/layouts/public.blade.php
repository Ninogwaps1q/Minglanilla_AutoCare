@props(['title' => null])

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Minglanilla AutoCare Hub') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="public-shell d-flex flex-column">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg public-navbar" aria-label="Primary navigation">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('home') }}">
                <img class="brand-logo" src="{{ asset('images/Minglanilla AutoCare Hub Logo.png') }}" alt="" width="44" height="44">
                <span>Minglanilla AutoCare Hub</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div id="publicNavigation" class="collapse navbar-collapse">
                <ul class="navbar-nav ms-lg-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('shops.index') }}">Shop directory</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('map.index') }}">Find nearby</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about">About</a></li>
                    @auth
                        <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="{{ route(auth()->user()->role === 'shop_owner' ? 'shop.dashboard' : (auth()->user()->role === 'admin' ? 'admin.dashboard' : 'dashboard')) }}">Open dashboard</a></li>
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
    <footer class="public-footer mt-auto"><div class="container d-flex flex-column flex-md-row justify-content-between gap-2 py-4"><span>© {{ now()->year }} Minglanilla AutoCare Hub</span><span>Vehicle service discovery for Minglanilla, Cebu.</span></div></footer>
    @stack('scripts')
</body>

</html>
