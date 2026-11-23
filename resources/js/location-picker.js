import { loadGoogleMaps } from './google-maps';

const element = document.querySelector('#location-picker-map');
const status = document.querySelector('#location-picker-status');
const latitude = document.querySelector('#latitude');
const longitude = document.querySelector('#longitude');

loadGoogleMaps(element.dataset.googleKey).then(googleMaps => {
    const saved = latitude.value && longitude.value ? { lat: Number(latitude.value), lng: Number(longitude.value) } : null;
    const map = new googleMaps.Map(element, { center: saved ?? { lat: 12.8, lng: 121.8 }, zoom: saved ? 16 : 6 });
    let marker = saved ? new googleMaps.Marker({ map, position: saved, title: 'Selected location' }) : null;
    const pin = position => {
        latitude.value = position.lat().toFixed(7);
        longitude.value = position.lng().toFixed(7);
        if (marker) marker.setPosition(position);
        else marker = new googleMaps.Marker({ map, position, title: 'Selected location' });
        status.textContent = 'Location pin saved. Check the coordinates below before submitting.';
    };
    map.addListener('click', event => pin(event.latLng));
    [latitude, longitude].forEach(input => input.addEventListener('change', () => {
        const lat = Number(latitude.value);
        const lng = Number(longitude.value);
        if (lat >= 4 && lat <= 22 && lng >= 116 && lng <= 127) {
            const position = new googleMaps.LatLng(lat, lng);
            pin(position);
            map.panTo(position);
        }
    }));
}).catch(() => {
    element.textContent = 'Map unavailable. Enter the latitude and longitude below.';
    status.textContent = 'Enter coordinates in the Philippines to save the location.';
});
