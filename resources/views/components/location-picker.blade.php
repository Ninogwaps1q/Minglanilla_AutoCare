@props(['latitude' => null, 'longitude' => null, 'label' => 'Shop location', 'hint' => 'Click the map to place your exact shop pin.', 'deferMap' => false])
<div class="col-12"><p class="form-label">{{ $label }}</p>
    <p class="small text-secondary">{{ $hint }}</p>
    <div id="location-picker-map" class="surface location-picker-map" role="region" aria-label="{{ $label }}" data-google-key="{{ config('services.google.maps_api_key') }}" @if ($deferMap) data-defer-map data-copy="{{ json_encode(__('roadside.map')) }}" @endif></div>
    <p id="location-picker-status" class="small text-secondary mt-2 mb-0" role="status" aria-live="polite">{{ $deferMap ? '' : 'Choose a point on the map, or enter coordinates below.' }}</p>
    @if ($deferMap)
        <button type="button" class="btn btn-outline-primary mt-2" data-pick-map-center hidden>{{ __('roadside.map_center') }}</button>
    @endif
    <div class="row g-3 mt-1">
        <div class="col-md-6"><label for="latitude" class="form-label">{{ $deferMap ? __('roadside.latitude') : 'Latitude' }}</label><input id="latitude" class="form-control" type="number" min="{{ $deferMap ? -90 : 4 }}" max="{{ $deferMap ? 90 : 22 }}" step="any"
                name="latitude" value="{{ old('latitude', $latitude) }}" @required(! $deferMap)></div>
        <div class="col-md-6"><label for="longitude" class="form-label">{{ $deferMap ? __('roadside.longitude') : 'Longitude' }}</label><input id="longitude" class="form-control" type="number" min="{{ $deferMap ? -180 : 112 }}" max="{{ $deferMap ? 180 : 128 }}" step="any"
                name="longitude" value="{{ old('longitude', $longitude) }}" @required(! $deferMap)></div>
    </div>
</div>
