export const createShopMapPopup = (shop, url, linkLabel) => {
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
    location.textContent = shop.provider_type === 'independent_mechanic' ? 'Approximate service area' : 'Shop location';
    const link = document.createElement('a');
    link.href = url;
    link.textContent = linkLabel;
    details.append(name, location, link);
    popup.append(logo, details);

    return popup;
};
