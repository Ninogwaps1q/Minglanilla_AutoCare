<fieldset class="border rounded p-3 mb-3">
    <legend class="float-none w-auto px-2 fs-6">Inspection item</legend>
    <div class="row g-3">
        <div class="col-md-4"><label for="category-{{ $index }}" class="form-label">Category</label><input id="category-{{ $index }}" name="items[{{ $index }}][category]" class="form-control" maxlength="255" value="{{ $item['category'] ?? '' }}" required></div>
        <div class="col-md-4"><label for="item-{{ $index }}" class="form-label">Item name</label><input id="item-{{ $index }}" name="items[{{ $index }}][item_name]" class="form-control" maxlength="255" value="{{ $item['item_name'] ?? '' }}" required></div>
        <div class="col-md-4"><label for="condition-{{ $index }}" class="form-label">Condition</label><select id="condition-{{ $index }}" name="items[{{ $index }}][condition]" class="form-select" required>@foreach (['Good', 'Needs Attention', 'Critical'] as $condition)<option @selected(($item['condition'] ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></div>
        <div class="col-12"><label for="recommendation-{{ $index }}" class="form-label">Recommendation</label><textarea id="recommendation-{{ $index }}" name="items[{{ $index }}][recommendation]" class="form-control" rows="2" maxlength="5000">{{ $item['recommendation'] ?? '' }}</textarea></div>
    </div>
</fieldset>
