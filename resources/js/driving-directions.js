import { loadGoogleMaps, onGoogleMapsAuthenticationFailure } from './google-maps';

const getCurrentLocation = () => new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
    });
});

const locationErrorMessage = error => {
    if (error?.code === 1) return 'Location access was denied. Allow location for this site in your browser settings, then try again.';
    if (error?.code === 3) return 'Finding your location timed out. Move to an area with a stronger signal and try again.';

    return 'Your current location is unavailable. Check your device location settings and try again.';
};

document.querySelectorAll('.driving-directions').forEach(section => {
    const mapElement = section.querySelector('.driving-directions-map');
    const button = section.querySelector('.driving-directions-button');
    const status = section.querySelector('.driving-directions-status');
    const summary = section.querySelector('.driving-directions-summary');
    const stepsPanel = section.querySelector('.driving-directions-steps');
    const stepsList = section.querySelector('.driving-directions-steps-list');
    const destination = {
        lat: Number.parseFloat(section.dataset.latitude),
        lng: Number.parseFloat(section.dataset.longitude),
    };
    let mapPromise;
    let map;
    let originMarker;
    let polylines = [];
    let authenticationFailed = false;
    const authenticationFailureMessage = 'Google Maps could not load. Ask the administrator to check the Maps API key, billing, and daily quota.';
    onGoogleMapsAuthenticationFailure(() => {
        authenticationFailed = true;
        mapElement.textContent = authenticationFailureMessage;
        status.textContent = authenticationFailureMessage;
        button.disabled = true;
    });

    const loadMap = () => {
        mapPromise ??= loadGoogleMaps(section.dataset.googleKey).then(googleMaps => {
            mapElement.textContent = '';
            map = new googleMaps.Map(mapElement, { center: destination, zoom: 15, mapTypeControl: false });
            new googleMaps.Marker({
                map,
                position: destination,
                title: section.dataset.destinationType === 'pickup' ? 'Customer pickup' : 'Shop location',
                icon: section.dataset.destinationType === 'pickup'
                    ? 'https://maps.google.com/mapfiles/ms/icons/orange-dot.png'
                    : undefined,
            });

            return googleMaps;
        }).catch(error => {
            if (!authenticationFailed) mapElement.textContent = 'Map could not load. Check your connection and try again.';
            mapPromise = undefined;
            throw error;
        });

        return mapPromise;
    };

    const resetRoute = () => {
        polylines.forEach(polyline => polyline.setMap(null));
        polylines = [];
        originMarker?.setMap(null);
        originMarker = undefined;
        summary.hidden = true;
        stepsPanel.hidden = true;
        stepsList.replaceChildren();
    };

    if (!Number.isFinite(destination.lat) || !Number.isFinite(destination.lng)) {
        mapElement.textContent = 'Destination location is unavailable.';
        status.textContent = 'A driving route cannot be shown without a saved destination.';
        button.disabled = true;
        return;
    }

    button.addEventListener('click', async () => {
        resetRoute();
        if (!window.isSecureContext) {
            status.textContent = 'Location needs HTTPS or localhost on this device. Open the site through a secure address and try again.';
            return;
        }
        if (!navigator.geolocation) {
            status.textContent = 'This browser cannot share your location. Try another browser or device.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Finding your current location…';
        try {
            const position = await getCurrentLocation();
            const origin = { lat: position.coords.latitude, lng: position.coords.longitude };
            status.textContent = 'Loading the driving route…';
            const googleMaps = await loadMap();
            const { Route } = await googleMaps.importLibrary('routes');
            const { routes } = await Route.computeRoutes({
                origin,
                destination,
                travelMode: 'DRIVING',
                fields: ['path', 'legs', 'distanceMeters', 'durationMillis'],
            });

            if (!routes?.length) {
                status.textContent = 'No driving route was found between your location and this destination. Try again from another location.';
                return;
            }

            const route = routes[0];
            polylines = route.createPolylines();
            polylines.forEach(polyline => polyline.setMap(map));
            originMarker = new googleMaps.Marker({ map, position: origin, title: 'Your current location' });
            const bounds = new googleMaps.LatLngBounds();
            (route.path?.length ? route.path : [origin, destination]).forEach(point => bounds.extend(point));
            map.fitBounds(bounds, 30);
            googleMaps.event.addListenerOnce(map, 'bounds_changed', () => {
                if (map.getZoom() > 16) map.setZoom(16);
            });

            const distance = Number.isFinite(route.distanceMeters)
                ? `${(route.distanceMeters / 1000).toFixed(1)} km`
                : 'Distance unavailable';
            const duration = Number.isFinite(route.durationMillis)
                ? `${Math.max(1, Math.ceil(route.durationMillis / 60000))} min`
                : 'Time unavailable';
            summary.textContent = `${distance} · About ${duration} by car`;
            summary.hidden = false;

            const steps = route.legs?.flatMap(leg => leg.steps ?? []) ?? [];
            steps.forEach(step => {
                const item = document.createElement('li');
                item.className = 'list-group-item';
                const instruction = document.createElement('span');
                instruction.textContent = step.instructions || 'Continue along the route';
                item.append(instruction);
                if (step.localizedValues?.distance) {
                    const distanceText = document.createElement('small');
                    distanceText.className = 'd-block text-secondary mt-1';
                    distanceText.textContent = step.localizedValues.distance;
                    item.append(distanceText);
                }
                stepsList.append(item);
            });
            stepsPanel.hidden = steps.length === 0;
            status.textContent = steps.length
                ? 'Driving route and turn-by-turn directions are ready below.'
                : 'The route is on the map, but turn-by-turn steps are unavailable for it.';
        } catch (error) {
            if (typeof error?.code === 'number' && [1, 2, 3].includes(error.code)) {
                status.textContent = locationErrorMessage(error);
            } else if (authenticationFailed) {
                status.textContent = authenticationFailureMessage;
            } else if (!map) {
                status.textContent = 'Map could not load. Check your connection or ask the administrator to check Google Maps setup.';
            } else {
                status.textContent = 'Driving directions could not load. Try again later or contact support.';
            }
        } finally {
            button.disabled = authenticationFailed;
        }
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                observer.disconnect();
                loadMap().catch(() => {
                    if (!authenticationFailed) status.textContent = 'Map could not load. Check your connection or ask the administrator to check Google Maps setup.';
                });
            }
        }, { rootMargin: '150px' });
        observer.observe(mapElement);
    } else {
        loadMap().catch(() => {
            if (!authenticationFailed) status.textContent = 'Map could not load. Check your connection or ask the administrator to check Google Maps setup.';
        });
    }
});
