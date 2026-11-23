import { loadGoogleMaps } from './google-maps';

const mapElement = document.querySelector('#shop-map');
const shops = JSON.parse(mapElement.dataset.shops || '[]');
const locationInput = document.querySelector('#latitude');
const longitudeInput = document.querySelector('#longitude');
const selectedShopId = Number(mapElement.dataset.selectedShopId || 0);
const message = text => { mapElement.textContent = text; };

if (!shops.length) {
    message('No matching providers have a map location yet. Browse the results below.');
} else {
    loadGoogleMaps(mapElement.dataset.googleKey).then(googleMaps => {
        mapElement.textContent = '';
        mapElement.classList.remove('d-flex', 'align-items-center', 'justify-content-center', 'p-4', 'text-secondary');
        const map = new googleMaps.Map(mapElement, { center: { lat: 12.8, lng: 121.8 }, zoom: 6, mapTypeControl: false });
        const bounds = new googleMaps.LatLngBounds();
        let selectedMarker;
        const infoWindow = new googleMaps.InfoWindow();

        shops.forEach(shop => {
            const position = { lat: Number(shop.latitude), lng: Number(shop.longitude) };
            const marker = new googleMaps.Marker({ map, position, title: shop.name });
            bounds.extend(position);
            const content = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = shop.name;
            const link = document.createElement('a');
            link.href = shop.url;
            link.textContent = 'View provider';
            link.className = 'd-block';
            content.append(name, link);
            marker.addListener('click', () => { infoWindow.setContent(content); infoWindow.open({ anchor: marker, map }); });
            if (Number(shop.id) === selectedShopId) selectedMarker = marker;
        });

        if (locationInput.value && longitudeInput.value) {
            const position = { lat: Number(locationInput.value), lng: Number(longitudeInput.value) };
            new googleMaps.Marker({ map, position, title: 'Your approximate location', icon: 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png' });
            bounds.extend(position);
        }
        map.fitBounds(bounds, 30);
        googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 15) map.setZoom(15); });
        if (selectedMarker) googleMaps.event.trigger(selectedMarker, 'click');
    }).catch(error => message(error.message === 'Google Maps API key is not configured.' ? 'Map unavailable until the administrator configures Google Maps. Browse providers below.' : 'The map could not load. Browse providers below.'));
}

const nearbyButton = document.querySelector('#nearby-button');
const locationStatus = document.querySelector('#location-status');
nearbyButton.addEventListener('click', () => {
    if (!navigator.geolocation) {
        locationStatus.textContent = 'Location is not supported by this browser. You can still browse all providers.';
        return;
    }
    nearbyButton.disabled = true;
    locationStatus.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(position => {
        locationInput.value = position.coords.latitude;
        longitudeInput.value = position.coords.longitude;
        document.querySelector('#shop-sort').querySelector('[value="distance"]').disabled = false;
        document.querySelector('#shop-sort').value = 'distance';
        document.querySelector('#shop-filters').requestSubmit();
    }, () => {
        nearbyButton.disabled = false;
        locationStatus.textContent = 'We could not access your location. You can still browse all providers.';
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
});
