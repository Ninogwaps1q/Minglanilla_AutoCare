<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('extensions.share.title') }}</title>
    <x-theme-init />
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="container py-4 py-lg-5">
        <div class="page-heading">
            <h1>{{ __('extensions.share.title') }}</h1>
            <p>{{ __('extensions.share.current', ['status' => __('extensions.share.status.'.$job->status)]) }}</p>
        </div>
        <ol class="list-group mb-4">
            @forelse ($events as $event)
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                    <strong>{{ __('extensions.share.status.'.$event->to_status) }}</strong>
                    <time datetime="{{ $event->changed_at->toIso8601String() }}">{{ $event->changed_at->timezone('Asia/Manila')->format('M j, Y, g:i A') }} PHT</time>
                </li>
            @empty
                <li class="list-group-item">{{ __('extensions.share.empty_events') }}</li>
            @endforelse
        </ol>
        @if ($photos->isNotEmpty())
            <section class="surface p-3 p-md-4">
                <h2 class="h5">{{ __('extensions.share.photos') }}</h2>
                <div class="row g-3">
                    @foreach ($photos as $photo)
                        @php($photoUrl = route('repairs.shared.photo', [$token, $photo->id]))
                        @php($photoLabel = __('extensions.share.stage.'.$photo->stage))
                        <figure class="col-6 col-md-4 mb-0">
                            <img src="{{ $photoUrl }}" loading="lazy" width="300" height="200" class="img-fluid rounded" alt="{{ $photoLabel }}">
                            <figcaption class="small text-secondary">{{ $photoLabel }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</body>
</html>
