import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';
import { createShopMapPopup, openShopMapPopup } from './shop-map-popup';
import { showProviderMapFallback } from './provider-map-fallback';

const mapElement = document.querySelector('#shop-map');
const shops = JSON.parse(mapElement.dataset.shops || '[]');
const copy = JSON.parse(mapElement.dataset.copy);
const rows = document.querySelector('.directory-rows');
const directory = document.querySelector('.directory-split');
const desktop = window.matchMedia('(min-width: 992px)');
let selectedShopId = Number(mapElement.dataset.selectedShopId || 0);
let selectMarker;
let started = false;

function selectProvider(id, scroll = false) {
    selectedShopId = id;
    rows.querySelectorAll('[data-provider-row]').forEach(row => {
        const selected = Number(row.dataset.providerRow) === id;
        row.classList.toggle('is-selected', selected);
        const link = row.querySelector('[data-select-provider]');
        if (selected) {
            link?.setAttribute('aria-current', 'location');
            if (scroll && rows.clientHeight) {
                const top = row.getBoundingClientRect().top - rows.getBoundingClientRect().top;
                if (top < 0 || top + row.clientHeight > rows.clientHeight) rows.scrollTop += top;
            }
        } else {
            link?.removeAttribute('aria-current');
        }
    });
}

function fallback() {
    if (!showProviderMapFallback(mapElement, shops, shop => shop.url, selectedShopId)) {
        mapElement.textContent = copy.map_failure;
        return;
    }
    const select = mapElement.querySelector('select');
    select.onchange = () => selectProvider(Number(select.value), true);
    selectMarker = id => {
        select.value = String(id);
        select.dispatchEvent(new Event('change'));
    };
}

function loadDirectoryMap() {
    if (started || (!desktop.matches && directory.dataset.directoryMode !== 'map')) return;
    started = true;

    if (!shops.length) {
        mapElement.textContent = copy.map_empty;
        return;
    }

    onGoogleMapsAuthenticationFailure(fallback);
    loadGoogleMaps(mapElement.dataset.googleKey).then(googleMaps => {
        if (mapElement.classList.contains('map-fallback-active')) return;
        mapElement.textContent = '';
        mapElement.classList.remove('d-flex', 'align-items-center', 'justify-content-center', 'p-4', 'text-secondary');
        const map = new googleMaps.Map(mapElement, { center: { lat: 12.8, lng: 121.8 }, zoom: 6, mapTypeControl: false });
        const bounds = new googleMaps.LatLngBounds();
        const infoWindow = new googleMaps.InfoWindow();
        const pins = new Map();

        shops.forEach(shop => {
            const position = { lat: Number(shop.latitude), lng: Number(shop.longitude) };
            const label = shop.price ? `${shop.name} · ${shop.price}` : shop.name;
            const marker = new googleMaps.Marker({
                map,
                position,
                title: shop.provider_type === 'independent_mechanic' ? `${label} · ${copy.approx_area}` : label,
                label: { text: label, className: 'directory-pin-label' },
            });
            pins.set(Number(shop.id), { marker, label });
            bounds.extend(position);
            marker.addListener('click', () => {
                selectProvider(Number(shop.id), true);
                pins.forEach((pin, id) => {
                    pin.marker.setZIndex?.(id === selectedShopId ? 1000 : undefined);
                    pin.marker.setLabel?.({ text: pin.label, className: id === selectedShopId ? 'directory-pin-label is-selected' : 'directory-pin-label' });
                });
                openShopMapPopup(googleMaps, infoWindow, map, mapElement, marker, createShopMapPopup(shop, shop.url, shop.provider_type === 'independent_mechanic' ? copy.view_mechanic : copy.view_shop, copy));
            });
        });

        const latitude = document.querySelector('#latitude').value;
        const longitude = document.querySelector('#longitude').value;
        if (latitude && longitude) {
            const position = { lat: Number(latitude), lng: Number(longitude) };
            new googleMaps.Marker({ map, position, title: copy.my_location, icon: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' });
            bounds.extend(position);
        }

        map.fitBounds(bounds, { top: Math.min(150, Math.round(mapElement.clientHeight * .38)), right: 45, bottom: 35, left: 45 });
        googleMaps.event.addListenerOnce(map, 'bounds_changed', () => { if (map.getZoom() > 15) map.setZoom(15); });
        selectMarker = id => {
            const selectedMarker = pins.get(id)?.marker;
            if (selectedMarker) googleMaps.event.trigger(selectedMarker, 'click');
        };
        if (selectedShopId) selectMarker(selectedShopId);
    }).catch(fallback);
}

document.querySelectorAll('[data-select-provider]').forEach(link => {
    link.addEventListener('click', event => {
        if (!desktop.matches || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const id = Number(link.dataset.selectProvider);
        selectProvider(id);
        selectMarker?.(id);
    });
});
document.querySelectorAll('[data-directory-logo]').forEach(image => {
    image.addEventListener('error', () => image.remove(), { once: true });
    if (image.complete && !image.naturalWidth) image.remove();
});
document.querySelectorAll('.directory-chip input').forEach(input => {
    input.addEventListener('change', () => input.form.requestSubmit());
});
desktop.addEventListener('change', loadDirectoryMap);
if (location.hash === '#shop-map' && directory.dataset.directoryMode === 'list') {
    directory.dataset.directoryMode = 'map';
    document.querySelector('#shop-filters [name="view"]').value = 'map';
    document.querySelectorAll('[data-directory-view]').forEach(link => {
        const active = link.dataset.directoryView === 'map';
        link.classList.toggle('btn-primary', active);
        link.classList.toggle('btn-outline-primary', !active);
        if (active) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
    });
}
loadDirectoryMap();
