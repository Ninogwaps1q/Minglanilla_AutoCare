const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

document.querySelectorAll('[data-roadside-share]').forEach(panel => {
    const copy = JSON.parse(panel.dataset.copy);
    const startButton = panel.querySelector('[data-share-location]');
    const stopButton = panel.querySelector('[data-stop-location]');
    const arrivalButton = panel.querySelector('[data-mark-arrival]');
    const status = panel.querySelector('[data-location-status]');
    let watch = null;
    let lastSent = 0;
    let pending = Promise.resolve();

    const send = async (attributes, keepalive = false) => {
        const response = await fetch(panel.dataset.roadsideShare, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(attributes),
            keepalive,
        });
        if (!response.ok) throw new Error('Location update failed');
    };

    const pauseWatch = () => {
        if (watch !== null) navigator.geolocation.clearWatch(watch);
        watch = null;
        if (startButton) startButton.hidden = false;
        if (stopButton) stopButton.hidden = true;
    };

    startButton?.addEventListener('click', () => {
        if (!window.isSecureContext || !navigator.geolocation) {
            status.textContent = copy.location_failure;
            return;
        }
        startButton.hidden = true;
        stopButton.hidden = false;
        lastSent = 0;
        watch = navigator.geolocation.watchPosition(position => {
            if (watch === null || Date.now() - lastSent < 10000) return;
            lastSent = Date.now();
            pending = pending.then(async () => {
                if (watch === null) return;
                try {
                    await send({ action: 'share', consent: true, latitude: position.coords.latitude, longitude: position.coords.longitude });
                    status.textContent = copy.location_live;
                } catch {
                    pauseWatch();
                    status.textContent = copy.location_failure;
                }
            });
        }, () => {
            pauseWatch();
            status.textContent = copy.location_failure;
            pending.then(() => send({ action: 'stop' })).catch(() => {});
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
    });

    stopButton?.addEventListener('click', async () => {
        pauseWatch();
        startButton.disabled = true;
        try {
            await pending;
            await send({ action: 'stop' });
            status.textContent = copy.location_stopped;
        } catch {
            status.textContent = copy.location_failure;
        } finally {
            startButton.disabled = false;
        }
    });

    arrivalButton.addEventListener('click', async () => {
        pauseWatch();
        arrivalButton.disabled = true;
        try {
            await pending;
            await send({ action: 'arrive' });
            status.textContent = copy.responder_arrived;
            if (startButton) startButton.hidden = true;
            arrivalButton.hidden = true;
        } catch {
            status.textContent = copy.location_failure;
        } finally {
            arrivalButton.disabled = false;
        }
    });

    window.addEventListener('pagehide', () => {
        if (watch === null) return;
        pauseWatch();
        pending.then(() => send({ action: 'stop' }, true)).catch(() => {});
    });
});

document.querySelectorAll('[data-roadside-tracking]').forEach(status => {
    const copy = JSON.parse(status.dataset.copy);
    const radians = degrees => degrees * Math.PI / 180;
    const pickupLat = radians(Number(status.dataset.latitude));
    const pickupLng = Number(status.dataset.longitude);
    let timer;

    const refresh = async () => {
        try {
            const response = await fetch(status.dataset.roadsideTracking, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Location unavailable');
            const { active, arrived, location } = await response.json();
            if (!active || arrived) {
                status.textContent = arrived ? copy.responder_arrived : copy.roadside_closed;
                return;
            }
            if (location) {
                const lat = radians(location.latitude);
                const distance = 12742 * Math.asin(Math.sqrt(Math.min(1, Math.sin((lat - pickupLat) / 2) ** 2
                    + Math.cos(lat) * Math.cos(pickupLat) * Math.sin(radians(location.longitude - pickupLng) / 2) ** 2)));
                status.textContent = copy.location_distance.replace(':distance', distance.toFixed(1));
            } else {
                status.textContent = copy.location_waiting;
            }
        } catch {
            status.textContent = copy.location_failure;
        }
        timer = window.setTimeout(refresh, 15000);
    };

    refresh();
    window.addEventListener('pagehide', () => window.clearTimeout(timer));
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
});
