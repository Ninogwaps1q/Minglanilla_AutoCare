import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';
import { createShopMapPopup, openShopMapPopup } from './shop-map-popup';
import { showProviderMapFallback } from './provider-map-fallback';

const mapElement = document.querySelector('#landing-shop-map');
const status = document.querySelector('#landing-map-status');

if (mapElement && status) {
    let shops = [];
    const showFallback = () => {
        const shown = showProviderMapFallback(mapElement, shops, shop => mapElement.dataset.shopUrlTemplate.replace('__SHOP_ID__', encodeURIComponent(shop.id)));
        if (shown) status.textContent = 'Showing provider locations in an OpenStreetMap preview while Google Maps is unavailable.';

        return shown;
    };
    const showAuthenticationFailure = () => {
        if (showFallback()) return;
        mapElement.innerHTML = '<p class="landing-map-message">Google Maps is unavailable. Browse approved providers in the directory.</p>';
        status.textContent = 'Google Maps could not load. Ask the administrator to check the Maps API key, billing, and daily quota.';
    };
    onGoogleMapsAuthenticationFailure(showAuthenticationFailure);
    const loadMap = async () => {
        status.textContent = 'Loading approved provider locations…';
        try {
            const response = await fetch(mapElement.dataset.shopsUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Shop locations unavailable');
            const payload = await response.json();
            shops = Array.isArray(payload.data) ? payload.data.filter(shop => Number.isFinite(Number(shop.latitude)) && Number.isFinite(Number(shop.longitude))) : [];
            const googleMaps = await loadGoogleMaps(mapElement.dataset.googleKey);
            mapElement.textContent = '';
            const map = new googleMaps.Map(mapElement, { center: { lat: 12.8, lng: 121.8 }, zoom: 6, mapTypeControl: false });
            const bounds = new googleMaps.LatLngBounds();
            const infoWindow = new googleMaps.InfoWindow();
            shops.forEach(shop => {
                const position = { lat: Number(shop.latitude), lng: Number(shop.longitude) };
                bounds.extend(position);
                const marker = new googleMaps.Marker({
                    map,
                    position,
                    title: shop.provider_type === 'independent_mechanic' ? `${shop.name} approximate service area` : shop.name,
                    icon: shop.provider_type === 'independent_mechanic' ? 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png' : undefined,
                });
                const content = createShopMapPopup(shop, mapElement.dataset.shopUrlTemplate.replace('__SHOP_ID__', encodeURIComponent(shop.id)), shop.provider_type === 'independent_mechanic' ? 'View mechanic' : 'View shop');
                marker.addListener('click', () => openShopMapPopup(googleMaps, infoWindow, map, mapElement, marker, content));
            });
            if (shops.length) {
                map.fitBounds(bounds, { top: Math.min(150, Math.round(mapElement.clientHeight * .38)), right: 30, bottom: 30, left: 30 });
                googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 14) map.setZoom(14); });
                status.textContent = `${shops.length} ${shops.length === 1 ? 'approved provider' : 'approved providers'} on the map.`;
            } else {
                status.textContent = 'No approved providers have a saved map location yet. Browse the directory.';
            }
        } catch (error) {
            if (error.message === 'Google Maps authentication failed.') {
                showAuthenticationFailure();
                return;
            }
            if (showFallback()) return;
            mapElement.innerHTML = '<p class="landing-map-message">Map unavailable. Browse approved providers in the directory.</p>';
            status.textContent = 'Map unavailable. Browse the directory instead.';
        }
    };
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) { observer.disconnect(); loadMap(); }
        }, { rootMargin: '250px' });
        observer.observe(mapElement);
    } else {
        loadMap();
    }
}
