@props(['prefix' => 'address', 'barangayName' => 'barangay_id', 'streetName' => 'address', 'landmarkName' => 'landmark', 'selectedBarangay' => null, 'street' => null, 'landmark' => null, 'streetMax' => 500, 'required' => false, 'areaOnly' => false, 'area' => []])
@php($selected = old($barangayName, $selectedBarangay))
<div class="row g-3" data-address-picker data-regions-url="{{ route('catalog.regions') }}" data-provinces-url="{{ route('catalog.provinces') }}" data-municipalities-url="{{ route('catalog.municipalities') }}" data-barangays-url="{{ route('catalog.barangays') }}" data-path-url="{{ route('catalog.address-path') }}" data-initial-barangay="{{ $areaOnly ? '' : $selected }}">
    <div class="{{ $areaOnly ? 'col-md-4' : 'col-md-6' }}">
        <label class="form-label" for="{{ $prefix }}-region">Region</label>
        <select id="{{ $prefix }}-region" class="form-select" data-address-region data-address-choice @if($areaOnly) name="region_id" @endif @required($required)>
            <option value="">{{ $areaOnly && ! $required ? 'All regions' : 'Select region' }}</option>
            @if ($areaOnly && ($id = $area['region_id'] ?? null))<option value="{{ $id }}" selected>Selected region</option>@endif
        </select>
    </div>
    <div class="{{ $areaOnly ? 'col-md-4' : 'col-md-6' }}">
        <label class="form-label" for="{{ $prefix }}-province">Province or area</label>
        <select id="{{ $prefix }}-province" class="form-select" data-address-province data-address-choice @if($areaOnly) name="province_id" @endif @required($required)>
            <option value="">{{ $areaOnly && ! $required ? 'All provinces and areas' : 'Select province or area' }}</option>
            @if ($areaOnly && ($id = $area['province_id'] ?? null))<option value="{{ $id }}" selected>Selected province or area</option>@endif
        </select>
    </div>
    <div class="{{ $areaOnly ? 'col-md-4' : 'col-md-6' }}">
        <label class="form-label" for="{{ $prefix }}-municipality">City / municipality</label>
        <select id="{{ $prefix }}-municipality" class="form-select" data-address-municipality data-address-choice @if($areaOnly) name="municipality_id" @endif @required($required)>
            <option value="">{{ $areaOnly && ! $required ? 'All cities and municipalities' : 'Select city or municipality' }}</option>
            @if ($areaOnly && ($id = $area['municipality_id'] ?? null))<option value="{{ $id }}" selected>Selected city or municipality</option>@endif
        </select>
    </div>
    @unless ($areaOnly)
    <div class="col-md-6"><label class="form-label" for="{{ $prefix }}-barangay">Barangay</label><select id="{{ $prefix }}-barangay" class="form-select @error($barangayName) is-invalid @enderror" name="{{ $barangayName }}" data-address-barangay data-address-choice @if($required) required @endif><option value="{{ $selected }}">{{ $selected ? 'Current barangay (loading…)' : 'Select barangay' }}</option></select>@error($barangayName)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-8"><label class="form-label" for="{{ $prefix }}-street">Street / house number</label><input id="{{ $prefix }}-street" class="form-control @error($streetName) is-invalid @enderror" name="{{ $streetName }}" value="{{ old($streetName, $street) }}" maxlength="{{ $streetMax }}" autocomplete="street-address" data-address-street @if($required) required @endif>@error($streetName)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="{{ $prefix }}-landmark">Landmark (optional)</label><input id="{{ $prefix }}-landmark" class="form-control @error($landmarkName) is-invalid @enderror" name="{{ $landmarkName }}" value="{{ old($landmarkName, $landmark) }}" maxlength="255">@error($landmarkName)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    @endunless
    <div class="col-12"><p class="form-text mb-0" role="status" aria-live="polite" data-address-status>Choose a region to load provinces and cities.</p></div>
    @unless ($areaOnly)<p class="col-12 form-text mt-1 mb-0">Address data from the Philippine Statistics Authority (PSGC).</p>@endunless
</div>
