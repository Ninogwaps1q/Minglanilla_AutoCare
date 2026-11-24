@props([
    'latitude',
    'longitude',
    'title' => 'Driving directions',
    'buttonLabel' => 'Get directions',
    'destinationLabel' => 'Destination',
    'destinationType' => 'shop',
])

<section {{ $attributes->merge(['class' => 'driving-directions']) }} data-google-key="{{ config('services.google.maps_api_key') }}" data-latitude="{{ $latitude }}" data-longitude="{{ $longitude }}" data-destination-type="{{ $destinationType }}">
    <h3 class="h6 mb-2">{{ $title }}</h3>
    <div class="driving-directions-map" role="region" aria-label="Map showing {{ $destinationLabel }}">Loading Google Maps…</div>
    <button class="btn btn-outline-primary mt-3 driving-directions-button" type="button"><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>{{ $buttonLabel }}</button>
    <p class="small text-secondary mt-2 mb-0 driving-directions-status" role="status" aria-live="polite">Share your current location to see the driving route and directions here.</p>
    <p class="small fw-semibold mt-3 mb-2 driving-directions-summary" hidden></p>
    <div class="driving-directions-steps mt-3" hidden>
        <h4 class="h6">Turn-by-turn directions</h4>
        <ol class="list-group list-group-numbered driving-directions-steps-list"></ol>
    </div>
</section>
