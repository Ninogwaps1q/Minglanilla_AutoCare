@props(['code', 'title', 'message'])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-init />
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="error-page">
    <main class="container py-4 py-lg-5">
        <section class="error-panel mx-auto" aria-labelledby="error-title">
            <p class="error-code">{{ $code }}</p>
            <h1 id="error-title">{{ $title }}</h1>
            <p class="error-message mb-4">{{ $message }}</p>
            @if ((int) $code === 419 && ! auth()->check())
                <a class="btn btn-primary" href="{{ route('login') }}">{{ __('account_ui.sign_in_again') }}</a>
                <a class="btn btn-link" href="{{ route('home') }}">{{ __('account_ui.return_home') }}</a>
            @else
                <a class="btn btn-primary" href="{{ route('home') }}">{{ __('account_ui.return_home') }}</a>
            @endif
        </section>
    </main>
</body>

</html>
