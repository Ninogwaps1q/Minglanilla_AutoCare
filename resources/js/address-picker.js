document.querySelectorAll('[data-address-picker]').forEach(picker => {
    const region = picker.querySelector('[data-address-region]');
    const province = picker.querySelector('[data-address-province]');
    const municipality = picker.querySelector('[data-address-municipality]');
    const barangay = picker.querySelector('[data-address-barangay]');
    const status = picker.querySelector('[data-address-status]');
    const selects = [region, province, municipality, barangay].filter(Boolean);
    const placeholders = new Map(selects.map(select => [select, select.options[0].textContent]));
    let selectionVersion = 0;

    const fill = (select, entries, selected = '') => {
        select.replaceChildren(new Option(placeholders.get(select), ''));
        entries.forEach(entry => select.add(new Option(entry.name, String(entry.id))));
        select.value = [...select.options].some(option => option.value === String(selected)) ? String(selected) : '';
    };

    const read = async url => {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Location list unavailable');
        return response.json();
    };

    const load = async (select, urlName, version, parentName = null, parentId = null, selected = '') => {
        if (!select || (parentName && !parentId)) return;
        const url = new URL(picker.dataset[urlName]);
        if (parentName) url.searchParams.set(parentName, parentId);
        const entries = await read(url);
        if (version === selectionVersion) fill(select, entries, selected);
    };

    const showError = version => {
        if (version === selectionVersion) status.textContent = 'Locations could not load. Try again before saving or filtering.';
    };

    const choices = [
        [region, province, 'provincesUrl', 'region_id', 'Choose a province or area.'],
        [province, municipality, 'municipalitiesUrl', 'province_id', 'Choose a city or municipality.'],
        [municipality, barangay, 'barangaysUrl', 'municipality_id', barangay ? 'Choose a barangay.' : 'Area selected.'],
    ];

    choices.forEach(([select, child, url, parent, message], index) => {
        select.addEventListener('change', async () => {
            const version = ++selectionVersion;
            selects.slice(index + 1).forEach(next => fill(next, []));
            try {
                await load(child, url, version, parent, select.value);
                if (version === selectionVersion) status.textContent = select.value ? message : 'Choose a region, province, or city.';
            } catch {
                showError(version);
            }
        });
    });
    barangay?.addEventListener('change', () => {
        selectionVersion++;
        status.textContent = barangay.value ? 'Address area selected.' : 'Choose a barangay.';
    });

    const initialize = async () => {
        const version = selectionVersion;
        const initial = { region_id: region.value, province_id: province.value, municipality_id: municipality.value, barangay_id: barangay?.value };
        try {
            if (picker.dataset.initialBarangay) {
                const url = new URL(picker.dataset.pathUrl);
                url.searchParams.set('barangay_id', picker.dataset.initialBarangay);
                Object.assign(initial, await read(url));
            }

            await load(region, 'regionsUrl', version, null, null, initial.region_id);
            await load(province, 'provincesUrl', version, 'region_id', initial.region_id, initial.province_id);
            await load(municipality, 'municipalitiesUrl', version, 'province_id', initial.province_id, initial.municipality_id);
            await load(barangay, 'barangaysUrl', version, 'municipality_id', initial.municipality_id, initial.barangay_id);
            if (version === selectionVersion) {
                status.textContent = region.value ? 'Area selection loaded.' : 'Choose a region to load provinces and cities.';
                picker.dataset.ready = 'true';
                picker.dispatchEvent(new Event('address:ready'));
            }
        } catch {
            showError(version);
        }
    };

    initialize();
});
