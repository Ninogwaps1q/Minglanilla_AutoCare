import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';

const element = document.querySelector('#location-picker-map');
const status = document.querySelector('#location-picker-status');
const latitude = document.querySelector('#latitude');
const longitude = document.querySelector('#longitude');
const deferred = element.hasAttribute('data-defer-map');
const copy = deferred ? JSON.parse(element.dataset.copy) : {
    pin: 'Location pin saved. Check the coordinates below before submitting.',
    lookup: 'Finding the selected area on the map…',
    centered: 'Map centered on the selected area. Click the map to place or move your pin.',
    unknown: 'The selected area could not be located. Move the map or enter coordinates below.',
    unavailable: 'Map unavailable. Enter the latitude and longitude below.',
    bounds: 'Enter coordinates in the Philippines to save the location.',
};

let activeLookup = 0;

function initialize() {
    const centerButton = element.parentElement.querySelector('[data-pick-map-center]');
    onGoogleMapsAuthenticationFailure(() => {
        activeLookup++;
        element.classList.add('is-unavailable');
        if (centerButton) centerButton.hidden = true;
        element.textContent = copy.unavailable;
        status.textContent = copy.bounds;
    });

    loadGoogleMaps(element.dataset.googleKey).then(googleMaps => {
        const saved = latitude.value && longitude.value ? { lat: Number(latitude.value), lng: Number(longitude.value) } : null;
        const map = new googleMaps.Map(element, { center: saved ?? { lat: 12.8, lng: 120.8 }, zoom: saved ? 16 : 5 });
        let marker = saved ? new googleMaps.Marker({ map, position: saved, title: 'Selected location' }) : null;
        const pin = position => {
            activeLookup++;
            latitude.value = position.lat().toFixed(7);
            longitude.value = position.lng().toFixed(7);
            if (marker) marker.setPosition(position);
            else marker = new googleMaps.Marker({ map, position, title: 'Selected location' });
            if (deferred) longitude.dispatchEvent(new Event('input', { bubbles: true }));
            status.textContent = copy.pin;
        };
        map.addListener('click', event => pin(event.latLng));
        if (centerButton) {
            centerButton.hidden = false;
            centerButton.addEventListener('click', () => pin(map.getCenter()));
        }
        const form = latitude.form;
        const picker = form?.querySelector('[data-address-picker]');
        let geocoder;
        const centerOnArea = async (includeBarangay = true) => {
            const lookup = ++activeLookup;
            const selectedName = selector => {
                const select = picker?.querySelector(selector);
                return select?.value ? select.selectedOptions[0]?.textContent : '';
            };
            const city = selectedName('[data-address-municipality]');
            if (!city) return;

            const province = selectedName('[data-address-province]');
            const region = selectedName('[data-address-region]');
            const barangay = includeBarangay ? selectedName('[data-address-barangay]') : '';
            const cityAddress = [city, province.endsWith(' cities and municipalities') ? '' : province, region, 'Philippines'].filter(Boolean).join(', ');

            status.textContent = copy.lookup;
            try {
                if (!geocoder) {
                    const { Geocoder } = await googleMaps.importLibrary('geocoding');
                    geocoder = new Geocoder();
                }
                const locate = address => geocoder.geocode({ address, componentRestrictions: { country: 'PH' } }).then(response => response.results[0]);
                let result = barangay ? await locate(`${barangay}, ${cityAddress}`).catch(() => null) : null;
                if (!result || result.partial_match) result = await locate(cityAddress);
                if (lookup !== activeLookup) return;
                if (!result?.geometry?.location) throw new Error('Area unavailable');

                if (result.geometry.viewport) map.fitBounds(result.geometry.viewport);
                else {
                    map.panTo(result.geometry.location);
                    map.setZoom(13);
                }
                status.textContent = copy.centered;
            } catch {
                if (lookup === activeLookup) status.textContent = copy.unknown;
            }
        };
        picker?.addEventListener('change', event => {
            if (event.target.matches('[data-address-choice]')) {
                if (event.target.matches('[data-address-region], [data-address-province]')) activeLookup++;
                else centerOnArea(!event.target.matches('[data-address-municipality]'));
            }
        });
        if (!saved) {
            picker?.addEventListener('address:ready', () => centerOnArea(), { once: true });
            if (picker?.dataset.ready === 'true') centerOnArea();
        }
        [latitude, longitude].forEach(input => input.addEventListener('change', () => {
            const lat = Number(latitude.value);
            const lng = Number(longitude.value);
            if (latitude.value && longitude.value && latitude.validity.valid && longitude.validity.valid) {
                const position = new googleMaps.LatLng(lat, lng);
                pin(position);
                map.panTo(position);
            }
        }));
    }).catch(error => {
        if (error.message === 'Google Maps authentication failed.') return;
        element.classList.add('is-unavailable');
        element.textContent = copy.unavailable;
        status.textContent = copy.bounds;
    });
}

const disclosure = deferred ? element.closest('details') : null;
if (disclosure && !disclosure.open) {
    disclosure.addEventListener('toggle', function onOpen() {
        if (!disclosure.open) return;
        disclosure.removeEventListener('toggle', onOpen);
        initialize();
    });
} else {
    initialize();
}
