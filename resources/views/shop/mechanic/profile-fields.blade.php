@php($profile = $profile ?? [])
<div class="col-md-4"><label for="experience_years" class="form-label">{{ __('mechanic.experience_years') }}</label><input id="experience_years" name="experience_years" type="number" class="form-control" min="0" max="80" value="{{ old('experience_years', $profile['experience_years'] ?? '') }}"></div>
<div class="col-md-8"><label for="specializations" class="form-label">{{ __('mechanic.specializations') }}</label><input id="specializations" name="specializations" class="form-control" maxlength="255" value="{{ old('specializations', $profile['specializations'] ?? '') }}"></div>
<fieldset class="col-12"><legend class="form-label">{{ __('mechanic.vehicle_types') }}</legend>
    <div class="d-flex flex-wrap gap-3">
        @foreach (['car', 'motorcycle', 'truck'] as $type)
            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="vehicle_types[]" value="{{ $type }}" @checked(in_array($type, (array) old('vehicle_types', $profile['vehicle_types'] ?? []), true))> {{ __('mechanic.'.$type) }}</label>
        @endforeach
    </div>
</fieldset>
<div class="col-12"><label for="certifications" class="form-label">{{ __('mechanic.certifications') }}</label><input id="certifications" name="certifications" class="form-control" maxlength="255" value="{{ old('certifications', $profile['certifications'] ?? '') }}"></div>
<div class="col-12"><label for="work_history" class="form-label">{{ __('mechanic.work_history') }}</label><textarea id="work_history" name="work_history" rows="3" class="form-control" maxlength="2000">{{ old('work_history', $profile['work_history'] ?? '') }}</textarea></div>
