<x-layouts.app :title="__('roadside.title')">
    <div class="page-heading"><h1>{{ __('roadside.title') }}</h1></div>

    @if ($activeRequest)
        <section class="surface p-4 mb-4" aria-labelledby="active-request-title">
            <h2 class="h4" id="active-request-title">{{ __('roadside.active') }}</h2>
            @include('customer.emergency-requests.request', ['emergencyRequest' => $activeRequest])
        </section>
    @else
        <section class="surface p-4 mb-4" aria-labelledby="request-help-title">
            <h2 class="h4" id="request-help-title">{{ __('roadside.request_help') }}</h2>
            <x-form-errors />
            <form method="POST" action="{{ route('customer.emergency-requests.store') }}" class="vstack gap-3" data-roadside-pickup data-copy="{{ json_encode(__('roadside.location')) }}">
                @csrf
                <div>
                    <label class="form-label" for="emergency_type">{{ __('roadside.issue') }}</label>
                    <select id="emergency_type" class="form-select" name="emergency_type" required>
                        @foreach (__('roadside.issues') as $value => $label)
                            <option value="{{ $value }}" @selected(old('emergency_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset class="vstack gap-2">
                    <legend class="h6">{{ __('roadside.pickup') }}</legend>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="location-button" class="btn btn-outline-primary" type="button" aria-describedby="pickup-location-permission">{{ __('roadside.gps') }}</button>
                    </div>
                    <p id="pickup-location-permission" class="form-text mb-0">{{ __('roadside.location_privacy') }}</p>
                    <p id="location-status" class="small mb-0" role="status" aria-live="polite">{{ old('latitude') && old('longitude') && ! $errors->hasAny(['latitude', 'longitude']) ? __('roadside.location.ready') : __('roadside.location.start') }}</p>
                    <details id="pickup-pin" class="workspace-disclosure" @if ($errors->hasAny(['latitude', 'longitude'])) open @endif>
                        <summary class="fw-semibold py-2">{{ __('roadside.manual') }}</summary>
                        <div class="pt-2">
                            <x-location-picker :label="__('roadside.pickup_map')" :hint="__('roadside.pickup_hint')" defer-map />
                        </div>
                    </details>
                </fieldset>
                <div>
                    <button class="btn btn-primary" type="submit">{{ __('roadside.send') }}</button>
                </div>
                <details class="workspace-disclosure" @if(old('address') || old('barangay_id') || old('description') || old('vehicle_id') || old('landmark') || $errors->hasAny(['address', 'barangay_id', 'description', 'vehicle_id', 'landmark'])) open @endif>
                    <summary class="fw-semibold py-2">{{ __('roadside.optional') }}</summary>
                    <div class="vstack gap-3 pt-3">
                        <div>
                            <label class="form-label" for="vehicle_id">{{ __('ui.Vehicle') }}</label>
                            <select id="vehicle_id" class="form-select" name="vehicle_id">
                                <option value="">{{ __('roadside.no_vehicle') }}</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }} · {{ $vehicle->make }} {{ $vehicle->model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <fieldset><legend class="h6">{{ __('roadside.pickup_address') }}</legend><x-address-picker prefix="pickup" :street-max="255" /></fieldset>
                        <div><label class="form-label" for="description">{{ __('roadside.details') }}</label><textarea id="description" class="form-control" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea></div>
                    </div>
                </details>
            </form>
        </section>
    @endif

    <section class="surface p-4" aria-labelledby="requests-title">
        <h2 class="h4" id="requests-title">{{ __('roadside.history') }}</h2>
        @forelse ($requests->reject(fn ($item) => $item->id === $activeRequest?->id) as $emergencyRequest)
            @include('customer.emergency-requests.request', ['emergencyRequest' => $emergencyRequest])
        @empty
            <p class="text-secondary mb-0">{{ __('roadside.empty') }}</p>
        @endforelse
    </section>
</x-layouts.app>
