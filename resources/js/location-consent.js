export function rememberLocationConsent(allowed) {
    const endpoint = document.querySelector('meta[name="location-consent-url"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!endpoint || !token) return;

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ allowed }),
        keepalive: true,
    }).catch(() => {});
}
