document.querySelectorAll('[data-nearby-search]').forEach(form => {
    const button = form.querySelector('[data-nearby-button]');
    const status = form.querySelector('[data-nearby-status]');

    button.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Location is not supported by this browser. You can still browse all providers.';
            return;
        }
        button.disabled = true;
        status.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(position => {
            form.elements.namedItem('latitude').value = position.coords.latitude;
            form.elements.namedItem('longitude').value = position.coords.longitude;
            const sort = form.elements.namedItem('sort');
            sort.disabled = false;
            const distance = sort.querySelector('[value="distance"]');
            if (distance) distance.disabled = false;
            sort.value = 'distance';
            button.disabled = false;
            form.requestSubmit();
        }, () => {
            button.disabled = false;
            status.textContent = 'We could not access your location. You can still browse all providers.';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
    });
});
