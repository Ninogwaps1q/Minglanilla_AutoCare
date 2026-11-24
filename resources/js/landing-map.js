import { loadGoogleMaps } from './google-maps';
import { createShopMapPopup } from './shop-map-popup';

const mapElement = document.querySelector('#landing-shop-map');
const status = document.querySelector('#landing-map-status');

if (mapElement && status) {
    const loadMap = async () => {
        status.textContent = 'Loading approved provider locations…';
        try {
            const response = await fetch(mapElement.dataset.shopsUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Shop locations unavailable');
            const payload = await response.json();
            const shops = Array.isArray(payload.data) ? payload.data.filter(shop => Number.isFinite(Number(shop.latitude)) && Number.isFinite(Number(shop.longitude))) : [];
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
                marker.addListener('click', () => { infoWindow.setContent(content); infoWindow.open({ anchor: marker, map }); });
            });
            if (shops.length) {
                map.fitBounds(bounds, { top: Math.min(150, Math.round(mapElement.clientHeight * .38)), right: 30, bottom: 30, left: 30 });
                googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 14) map.setZoom(14); });
                status.textContent = `${shops.length} ${shops.length === 1 ? 'approved provider' : 'approved providers'} on the map.`;
            } else {
                status.textContent = 'No approved providers have a saved map location yet. Browse the directory.';
            }
        } catch {
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
