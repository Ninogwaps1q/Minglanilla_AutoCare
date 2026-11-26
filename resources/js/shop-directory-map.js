import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';
import { createShopMapPopup, openShopMapPopup } from './shop-map-popup';
import { showProviderMapFallback } from './provider-map-fallback';

const mapElement = document.querySelector('#shop-map');
const shops = JSON.parse(mapElement.dataset.shops || '[]');
const locationInput = document.querySelector('#latitude');
const longitudeInput = document.querySelector('#longitude');
const selectedShopId = Number(mapElement.dataset.selectedShopId || 0);
const message = text => { mapElement.textContent = text; };
const authenticationFailureMessage = 'Google Maps could not load. Switch to List to browse matching providers.';
const showFallback = () => showProviderMapFallback(mapElement, shops, shop => shop.url, selectedShopId);
onGoogleMapsAuthenticationFailure(() => { if (!showFallback()) message(authenticationFailureMessage); });

if (!shops.length) {
    message('No matching providers have a map location yet. Switch to List to browse the results.');
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
        message(error.message === 'Google Maps authentication failed.' ? authenticationFailureMessage : 'The map could not load. Switch to List to browse matching providers.');
    });
}
