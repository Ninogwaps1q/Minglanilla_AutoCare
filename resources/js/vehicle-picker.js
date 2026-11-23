const picker = document.querySelector('[data-vehicle-picker]');

if (picker) {
    const type = picker.querySelector('#vehicle_type');
    const make = picker.querySelector('#vehicle_make_id');
    const model = picker.querySelector('#vehicle_model_id');
    const variant = picker.querySelector('#variant');
    const manualMake = picker.querySelector('[data-manual-make]');
    const manualModel = picker.querySelector('[data-manual-model]');
    const manualVariant = picker.querySelector('[data-manual-variant]');
    const status = picker.querySelector('[data-picker-status]');
    const initial = { make: picker.dataset.initialMake, model: picker.dataset.initialModel, variant: picker.dataset.initialVariant };
    let currentModels = [];

    const fill = (select, entries, placeholder, selected) => {
        select.replaceChildren(new Option(placeholder, ''));
        entries.forEach(entry => select.add(new Option(entry.name, String(entry.id))));
        select.value = selected && [...select.options].some(option => option.value === String(selected)) ? String(selected) : '';
    };
    const syncManual = () => {
        manualMake.hidden = Boolean(make.value);
        manualModel.hidden = Boolean(model.value);
        manualVariant.hidden = variant.value !== '__other';
        manualMake.querySelector('input').required = !make.value;
        manualModel.querySelector('input').required = !model.value;
        manualVariant.querySelector('input').required = variant.value === '__other';
    };
    const syncVariants = (selected = '') => {
        const chosen = currentModels.find(entry => String(entry.id) === model.value);
        const options = chosen?.variants?.length ? chosen.variants : ['Base'];
        variant.replaceChildren(new Option('Select variant', ''));
        options.forEach(name => variant.add(new Option(name, name)));
        variant.add(new Option('Other / not listed', '__other'));
        if (selected && options.includes(selected)) variant.value = selected;
        else if (selected) {
            variant.value = '__other';
            manualVariant.querySelector('input').value = selected;
        }
        syncManual();
    };
    const loadModels = async (selected = '') => {
        currentModels = [];
        fill(model, [], 'Other / not listed', '');
        syncVariants();
        if (!make.value) { syncManual(); return; }
        try {
            const url = new URL(picker.dataset.modelsUrl);
            url.searchParams.set('make_id', make.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            currentModels = await response.json();
            fill(model, currentModels, 'Other / not listed', selected);
            status.textContent = `${currentModels.length} models available. Choose Other if yours is missing.`;
            syncVariants(selected ? initial.variant : '');
        } catch {
            status.textContent = 'Models could not load. Enter the make and model manually.';
            make.value = '';
        }
        syncManual();
    };
    const loadMakes = async (selected = '') => {
        fill(make, [], 'Other / not listed', '');
        await loadModels();
        if (!['car', 'motorcycle'].includes(type.value)) { syncManual(); return; }
        try {
            const url = new URL(picker.dataset.makesUrl);
            url.searchParams.set('type', type.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            fill(make, await response.json(), 'Other / not listed', selected);
            status.textContent = 'Choose a make, then a model. Other / not listed lets you enter one manually.';
            await loadModels(selected ? initial.model : '');
        } catch {
            status.textContent = 'Makes could not load. Enter the make and model manually.';
        }
        syncManual();
    };

    type.addEventListener('change', () => loadMakes());
    make.addEventListener('change', () => loadModels());
    model.addEventListener('change', () => { syncVariants(); syncManual(); });
    variant.addEventListener('change', syncManual);
    syncManual();
    loadMakes(initial.make);
}
