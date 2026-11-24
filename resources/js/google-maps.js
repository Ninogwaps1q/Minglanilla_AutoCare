let mapPromise;
let authenticationFailed = false;
const failureListeners = new Set();

export const onGoogleMapsAuthenticationFailure = listener => {
    failureListeners.add(listener);
    if (authenticationFailed) listener();

    return () => failureListeners.delete(listener);
};

export const loadGoogleMaps = key => {
    if (!key) {
        return Promise.reject(new Error('Google Maps API key is not configured.'));
    }

    mapPromise ??= new Promise((resolve, reject) => {
        if (authenticationFailed) {
            reject(new Error('Google Maps authentication failed.'));
            return;
        }
        if (window.google?.maps) {
            resolve(window.google.maps);
            return;
        }

        const callback = '__autoCareGoogleMapsReady';
        const script = document.createElement('script');
        const timeout = window.setTimeout(() => reject(new Error('Google Maps timed out.')), 12000);
        window.gm_authFailure = () => {
            authenticationFailed = true;
            window.clearTimeout(timeout);
            reject(new Error('Google Maps authentication failed.'));
            failureListeners.forEach(listener => listener());
        };
        window[callback] = () => {
            window.clearTimeout(timeout);
            delete window[callback];
            if (!authenticationFailed) resolve(window.google.maps);
        };
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&loading=async&callback=${callback}`;
        script.async = true;
        script.onerror = () => {
            window.clearTimeout(timeout);
            delete window[callback];
            mapPromise = undefined;
            reject(new Error('Google Maps could not load.'));
        };
        document.head.append(script);
    });

    return mapPromise;
};
