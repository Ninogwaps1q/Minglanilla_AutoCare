document.querySelectorAll('[data-address-picker]').forEach(picker => {
    const region = picker.querySelector('[data-address-region]');
    const province = picker.querySelector('[data-address-province]');
    const municipality = picker.querySelector('[data-address-municipality]');
    const barangay = picker.querySelector('[data-address-barangay]');
    const status = picker.querySelector('[data-address-status]');
    const fill = (select, entries, placeholder, selected) => {
        select.replaceChildren(new Option(placeholder, ''));
        entries.forEach(entry => select.add(new Option(entry.name, String(entry.id))));
        select.value = selected && [...select.options].some(option => option.value === String(selected)) ? String(selected) : '';
    };
    const load = async (select, urlName, parentName, parentId, selected = '') => {
        if (!parentId && parentName) return;
        const url = new URL(picker.dataset[urlName]);
        if (parentName) url.searchParams.set(parentName, parentId);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Location list unavailable');
        const entries = await response.json();
        fill(select, entries, select === region ? 'Select region' : select === province ? 'Select province' : select === municipality ? 'Select city or municipality' : 'Select barangay', selected);
    };
    const clearBelow = (level) => {
        if (level <= 1) fill(province, [], 'Select province');
        if (level <= 2) fill(municipality, [], 'Select city or municipality');
        if (level <= 3) fill(barangay, [], 'Select barangay');
    };
    const showError = () => { status.textContent = 'Locations could not load. Try again or contact support before saving this address.'; };
    region.addEventListener('change', async () => {
        clearBelow(1);
        try { await load(province, 'provincesUrl', 'region_id', region.value); status.textContent = 'Choose a province.'; } catch { showError(); }
    });
    province.addEventListener('change', async () => {
        clearBelow(2);
        try { await load(municipality, 'municipalitiesUrl', 'province_id', province.value); status.textContent = 'Choose a city or municipality.'; } catch { showError(); }
    });
    municipality.addEventListener('change', async () => {
        clearBelow(3);
        try { await load(barangay, 'barangaysUrl', 'municipality_id', municipality.value); status.textContent = 'Choose a barangay.'; } catch { showError(); }
    });
    barangay.addEventListener('change', () => { status.textContent = barangay.value ? 'Address area selected.' : 'Choose a barangay.'; });

    (async () => {
        try {
            const initialId = picker.dataset.initialBarangay;
            let path = null;
            if (initialId) {
                const url = new URL(picker.dataset.pathUrl);
                url.searchParams.set('barangay_id', initialId);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (response.ok) path = await response.json();
            }
            await load(region, 'regionsUrl', null, null, path?.region_id);
            if (path?.region_id) await load(province, 'provincesUrl', 'region_id', path.region_id, path.province_id);
            if (path?.province_id) await load(municipality, 'municipalitiesUrl', 'province_id', path.province_id, path.municipality_id);
            if (path?.municipality_id) await load(barangay, 'barangaysUrl', 'municipality_id', path.municipality_id, path.barangay_id);
            status.textContent = path ? 'Current address area loaded.' : 'Choose a region to load provinces and cities.';
        } catch { showError(); }
    })();
});
