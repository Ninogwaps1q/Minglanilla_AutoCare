@props(['code', 'title', 'message'])

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="error-page">
    <main class="container py-4 py-lg-5">
        <section class="error-panel text-center mx-auto" aria-labelledby="error-title">
            <p class="error-code">{{ $code }}</p>
            <h1 id="error-title">{{ $title }}</h1>
            <p class="error-message mb-4">{{ $message }}</p>
            <a class="btn btn-primary px-4" href="{{ route('home') }}">Return home</a>
        </section>
    </main>
</body>

</html>
