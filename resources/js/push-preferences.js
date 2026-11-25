const section = document.querySelector('[data-push-preferences]');
const enable = section?.querySelector('[data-push-enable]');
const disable = section?.querySelector('[data-push-disable]');
const status = section?.querySelector('[data-push-status]');
const token = document.querySelector('meta[name="csrf-token"]')?.content;

async function saveSubscription(method, subscription) {
    const response = await fetch(section.dataset.url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: subscription ? JSON.stringify(subscription) : undefined,
    });
    if (!response.ok) throw new Error('Subscription request failed');
}

if (enable && 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window) {
    enable.hidden = false;
    enable.addEventListener('click', async () => {
        enable.disabled = true;
        try {
            if (await Notification.requestPermission() !== 'granted') throw new Error('Permission denied');
            const registration = await navigator.serviceWorker.getRegistration();
            if (!registration?.active) throw new Error('Service worker unavailable');
            const key = section.dataset.publicKey.replace(/-/g, '+').replace(/_/g, '/');
            const applicationServerKey = Uint8Array.from(atob(key), character => character.charCodeAt(0));
            const subscription = await registration.pushManager.getSubscription()
                ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey });
            await saveSubscription('POST', subscription);
            status.textContent = section.dataset.success;
            disable.hidden = false;
        } catch {
            status.textContent = section.dataset.error;
        } finally {
            enable.disabled = false;
        }
    });
} else if (enable) {
    status.textContent = section.dataset.error;
}

disable?.addEventListener('click', async () => {
    disable.disabled = true;
    try {
        await saveSubscription('DELETE');
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            const subscription = await registration?.pushManager?.getSubscription();
            await subscription?.unsubscribe().catch(() => {});
        }
        status.textContent = section.dataset.disabled;
        disable.hidden = true;
    } catch {
        status.textContent = section.dataset.error;
    } finally {
        disable.disabled = false;
    }
});
