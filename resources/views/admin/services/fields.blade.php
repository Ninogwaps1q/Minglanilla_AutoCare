@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">Service could not be saved. Please correct these fields:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Service name</label>
        <input id="name" class="form-control" name="name" maxlength="255" value="{{ old('name', $service->name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="category_id">Category</label>
        <select id="category_id" class="form-select" name="category_id" required>
            <option value="">Choose a category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $service->category_id ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" class="form-control" name="description" rows="3" maxlength="5000">{{ old('description', $service->description ?? '') }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="default_price">Reference price (PHP)</label>
        <input id="default_price" class="form-control" name="default_price" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('default_price', $service->default_price ?? '') }}" aria-describedby="price-help">
        <div id="price-help" class="form-text">Optional. Each shop sets its own service price.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="estimated_duration">Estimated duration (minutes)</label>
        <input id="estimated_duration" class="form-control" name="estimated_duration" type="number" min="1" max="65535" step="1" value="{{ old('estimated_duration', $service->estimated_duration ?? '') }}">
    </div>
</div>
