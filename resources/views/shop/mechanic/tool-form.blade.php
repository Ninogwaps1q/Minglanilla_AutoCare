<form method="POST" action="{{ $item ? route('shop.mechanic.tools.update', $item) : route('shop.mechanic.tools.store') }}" class="row g-3 align-items-end mt-1">
    @csrf
    @if ($item) @method('PUT') @endif
    <div class="col-md-6"><label class="d-block">Name<input class="form-control" name="name" maxlength="100" value="{{ $tool['name'] ?? '' }}" required></label></div>
    <div class="col-md-6"><label class="d-block">Category<select class="form-select" name="category"><option value="equipment" @selected(($tool['category'] ?? '') === 'equipment')>Equipment</option><option value="carried_part" @selected(($tool['category'] ?? '') === 'carried_part')>Carried spare part</option></select></label></div>
    <div class="col-md-4"><label class="d-block">Quantity<input class="form-control" name="quantity" type="number" min="0" max="9999" value="{{ $tool['quantity'] ?? 1 }}" required></label></div>
    <div class="col-md-4"><label class="d-block">Condition<select class="form-select" name="condition">@foreach (['good', 'worn', 'damaged'] as $condition)<option value="{{ $condition }}" @selected(($tool['condition'] ?? '') === $condition)>{{ ucfirst($condition) }}</option>@endforeach</select></label></div>
    <div class="col-md-4"><label class="d-block">Availability<select class="form-select" name="available"><option value="1" @selected($tool['available'] ?? true)>Available</option><option value="0" @selected(isset($tool['available']) && ! $tool['available'])>Unavailable</option></select></label></div>
    <div class="col-md-6"><label class="d-block">Maintenance notes<input class="form-control" name="maintenance" maxlength="255" value="{{ $tool['maintenance'] ?? '' }}"></label></div>
    <div class="col-md-6"><label class="d-block">Supported services<input class="form-control" name="supports" maxlength="255" value="{{ $tool['supports'] ?? '' }}"></label></div>
    <div class="col-12"><button class="btn btn-primary">{{ $item ? __('ui.Save changes') : 'Add equipment' }}</button></div>
</form>
