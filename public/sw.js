const cachePrefix = 'minglanilla-autocare-offline-';
const cacheName = cachePrefix + 'v1';
const offlinePage = '/offline.html';
const icons = ['/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png'];

self.addEventListener('push', event => {
    event.waitUntil((async () => {
        const message = event.data?.json();
        if (!message?.title || !message.url) return;
        const url = new URL(message.url, self.location.origin);
        if (url.origin !== self.location.origin) return;
        await self.registration.showNotification(message.title, {
            body: message.body,
            icon: icons[0],
            tag: message.tag,
            data: { url: url.href },
        });
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const url = new URL(event.notification.data?.url ?? '/', self.location.origin);
        if (url.origin !== self.location.origin) return;
        const tabs = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const current = tabs.find(tab => tab.url === url.href);
        if (current) await current.focus();
        else await self.clients.openWindow(url.href);
    })());
});

self.addEventListener('install', event => {
    const assets = [offlinePage, ...icons].map(path => new Request(path, {
        credentials: 'omit',
        redirect: 'error',
        cache: 'reload',
    }));
    event.waitUntil(caches.open(cacheName).then(cache => cache.addAll(assets)));
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const oldCaches = (await caches.keys()).filter(key => key.startsWith(cachePrefix) && key !== cacheName);
        await Promise.all(oldCaches.map(key => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (!url.search && icons.includes(url.pathname)) {
        event.respondWith(caches.open(cacheName).then(cache => cache.match(request)).then(icon => icon || fetch(request)));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const cache = await caches.open(cacheName);
            const offline = await cache.match(offlinePage);
            return new Response(offline?.body ?? 'You are offline. Reconnect and reload to continue.', {
                status: 503,
                headers: { 'Content-Type': offline ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8' },
            });
        }));
    }
});
