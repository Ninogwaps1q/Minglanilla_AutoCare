@props(['path' => null, 'alt' => '', 'fallbackIcon' => 'image'])

@if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path))
    <img src="{{ url('media/'.ltrim($path, '/')) }}" alt="{{ $alt }}" loading="lazy" decoding="async" {{ $attributes }}>
@else
    <div role="img" aria-label="{{ $alt }}" {{ $attributes->class(['uploaded-image-fallback']) }}>
        <i class="bi bi-{{ $fallbackIcon }}" aria-hidden="true"></i>
    </div>
@endif
