import { createShopMapPopup } from './shop-map-popup';

const embedUrl = shop => {
    const latitude = Number(shop.latitude);
    const longitude = Number(shop.longitude);
    const latitudeSpan = 0.012;
    const longitudeSpan = 0.018;
    const bounds = [longitude - longitudeSpan, latitude - latitudeSpan, longitude + longitudeSpan, latitude + latitudeSpan];
    const url = new URL('https://www.openstreetmap.org/export/embed.html');
    url.searchParams.set('bbox', bounds.join(','));
    url.searchParams.set('layer', 'mapnik');
    url.searchParams.set('marker', `${latitude},${longitude}`);

    return url.toString();
};

export const showProviderMapFallback = (mapElement, providers, providerUrl, selectedProviderId = null) => {
    if (mapElement.classList.contains('map-fallback-active')) return true;

    const shops = providers.filter(shop => shop.latitude !== null && shop.longitude !== null
        && Number(shop.latitude) >= 4 && Number(shop.latitude) <= 22
        && Number(shop.longitude) >= 116 && Number(shop.longitude) <= 127);
    if (!shops.length) return false;

    const wrapper = document.createElement('div');
    wrapper.className = 'provider-map-fallback';

    const toolbar = document.createElement('div');
    toolbar.className = 'provider-map-fallback-toolbar';
    const label = document.createElement('label');
    label.className = 'form-label fw-semibold mb-1';
    label.htmlFor = `${mapElement.id}-fallback-provider`;
    label.textContent = 'Show a provider on the map';
    const select = document.createElement('select');
    select.className = 'form-select';
    select.id = label.htmlFor;
    shops.forEach(shop => {
        const option = document.createElement('option');
        option.value = String(shop.id);
        option.textContent = `${shop.name} · ${shop.provider_type === 'independent_mechanic' ? 'Mobile mechanic' : 'Service shop'}`;
        select.append(option);
    });
    toolbar.append(label, select);

    const iframe = document.createElement('iframe');
    iframe.className = 'provider-map-fallback-frame';
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.setAttribute('title', 'Map showing provider location');

    const details = document.createElement('div');
    details.className = 'provider-map-fallback-details';
    const note = document.createElement('p');
    note.className = 'small text-secondary mb-0';
    note.textContent = 'OpenStreetMap preview while Google Maps is unavailable. Select a provider to move the pin.';
    const attribution = document.createElement('a');
    attribution.href = 'https://www.openstreetmap.org/copyright';
    attribution.textContent = 'Map data © OpenStreetMap contributors';
    attribution.target = '_blank';
    attribution.rel = 'noopener noreferrer';
    attribution.className = 'small';

    const showProvider = shop => {
        iframe.src = embedUrl(shop);
        iframe.title = `Map showing ${shop.name}${shop.provider_type === 'independent_mechanic' ? ' approximate service area' : ''}`;
        details.replaceChildren(createShopMapPopup(shop, providerUrl(shop), shop.provider_type === 'independent_mechanic' ? 'View mechanic' : 'View shop'));
    };
    select.addEventListener('change', () => showProvider(shops.find(shop => String(shop.id) === select.value) ?? shops[0]));
    const initial = shops.find(shop => String(shop.id) === String(selectedProviderId)) ?? shops[0];
    select.value = String(initial.id);
    showProvider(initial);

    wrapper.append(toolbar, iframe, details, note, attribution);
    mapElement.classList.remove('d-flex', 'align-items-center', 'justify-content-center', 'p-4', 'text-secondary');
    mapElement.classList.add('map-fallback-active');
    mapElement.replaceChildren(wrapper);

    return true;
};
