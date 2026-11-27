export const createShopMapPopup = (shop, url, linkLabel, copy = {}) => {
    const popup = document.createElement('div');
    popup.className = 'shop-map-popup';

    const logo = document.createElement('span');
    logo.className = 'shop-map-popup-logo';
    const fallback = document.createElement('i');
    fallback.className = 'bi bi-wrench-adjustable-circle';
    fallback.setAttribute('aria-hidden', 'true');
    logo.append(fallback);
    if (shop.logo_url) {
        const image = document.createElement('img');
        image.src = shop.logo_url;
        image.alt = '';
        image.addEventListener('error', () => image.remove());
        logo.append(image);
    }

    const details = document.createElement('span');
    details.className = 'shop-map-popup-details';
    const name = document.createElement('strong');
    name.textContent = shop.name;
    const location = document.createElement('small');
    location.textContent = shop.address || (shop.provider_type === 'independent_mechanic' ? 'Approximate service area' : 'Shop location');
    const link = document.createElement('a');
    link.href = url;
    link.textContent = linkLabel;
    details.append(name, location, link);
    if (shop.booking_url || shop.directions_url) {
        const actions = document.createElement('div');
        actions.className = 'd-flex flex-wrap gap-2 mt-2';
        for (const [href, label, style] of [[shop.booking_url, copy.book_short || 'Book', 'btn-primary'], [shop.directions_url, copy.directions || 'Directions', 'btn-outline-primary']]) {
            if (!href) continue;
            const action = document.createElement('a');
            action.href = href;
            action.className = `btn btn-sm ${style}`;
            action.textContent = label;
            actions.append(action);
        }
        details.append(actions);
    }
    popup.append(logo, details);

    return popup;
};

export const openShopMapPopup = (googleMaps, infoWindow, map, mapElement, marker, content) => {
    infoWindow.setContent(content);
    googleMaps.event.addListenerOnce(infoWindow, 'domready', () => {
        requestAnimationFrame(() => {
            const popup = mapElement.querySelector('.gm-style-iw-c');
            if (!popup) return;

            const mapTop = mapElement.getBoundingClientRect().top;
            const popupTop = popup.getBoundingClientRect().top;
            const overflow = Math.ceil(mapTop + 12 - popupTop);
            if (overflow > 0) map.panBy(0, -overflow);
        });
    });
    infoWindow.open({ anchor: marker, map });
};
