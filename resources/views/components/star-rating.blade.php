@props(['rating'])

@php
    $score = max(0, min(5, (float) $rating));
    $wholeStars = (int) floor($score);
    $fraction = $score - $wholeStars;
@endphp

<span {{ $attributes->class(['rating-display']) }} role="img" aria-label="{{ number_format($score, 1) }} out of 5 stars">
    <span class="rating-stars" aria-hidden="true">
        @for ($star = 1; $star <= 5; $star++)
            <i class="bi bi-{{ $star <= $wholeStars || ($star === $wholeStars + 1 && $fraction >= .75) ? 'star-fill' : ($star === $wholeStars + 1 && $fraction >= .25 ? 'star-half' : 'star') }}"></i>
        @endfor
    </span>
    <strong class="rating-score" aria-hidden="true">{{ number_format($score, 1) }}</strong>
</span>
