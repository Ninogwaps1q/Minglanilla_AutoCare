const form = document.querySelector('[data-roadside-pickup]');
const manual = form.querySelector('#pickup-pin');
const latitude = form.elements.latitude;
const longitude = form.elements.longitude;
const status = form.querySelector('#location-status');
const button = form.querySelector('#location-button');
const copy = JSON.parse(form.dataset.copy);

const hasCoordinates = () => latitude.value && longitude.value && latitude.validity.valid && longitude.validity.valid;
let lastChoice = 0;

for (const input of [latitude, longitude]) {
    input.addEventListener('input', () => {
        lastChoice++;
        status.textContent = hasCoordinates() ? copy.ready : copy.start;
    });
}

button.addEventListener('click', () => {
    if (!navigator.geolocation) {
        manual.open = true;
        status.textContent = copy.unavailable;
        return;
    }

    const choice = ++lastChoice;
    button.disabled = true;
    status.textContent = copy.locating;
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            button.disabled = false;
            document.dispatchEvent(new CustomEvent('autocare:location-consent', { detail: true }));
            if (choice !== lastChoice) return;

            latitude.value = coords.latitude.toFixed(7);
            longitude.value = coords.longitude.toFixed(7);
            longitude.dispatchEvent(new Event('change'));
            status.textContent = copy.ready;
        },
        error => {
            button.disabled = false;
            if (error.code === 1) {
                document.dispatchEvent(new CustomEvent('autocare:location-consent', { detail: false }));
            }
            if (choice !== lastChoice) return;

            manual.open = true;
            status.textContent = copy.unavailable;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
});

form.addEventListener('submit', event => {
    if (hasCoordinates()) return;
    event.preventDefault();
    manual.open = true;
    status.textContent = copy.restore;
    manual.querySelector('summary').focus();
});
