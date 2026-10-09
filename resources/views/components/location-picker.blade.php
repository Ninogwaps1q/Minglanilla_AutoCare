@props(['latitude' => null, 'longitude' => null])
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="col-12"><label class="form-label">Shop location</label>
    <p class="small text-secondary">Click the map to place your shop pin in Minglanilla, Cebu.</p>
    <div id="location-picker-map" class="surface" style="height:360px"></div>
    <div class="row g-3 mt-1">
        <div class="col-md-6"><label class="form-label">Latitude</label><input id="latitude" class="form-control"
                name="latitude" value="{{ old('latitude', $latitude) }}" readonly required></div>
        <div class="col-md-6"><label class="form-label">Longitude</label><input id="longitude" class="form-control"
                name="longitude" value="{{ old('longitude', $longitude) }}" readonly required></div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (() => {
        const lat = document.querySelector('#latitude'),
            lng = document.querySelector('#longitude'),
            saved = lat.value && lng.value ? [Number(lat.value), Number(lng.value)] : [10.2443, 123.7898],
            map = L.map('location-picker-map').setView(saved, lat.value ? 16 : 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        let marker = lat.value ? L.marker(saved).addTo(map) : null;
        map.on('click', event => {
            lat.value = event.latlng.lat.toFixed(7);
            lng.value = event.latlng.lng.toFixed(7);
            marker ? marker.setLatLng(event.latlng) : marker = L.marker(event.latlng).addTo(map);
        });
    })();
</script>