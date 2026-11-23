@props(['latitude' => null, 'longitude' => null, 'label' => 'Shop location', 'hint' => 'Click the map to place your exact shop pin.'])
<div class="col-12"><label class="form-label">{{ $label }}</label>
    <p class="small text-secondary">{{ $hint }}</p>
    <div id="location-picker-map" class="surface" style="height:360px" role="region" aria-label="Google map for choosing the location" data-google-key="{{ config('services.google.maps_api_key') }}"></div>
    <p id="location-picker-status" class="small text-secondary mt-2 mb-0" role="status" aria-live="polite">Choose a point on the map, or enter coordinates below.</p>
    <div class="row g-3 mt-1">
        <div class="col-md-6"><label for="latitude" class="form-label">Latitude</label><input id="latitude" class="form-control" type="number" min="4" max="22" step="any"
                name="latitude" value="{{ old('latitude', $latitude) }}" required></div>
        <div class="col-md-6"><label for="longitude" class="form-label">Longitude</label><input id="longitude" class="form-control" type="number" min="116" max="127" step="any"
                name="longitude" value="{{ old('longitude', $longitude) }}" required></div>
    </div>
</div>
