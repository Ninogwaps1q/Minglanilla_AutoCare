<fieldset class="row g-3 mb-3">
    <legend class="h6">Estimate item</legend>
    <div class="col-md-6"><label class="form-label" for="type-{{ $index }}">{{ __('extensions.estimate.category') }}</label>
        <select class="form-select" id="type-{{ $index }}" name="items[{{ $index }}][type]">
            @foreach (['other', 'service', 'part', 'labor'] as $type)
                <option value="{{ $type }}" @selected(($item['type'] ?? 'other') === $type)>{{ __('extensions.estimate.type.'.$type) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label" for="description-{{ $index }}">Description</label><input
            id="description-{{ $index }}" name="items[{{ $index }}][description]" class="form-control"
            value="{{ $item['description'] ?? '' }}" maxlength="1000" required></div>
    <div class="col-md-3"><label class="form-label" for="quantity-{{ $index }}">Quantity</label><input
            id="quantity-{{ $index }}" name="items[{{ $index }}][quantity]" class="form-control" type="number" min="1"
            max="10000" step="1" value="{{ $item['quantity'] ?? 1 }}" required></div>
    <div class="col-md-3"><label class="form-label" for="price-{{ $index }}">Unit price (PHP)</label><input
            id="price-{{ $index }}" name="items[{{ $index }}][unit_price]" class="form-control" type="number" min="0"
            max="999999" step="0.01" value="{{ $item['unit_price'] ?? '' }}" required></div>
</fieldset>
