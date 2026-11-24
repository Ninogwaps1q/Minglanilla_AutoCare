import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';
import { createShopMapPopup, openShopMapPopup } from './shop-map-popup';
import { showProviderMapFallback } from './provider-map-fallback';

const mapElement = document.querySelector('#shop-map');
const shops = JSON.parse(mapElement.dataset.shops || '[]');
const locationInput = document.querySelector('#latitude');
const longitudeInput = document.querySelector('#longitude');
const selectedShopId = Number(mapElement.dataset.selectedShopId || 0);
const message = text => { mapElement.textContent = text; };
const authenticationFailureMessage = 'Google Maps could not load. Ask the administrator to check the Maps API key, billing, and daily quota. Browse providers below.';
const showFallback = () => showProviderMapFallback(mapElement, shops, shop => shop.url, selectedShopId);
onGoogleMapsAuthenticationFailure(() => { if (!showFallback()) message(authenticationFailureMessage); });

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
            const marker = new googleMaps.Marker({
                map,
                position,
                title: shop.provider_type === 'independent_mechanic' ? `${shop.name} approximate service area` : shop.name,
                icon: shop.provider_type === 'independent_mechanic' ? 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png' : undefined,
            });
            bounds.extend(position);
            const content = createShopMapPopup(shop, shop.url, shop.provider_type === 'independent_mechanic' ? 'View mechanic' : 'View shop');
            marker.addListener('click', () => openShopMapPopup(googleMaps, infoWindow, map, mapElement, marker, content));
            if (Number(shop.id) === selectedShopId) selectedMarker = marker;
        });

        if (locationInput.value && longitudeInput.value) {
            const position = { lat: Number(locationInput.value), lng: Number(longitudeInput.value) };
            new googleMaps.Marker({ map, position, title: 'Your approximate location', icon: 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png' });
            bounds.extend(position);
        }
        map.fitBounds(bounds, { top: Math.min(150, Math.round(mapElement.clientHeight * .38)), right: 30, bottom: 30, left: 30 });
        googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 15) map.setZoom(15); });
        if (selectedMarker) googleMaps.event.trigger(selectedMarker, 'click');
    }).catch(error => {
        if (showFallback()) return;
        message(error.message === 'Google Maps authentication failed.' ? authenticationFailureMessage : (error.message === 'Google Maps API key is not configured.' ? 'Map unavailable until the administrator configures Google Maps. Browse providers below.' : 'The map could not load. Browse providers below.'));
    });
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
