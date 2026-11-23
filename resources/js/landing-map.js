import { loadGoogleMaps } from './google-maps';

const mapElement = document.querySelector('#landing-shop-map');
const status = document.querySelector('#landing-map-status');

if (mapElement && status) {
    const loadMap = async () => {
        status.textContent = 'Loading approved shop locations?';
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
                const marker = new googleMaps.Marker({ map, position, title: shop.name });
                const content = document.createElement('div');
                const name = document.createElement('strong');
                name.textContent = shop.name;
                const link = document.createElement('a');
                link.href = mapElement.dataset.shopUrlTemplate.replace('__SHOP_ID__', encodeURIComponent(shop.id));
                link.textContent = 'View shop';
                link.className = 'd-block';
                content.append(name, link);
                marker.addListener('click', () => { infoWindow.setContent(content); infoWindow.open({ anchor: marker, map }); });
            });
            if (shops.length) {
                map.fitBounds(bounds, 30);
                googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 14) map.setZoom(14); });
                status.textContent = `${shops.length} ${shops.length === 1 ? 'approved shop' : 'approved shops'} on the map.`;
            } else {
                status.textContent = 'No approved shops have a saved map location yet. Browse the directory.';
            }
        } catch {
            mapElement.innerHTML = '<p class="landing-map-message">Map unavailable. Browse approved shops in the directory.</p>';
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
