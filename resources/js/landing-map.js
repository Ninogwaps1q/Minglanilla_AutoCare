const mapElement = document.querySelector('#landing-shop-map');
const status = document.querySelector('#landing-map-status');

if (mapElement && status) {
    const showFailure = () => {
        mapElement.innerHTML = '';
        const message = document.createElement('p');
        message.className = 'landing-map-message';
        message.textContent = 'The map could not load right now. You can still find shops in the directory.';
        mapElement.append(message);
        status.textContent = 'Map unavailable. Browse approved shops in the directory instead.';
    };

    const loadAsset = (tagName, attributes) => new Promise((resolve, reject) => {
        const element = document.createElement(tagName);
        Object.entries(attributes).forEach(([key, value]) => { element[key] = value; });
        element.addEventListener('load', resolve, { once: true });
        element.addEventListener('error', reject, { once: true });
        (tagName === 'link' ? document.head : document.body).append(element);
    });

    const loadMap = async () => {
        status.textContent = 'Loading approved shop locations…';
        mapElement.querySelector('.landing-map-message').textContent = 'Loading map…';

        try {
            const response = await fetch(mapElement.dataset.shopsUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('Shop locations unavailable');
            }
            const payload = await response.json();
            const shops = Array.isArray(payload.data) ? payload.data.filter(shop =>
                Number.isFinite(Number(shop.latitude)) && Number.isFinite(Number(shop.longitude))
                && Number(shop.latitude) >= -90 && Number(shop.latitude) <= 90
                && Number(shop.longitude) >= -180 && Number(shop.longitude) <= 180
            ) : [];

            await Promise.race([
                Promise.all([
                    loadAsset('link', {
                        rel: 'stylesheet',
                        href: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                        integrity: 'sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==',
                        crossOrigin: 'anonymous',
                    }),
                    loadAsset('script', {
                        src: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                        integrity: 'sha512-BwHfrr4c9kmRkLw6iXFdzcdWV/PGkVgiIyIWLLlTSXzWQzxuSg4DiQUCpauz/EWjgk5TYQqX/kvn9pG1NpYfqg==',
                        crossOrigin: 'anonymous',
                    }),
                ]),
                new Promise((_, reject) => window.setTimeout(() => reject(new Error('Map provider timed out')), 8000)),
            ]);

            mapElement.innerHTML = '';
            const map = window.L.map(mapElement).setView([10.2443, 123.7898], 13);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const bounds = [];
            shops.forEach(shop => {
                const point = [Number(shop.latitude), Number(shop.longitude)];
                bounds.push(point);
                const popup = document.createElement('div');
                popup.className = 'shop-map-popup';
                const avatar = document.createElement('div');
                avatar.className = 'shop-map-popup-logo';
                avatar.setAttribute('role', 'img');
                avatar.setAttribute('aria-label', `${shop.name} logo`);
                const fallbackIcon = document.createElement('i');
                fallbackIcon.className = 'bi bi-wrench-adjustable-circle';
                fallbackIcon.setAttribute('aria-hidden', 'true');
                avatar.append(fallbackIcon);
                if (shop.logo_url) {
                    const logo = document.createElement('img');
                    logo.src = shop.logo_url;
                    logo.alt = '';
                    logo.addEventListener('error', () => logo.remove(), { once: true });
                    avatar.append(logo);
                }
                const details = document.createElement('div');
                details.className = 'shop-map-popup-details';
                const name = document.createElement('strong');
                name.textContent = shop.name;
                const link = document.createElement('a');
                link.href = mapElement.dataset.shopUrlTemplate.replace('__SHOP_ID__', encodeURIComponent(shop.id));
                link.textContent = 'View shop';
                details.append(name, link);
                popup.append(avatar, details);
                window.L.marker(point).addTo(map).bindPopup(popup);
            });

            if (bounds.length) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
                status.textContent = `${bounds.length} ${bounds.length === 1 ? 'approved shop' : 'approved shops'} on the map. Select a marker to view a shop.`;
            } else {
                status.textContent = 'No approved shops have a saved map location yet. You can still browse the directory.';
            }
            window.setTimeout(() => map.invalidateSize(), 0);
        } catch {
            showFailure();
        }
    };

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                observer.disconnect();
                loadMap();
            }
        }, { rootMargin: '250px' });
        observer.observe(mapElement);
    } else {
        loadMap();
    }
}
