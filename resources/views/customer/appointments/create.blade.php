<x-layouts.app :title="__('ui.' . 'Book a service')">
    @php
        $bookingValue = function ($key, $default = null) use ($bookingDraft) {
            $value = old($key, array_key_exists($key, $bookingDraft) ? $bookingDraft[$key] : $default);

            return is_scalar($value) ? $value : null;
        };
        $shopId = $bookingValue('shop_id', $selectedShopId);
        $selectedServices = (array) old('service_ids', $bookingDraft['service_ids'] ?? []);
        $selectedProvider = $shops->firstWhere('id', $shopId);
        $vehicleFormErrors = $errors->hasAny(['plate_number', 'vehicle_type', 'vehicle_make_id', 'vehicle_model_id', 'make', 'model', 'year', 'variant', 'variant_other', 'color', 'current_mileage']);
    @endphp
    <div class="col-lg-9 mx-auto">
        <div class="page-heading booking-page-heading d-flex align-items-start gap-3">
            <a class="btn btn-outline-secondary" id="booking-exit" href="{{ route('customer.appointments.index') }}" aria-label="{{ __('ui.Back to appointments') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <button class="btn btn-outline-secondary" type="button" id="booking-prev" aria-label="{{ __('booking.back') }}" hidden><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <div class="text-break">
                <h1 id="booking-provider-summary">{{ $selectedProvider?->name ?? __('ui.Book a service') }}</h1>
                <p id="booking-provider-area" class="mb-0" @unless ($selectedProvider) hidden @endunless>{{ $selectedProvider?->barangay?->areaLabel() }}</p>
            </div>
        </div>

        @if ($vehicles->isEmpty())
            <div class="alert alert-info" role="status">Add a vehicle before booking. Open “Add a vehicle here” below to continue.</div>
        @elseif ($shops->isEmpty())
            <div class="alert alert-info" role="status">No verified providers are available right now. <a href="{{ route('shops.index') }}" class="alert-link">{{ __('ui.' . 'Browse the directory') }}</a></div>
        @endif

        <details class="workspace-disclosure mb-3" @if ($vehicleFormErrors) open @endif>
            <summary class="fw-semibold">{{ __('ui.' . 'Add a vehicle here') }}</summary>
            <form method="POST" action="{{ route('customer.vehicles.store') }}" class="row g-3 mt-2" data-booking-vehicle-form>
                @csrf
                <input type="hidden" name="return_to_booking" value="1">
                @if ($vehicleFormErrors)<div class="col-12"><x-form-errors /></div>@endif
                <div class="col-md-4"><label for="booking-plate" class="form-label">{{ __('ui.' . 'Plate number') }}</label><input id="booking-plate" class="form-control" name="plate_number" value="{{ old('plate_number') }}" maxlength="30" required></div>
                <div class="col-md-4"><label for="booking-color" class="form-label">{{ __('ui.' . 'Color (optional)') }}</label><input id="booking-color" class="form-control" name="color" value="{{ old('color') }}" maxlength="50"></div>
                <div class="col-md-4"><label for="booking-mileage" class="form-label">{{ __('ui.' . 'Mileage (optional)') }}</label><input id="booking-mileage" class="form-control" type="number" min="0" name="current_mileage" value="{{ old('current_mileage') }}"></div>
                <div class="col-12"><x-vehicle-picker :vehicle="$newVehicle" /></div>
                <div class="col-12"><button class="btn btn-outline-primary">{{ __('ui.' . 'Save vehicle and continue booking') }}</button></div>
            </form>
        </details>

        <form method="POST" action="{{ route('customer.appointments.store') }}" class="surface booking-form p-3 p-lg-4" data-booking-form>
            @csrf
            <div class="booking-step-header mb-3" hidden data-booking-progress>
                <div class="booking-step-track" aria-hidden="true">
                    @for ($step = 0; $step < 4; $step++) <span></span> @endfor
                </div>
                <p id="booking-progress" class="small text-secondary mb-0"></p>
            </div>
            @if ($errors->any() && ! $vehicleFormErrors)<x-form-errors />@endif
            <section class="row g-3 mb-3" data-booking-step data-step-label="{{ __('booking.choose_vehicle') }}" data-step-action="{{ __('booking.to_services') }}" aria-labelledby="booking-vehicle-heading" @if ($errors->hasAny(['shop_id', 'vehicle_id', 'service_barangay_id', 'service_street', 'service_landmark', 'latitude', 'longitude'])) data-step-error @endif>
            <div class="col-12 booking-form-section-head">
                <h2 id="booking-vehicle-heading" tabindex="-1">{{ __('booking.choose_vehicle') }}</h2>
            </div>
            <div class="col-md-6">
                <label for="shop_id" class="form-label">{{ __('ui.' . 'Service provider') }}</label>
                <select id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" name="shop_id" required>
                    <option value="">{{ __('ui.' . 'Choose a shop or mechanic') }}</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" data-provider-type="{{ $shop->provider_type }}" data-visit-pin="{{ isset($shop->mechanic_settings['radius_km']) ? 'yes' : 'no' }}" data-shop-name="{{ $shop->name }}" data-shop-area="{{ $shop->barangay?->areaLabel() }}" @selected($shopId == $shop->id)>{{ $shop->name }} · {{ $shop->providerLabel() }}</option>
                    @endforeach
                </select>
                @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="form-text" id="booking-policy" aria-live="polite"></p>
                <div class="form-text">Need to compare providers? <a href="{{ route('shops.index') }}">{{ __('ui.' . 'Browse the directory') }}</a>.</div>
            </div>
            <div class="col-md-6">
                <label for="vehicle_id" class="form-label">{{ __('ui.' . 'Vehicle') }}</label>
                <select id="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror" name="vehicle_id" required>
                    <option value="">{{ __('ui.' . 'Choose a vehicle') }}</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $selectedVehicleId) == $vehicle->id) data-vehicle-name="{{ $vehicle->make }} {{ $vehicle->model }} {{ $vehicle->variant ? '· '.$vehicle->variant : '' }} {{ $vehicle->year ? '· '.$vehicle->year : '' }}" data-plate="{{ $vehicle->plate_number }}">{{ $vehicle->plate_number }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                    @endforeach
                </select>
                @error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12" id="service-address-field" @if(! $selectedProvider?->isIndependentMechanic()) hidden @endif>
                <h2 class="h5">{{ __('ui.' . 'Where should the mechanic meet you?') }}</h2>
                <x-address-picker prefix="service" barangay-name="service_barangay_id" street-name="service_street" landmark-name="service_landmark" :selected-barangay="$bookingValue('service_barangay_id', auth()->user()->barangay_id)" :street="$bookingValue('service_street', auth()->user()->address)" :landmark="$bookingValue('service_landmark', auth()->user()->landmark)" :street-max="255" :required="$selectedProvider?->isIndependentMechanic()" />
                <div id="booking-visit-pin" class="mt-3" @unless(isset($selectedProvider?->mechanic_settings['radius_km'])) hidden @endunless>
                    <x-location-picker :label="__('mechanic.visit_pin')" :hint="__('mechanic.visit_pin_help')" :latitude="$bookingValue('latitude')" :longitude="$bookingValue('longitude')" :required="false" load-when-visible />
                </div>
            </div>
            </section>
            <section class="row g-3 mb-3" data-booking-step data-step-label="{{ __('booking.choose_services') }}" data-step-action="{{ __('booking.to_schedule') }}" aria-labelledby="booking-service-heading" @if ($errors->has('service_ids') || $errors->get('service_ids.*') !== []) data-step-error @endif>
                <div class="col-12 booking-vehicle-summary" id="booking-vehicle-summary" hidden>
                    <i class="bi bi-car-front" aria-hidden="true"></i>
                    <div><strong id="booking-vehicle-name"></strong><span class="small text-secondary d-block" id="booking-vehicle-plate"></span></div>
                    <button class="btn btn-link" type="button" id="booking-change-vehicle" aria-label="{{ __('booking.change_vehicle') }}">{{ __('booking.change') }}</button>
                </div>
                <div class="col-12 booking-form-section-head">
                    <h2 id="booking-service-heading" tabindex="-1">{{ __('booking.vehicle_needs') }}</h2>
                </div>
            <fieldset class="col-12" aria-describedby="service-help booking-service-error">
                <legend class="visually-hidden">{{ __('ui.Services') }}</legend>
                <p id="service-help" class="form-text">{{ __('booking.pick_services_help') }}</p>
                <div class="row g-2" id="booking-services">
                    @foreach ($shops as $shop)
                        @foreach ($shop->services->where('availability', true) as $offer)
                            <div class="col-12" data-service-shop="{{ $shop->id }}" @if($shopId != $shop->id) hidden @endif>
                                <label class="booking-service-choice">
                                    <input class="form-check-input mt-1" type="checkbox" name="service_ids[]" value="{{ $offer->service_id }}" data-shop-id="{{ $shop->id }}" data-price="{{ $offer->price }}" data-service-name="{{ $offer->service->name }}" data-labor-included="{{ $offer->labor_included === null ? '' : (int) $offer->labor_included }}" data-parts-included="{{ $offer->parts_included === null ? '' : (int) $offer->parts_included }}" data-scope="{{ $offer->description }}" @disabled($shopId != $shop->id) @checked($shopId == $shop->id && in_array($offer->service_id, $selectedServices))>
                                    <span class="booking-service-description">
                                        <strong>{{ $offer->service->name }}</strong>
                                        @if ($offer->description)
                                            <span class="text-secondary small">{{ $offer->description }}</span>
                                        @endif
                                        @if ($offer->labor_included !== null || $offer->parts_included !== null)
                                            <span class="text-secondary small">{{ __('booking.price_includes', [
                                                'labor' => __($offer->labor_included === null ? 'booking.unspecified' : ($offer->labor_included ? 'booking.included' : 'booking.separate')),
                                                'parts' => __($offer->parts_included === null ? 'booking.unspecified' : ($offer->parts_included ? 'booking.included' : 'booking.separate')),
                                            ]) }}</span>
                                        @endif
                                    </span>
                                    <strong class="price text-end">₱{{ number_format($offer->price, 2) }}</strong>
                                </label>
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <p class="text-danger small mt-2 mb-0" id="booking-service-error" role="status" hidden>{{ __('booking.service_required') }}</p>
                @foreach ($errors->get('service_ids') as $message)
                    <p class="text-danger small mt-2 mb-0">{{ $message }}</p>
                @endforeach
                @foreach ($errors->get('service_ids.*') as $messages)
                    @foreach ($messages as $message)
                        <p class="text-danger small mt-2 mb-0">{{ $message }}</p>
                    @endforeach
                @endforeach
                <p class="text-secondary mb-0" id="booking-no-offers" hidden>{{ __('booking.no_offers') }}</p>
            </fieldset>
            </section>
            <section class="row g-3 mb-3" data-booking-step data-step-label="{{ __('ui.Choose a visit time') }}" data-step-action="{{ __('booking.to_review') }}" aria-labelledby="booking-time-heading" @if ($errors->hasAny(['appointment_date', 'appointment_time'])) data-step-error @endif>
            <div class="col-12 booking-form-section-head">
                <h2 id="booking-time-heading" tabindex="-1">{{ __('ui.' . 'Choose a visit time') }}</h2>
                <p>{{ __('booking.manila_time') }}</p>
            </div>
            <div class="col-md-6">
                <label for="appointment_date" class="form-label">{{ __('ui.' . 'Preferred date') }}</label>
                <input id="appointment_date" class="form-control @error('appointment_date') is-invalid @enderror" type="date" name="appointment_date" min="{{ today(\App\Models\Appointment::TIMEZONE)->toDateString() }}" value="{{ $bookingValue('appointment_date') }}" required>
                @error('appointment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="appointment_time" class="form-label">{{ __('ui.' . 'Preferred time') }}</label>
                <input id="appointment_time" class="form-control @error('appointment_time') is-invalid @enderror" type="time" name="appointment_time" value="{{ $bookingValue('appointment_time') }}" aria-describedby="slot-feedback" required>
                @error('appointment_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="slot-feedback" class="form-text" aria-live="polite">{{ __('booking.slot_wait') }}</div>
                <div id="time-suggestions" class="booking-time-suggestions d-flex flex-wrap gap-2 mt-2" aria-label="{{ __('booking.slot_suggestions') }}"></div>
            </div>
            </section>
            <section class="row g-3 mb-3" data-booking-step data-step-label="{{ __('ui.Review your request') }}" aria-labelledby="booking-review-heading" @if ($errors->hasAny(['reason', 'promo_code'])) data-step-error @endif>
            <div class="col-12 booking-form-section-head">
                <h2 id="booking-review-heading" tabindex="-1">{{ __('ui.' . 'Review your request') }}</h2>
                <p>{{ __('ui.' . 'Add a short note if needed, then check the listed price before sending.') }}</p>
            </div>
            <div class="col-12">
                <label for="reason" class="form-label">{{ __('ui.' . 'Message to the provider (optional)') }}</label>
                <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="3" maxlength="2000">{{ $bookingValue('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="booking-summary" aria-live="polite">
                    <h3 class="h6 mb-2">{{ __('ui.' . 'Price and confirmation') }}</h3>
                    <p class="mb-1" id="booking-review-summary"></p>
                    <p class="mb-1" id="booking-inclusions">{{ __('booking.inclusions_empty') }}</p>
                    <p class="mb-0 text-secondary">{{ __('booking.confirmation') }}</p>
                    <p id="mobile-quote-error" class="text-danger mb-0 mt-2" role="alert" hidden></p>
                    <dl id="mobile-quote-summary" class="row gx-2 gy-1 small mb-0 mt-2" hidden></dl>
                    <input type="hidden" name="mobile_quote_total" id="mobile-quote-total">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="promo-code">{{ __('extensions.promo.booking_code') }}</label>
                <input id="promo-code" class="form-control @error('promo_code') is-invalid @enderror" name="promo_code" maxlength="32" value="{{ $bookingValue('promo_code') }}">
                @error('promo_code')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
            </div>
            </section>
            <div class="booking-action-bar">
                <div class="booking-action-totals" aria-live="polite" hidden>
                    <div><span id="booking-service-summary"></span><span class="small text-secondary d-block" id="booking-estimate-label">{{ __('booking.listed_estimate') }}</span></div>
                    <strong class="price" id="booking-estimate"></strong>
                </div>
                <div class="booking-action-controls d-flex flex-wrap align-items-center gap-2">
                    <button class="btn btn-accent" type="button" id="booking-next" hidden>{{ __('booking.continue') }}</button>
                    <button class="btn btn-accent" type="submit" id="booking-submit" @disabled($vehicles->isEmpty() || $shops->isEmpty())>{{ __('ui.' . 'Request appointment') }}</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        const shopSelect = document.querySelector('#shop_id');
        const serviceAddressField = document.querySelector('#service-address-field');
        const serviceAddressInputs = serviceAddressField.querySelectorAll('[data-address-choice], [data-address-street]');
        const visitPin = document.querySelector('#booking-visit-pin');
        const serviceChecks = [...document.querySelectorAll('#booking-services input[type="checkbox"]')];
        const selectedServices = () => serviceChecks.filter(input => input.checked && !input.disabled);
        const dateInput = document.querySelector('#appointment_date');
        const timeInput = document.querySelector('#appointment_time');
        const slotFeedback = document.querySelector('#slot-feedback');
        const timeSuggestions = document.querySelector('#time-suggestions');
        const serviceSummary = document.querySelector('#booking-service-summary');
        const estimate = document.querySelector('#booking-estimate');
        const estimateLabel = document.querySelector('#booking-estimate-label');
        const reviewSummary = document.querySelector('#booking-review-summary');
        const vehicleSelect = document.querySelector('#vehicle_id');
        const vehicleSummary = document.querySelector('#booking-vehicle-summary');
        const bookingInclusions = document.querySelector('#booking-inclusions');
        const providerSummary = document.querySelector('#booking-provider-summary');
        const providerArea = document.querySelector('#booking-provider-area');
        const defaultHeading = @js(__('ui.Book a service'));
        const priceFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        const bookingCopy = @js(trans('booking'));
        const shopPolicies = @js($shops->mapWithKeys(fn ($shop) => [$shop->id => trans_choice('extensions.appointments.policy', $shop->cancellation_notice_hours, ['count' => $shop->cancellation_notice_hours])])->all());
        const bookingShop = document.querySelector('#shop_id');
        const showPolicy = () => {
            document.querySelector('#booking-policy').textContent = shopPolicies[bookingShop.value] ?? '';
        };
        bookingShop.addEventListener('change', showPolicy);
        showPolicy();
        const bookingText = (key, values = {}) => bookingCopy[key].replace(/:([a-z]+)/g, (slot, name) => values[name] ?? slot);
        let availability = null;
        let availabilityRequest = 0;

        const updateSummary = () => {
            const selected = selectedServices();
            const total = selected.reduce((sum, input) => sum + Number(input.dataset.price), 0);
            const provider = shopSelect.selectedOptions[0];
            providerSummary.textContent = provider?.dataset.shopName ?? defaultHeading;
            providerArea.textContent = provider?.dataset.shopArea ?? '';
            providerArea.hidden = !providerArea.textContent;
            serviceSummary.textContent = bookingText(selected.length === 1 ? 'service_count_one' : 'service_count_many', { count: selected.length });
            estimate.textContent = priceFormatter.format(total);
            reviewSummary.textContent = selected.length
                ? bookingText('summary', { count: selected.length, names: selected.map(input => input.dataset.serviceName).join(', '), total: priceFormatter.format(total) })
                : bookingText('summary_empty');
            bookingInclusions.textContent = selected.length
                ? selected.map(input => bookingText('inclusions', {
                    name: input.dataset.serviceName,
                    labor: bookingText(input.dataset.laborIncluded === '' ? 'unspecified' : (input.dataset.laborIncluded === '1' ? 'included' : 'separate')),
                    parts: bookingText(input.dataset.partsIncluded === '' ? 'unspecified' : (input.dataset.partsIncluded === '1' ? 'included' : 'separate')),
                })).join(' | ')
                : bookingText('inclusions_empty');
        };
        const asMinutes = (time) => {
            const [hour, minute] = time.split(':').map(Number);
            return hour * 60 + minute;
        };
        const fitsDuration = (time) => {
            const end = asMinutes(time) + availability.duration_minutes;
            return availability.is_open_day && end <= 1440
                && (!availability.opening_time || asMinutes(time) >= asMinutes(availability.opening_time))
                && (!availability.closing_time || end <= asMinutes(availability.closing_time))
                && !availability.schedule_blocks.some(block => asMinutes(time) < asMinutes(block.end) && end > asMinutes(block.start));
        };
        const hasSpace = (time) => {
            const start = asMinutes(time);
            const end = start + availability.duration_minutes;
            const changes = new Map();
            for (const interval of availability.booked_intervals) {
                if (interval.start >= end || interval.end <= start) continue;
                const overlapStart = Math.max(start, interval.start);
                const overlapEnd = Math.min(end, interval.end);
                changes.set(overlapStart, (changes.get(overlapStart) || 0) + 1);
                changes.set(overlapEnd, (changes.get(overlapEnd) || 0) - 1);
            }
            let occupied = 0;
            for (const [, change] of [...changes].sort((a, b) => a[0] - b[0])) {
                occupied += change;
                if (occupied >= availability.capacity) return false;
            }
            return true;
        };

        const checkTime = () => {
            const time = timeInput.value;
            slotFeedback.classList.remove('text-danger', 'text-success');
            if (!shopSelect.value || !selectedServices().length || !dateInput.value) {
                slotFeedback.textContent = bookingText('slot_wait');
            } else if (!availability) {
                slotFeedback.textContent = bookingText('slot_check');
            } else if (time && Date.parse(`${dateInput.value}T${time}:00+08:00`) <= Date.now()) {
                slotFeedback.textContent = bookingText('slot_past');
                slotFeedback.classList.add('text-danger');
            } else if (time && !fitsDuration(time)) {
                slotFeedback.textContent = availability.is_open_day ? bookingText('slot_blocked', { minutes: availability.duration_minutes }) : bookingText('slot_closed');
                slotFeedback.classList.add('text-danger');
            } else if (time && !hasSpace(time)) {
                slotFeedback.textContent = bookingText('slot_full');
                slotFeedback.classList.add('text-danger');
            } else if (time) {
                slotFeedback.textContent = bookingText('slot_clear');
                slotFeedback.classList.add('text-success');
            } else {
                const hours = availability.opening_time && availability.closing_time
                    ? bookingText('slot_hours', { open: availability.opening_time, close: availability.closing_time })
                    : bookingText('slot_no_hours');
                slotFeedback.textContent = hours + ' ' + bookingText('slot_duration', { minutes: availability.duration_minutes, capacity: availability.capacity });
            }
        };

        const showSuggestions = () => {
            timeSuggestions.replaceChildren();
            if (!availability?.opening_time || !availability?.closing_time) return;

            const [openingHour, openingMinute] = availability.opening_time.split(':').map(Number);
            const [closingHour, closingMinute] = availability.closing_time.split(':').map(Number);
            let start = openingHour * 60 + openingMinute;
            const end = closingHour * 60 + closingMinute;
            while (start < end && timeSuggestions.childElementCount < 8) {
                const time = `${String(Math.floor(start / 60)).padStart(2, '0')}:${String(start % 60).padStart(2, '0')}`;
                if (fitsDuration(time) && hasSpace(time) && Date.parse(`${dateInput.value}T${time}:00+08:00`) > Date.now()) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-secondary';
                    button.textContent = time;
                    button.setAttribute('aria-label', bookingText('slot_choose', { time }));
                    button.addEventListener('click', () => {
                        timeInput.value = time;
                        checkTime();
                    });
                    timeSuggestions.append(button);
                }
                start += 60;
            }
        };

        const refreshAvailability = async () => {
            const currentRequest = ++availabilityRequest;
            availability = null;
            timeSuggestions.replaceChildren();
            checkTime();
            if (!shopSelect.value || !selectedServices().length || !dateInput.value) return;

            const url = new URL(@json(route('customer.appointments.availability')));
            url.searchParams.set('shop_id', shopSelect.value);
            url.searchParams.set('date', dateInput.value);
            selectedServices().forEach(input => url.searchParams.append('service_ids[]', input.value));
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Availability unavailable');
                const result = await response.json();
                if (currentRequest !== availabilityRequest) return;
                availability = result;
                showSuggestions();
                checkTime();
            } catch {
                if (currentRequest !== availabilityRequest) return;
                slotFeedback.textContent = bookingText('slot_unavailable');
            }
        };
        const syncServices = () => {
            const isMechanic = shopSelect.selectedOptions[0]?.dataset.providerType === 'independent_mechanic';
            serviceAddressField.hidden = !isMechanic;
            serviceAddressInputs.forEach(input => { input.required = isMechanic; });
            visitPin.hidden = shopSelect.selectedOptions[0]?.dataset.visitPin !== 'yes';
            visitPin.querySelectorAll('input').forEach(input => {
                input.required = !visitPin.hidden;
                input.disabled = visitPin.hidden;
            });
            serviceChecks.forEach(input => {
                const unavailable = input.dataset.shopId !== shopSelect.value;
                input.closest('[data-service-shop]').hidden = unavailable;
                input.disabled = unavailable;
                if (unavailable) input.checked = false;
            });
            document.querySelector('#booking-no-offers').hidden = serviceChecks.some(input => !input.disabled);
            updateSummary();
            refreshAvailability();
        };
        shopSelect.addEventListener('change', syncServices);
        serviceChecks.forEach(input => input.addEventListener('change', () => { updateSummary(); refreshAvailability(); }));
        dateInput.addEventListener('change', refreshAvailability);
        timeInput.addEventListener('change', checkTime);
        syncServices();

        const bookingForm = document.querySelector('[data-booking-form]');
        const steps = [...bookingForm.querySelectorAll('[data-booking-step]')];
        const progress = document.querySelector('#booking-progress');
        const next = document.querySelector('#booking-next');
        const previous = document.querySelector('#booking-prev');
        const submit = document.querySelector('#booking-submit');
        const exit = document.querySelector('#booking-exit');
        const serviceError = document.querySelector('#booking-service-error');
        const vehicleForm = document.querySelector('[data-booking-vehicle-form]');
        const lastStep = steps.length - 1;
        let currentStep = 0;

        const showStep = (index, focus = true) => {
            currentStep = index;
            vehicleForm.closest('details').hidden = index !== 0;
            steps.forEach((step, position) => { step.hidden = position !== index; });
            progress.textContent = bookingText('progress', { step: index + 1, total: steps.length }) + ' · ' + steps[index].dataset.stepLabel;
            document.querySelectorAll('.booking-step-track span').forEach((segment, position) => {
                segment.classList.toggle('is-complete', position <= index);
            });
            document.querySelector('.booking-action-totals').hidden = index === 0;
            next.textContent = steps[index].dataset.stepAction ?? '';
            const vehicle = vehicleSelect.selectedOptions[0];
            vehicleSummary.hidden = !vehicle?.value;
            document.querySelector('#booking-vehicle-name').textContent = vehicle?.dataset.vehicleName ?? '';
            document.querySelector('#booking-vehicle-plate').textContent = vehicle?.dataset.plate ?? '';
            next.hidden = index === lastStep;
            submit.hidden = index !== lastStep;
            previous.hidden = index === 0;
            exit.hidden = index !== 0;
            if (focus) steps[index].querySelector('h2').focus();
        };

        const advance = () => {
            const invalid = [...steps[currentStep].querySelectorAll('input, select, textarea')]
                .find(field => field.willValidate && !field.validity.valid);
            if (invalid) {
                invalid.reportValidity();
                return;
            }
            if (currentStep === 1 && !selectedServices().length) {
                serviceError.hidden = false;
                serviceChecks.find(field => !field.disabled)?.focus();
                return;
            }
            if (currentStep === 2 && slotFeedback.classList.contains('text-danger')) {
                timeInput.focus();
                return;
            }
            showStep(currentStep + 1);
        };
        serviceChecks.forEach(field => field.addEventListener('change', () => { serviceError.hidden = true; }));
        shopSelect.addEventListener('change', () => { serviceError.hidden = true; });
        next.addEventListener('click', advance);
        previous.addEventListener('click', () => showStep(currentStep - 1));
        document.querySelector('#booking-change-vehicle').addEventListener('click', () => {
            showStep(0, false);
            vehicleSelect.focus();
        });
        bookingForm.addEventListener('invalid', event => {
            const index = steps.indexOf(event.target.closest('[data-booking-step]'));
            if (index >= 0 && index !== currentStep) showStep(index, false);
        }, true);
        const mobileQuoteTotal = document.querySelector('#mobile-quote-total');
        const mobileQuoteSummary = document.querySelector('#mobile-quote-summary');
        const mobileQuoteError = document.querySelector('#mobile-quote-error');
        const mechanicCopy = @js(array_replace(trans('mechanic', [], 'en'), trans('mechanic')));
        const bookingSubmitLabel = submit.textContent.trim();
        const isMobileBooking = () => shopSelect.selectedOptions[0]?.dataset.providerType === 'independent_mechanic';
        let quoteVersion = 0;
        const resetMobileQuote = () => {
            quoteVersion++;
            if (mobileQuoteTotal.value !== '') {
                estimate.textContent = priceFormatter.format(selectedServices().reduce((sum, input) => sum + Number(input.dataset.price), 0));
                estimateLabel.textContent = bookingCopy.listed_estimate;
            }
            mobileQuoteTotal.value = '';
            mobileQuoteSummary.hidden = true;
            mobileQuoteError.hidden = true;
            submit.textContent = isMobileBooking() ? mechanicCopy.check_quote : bookingSubmitLabel;
        };
        bookingForm.addEventListener('input', resetMobileQuote);
        bookingForm.addEventListener('change', resetMobileQuote);
        bookingForm.addEventListener('submit', async event => {
            if (currentStep !== lastStep) {
                event.preventDefault();
                advance();
                return;
            }
            if (!isMobileBooking() || mobileQuoteTotal.value !== '') return;
            event.preventDefault();
            const version = ++quoteVersion;
            submit.disabled = true;
            mobileQuoteError.hidden = true;
            try {
                const response = await fetch(@js(route('customer.appointments.mobile-quote')), {
                    method: 'POST',
                    body: new FormData(bookingForm),
                    headers: { Accept: 'application/json' },
                });
                const quote = await response.json();
                if (version !== quoteVersion) return;
                if (!response.ok) throw new Error(response.status === 422 ? quote.message : mechanicCopy.quote_unavailable);
                mobileQuoteSummary.replaceChildren();
                for (const [key, cents] of Object.entries(quote)) {
                    if (cents === 0 && !['service', 'total'].includes(key)) continue;
                    const label = document.createElement('dt');
                    label.className = 'col-7 text-break';
                    label.textContent = mechanicCopy[key];
                    const amount = document.createElement('dd');
                    amount.className = 'col-5 mb-0 text-end';
                    amount.textContent = priceFormatter.format(cents / 100);
                    mobileQuoteSummary.append(label, amount);
                }
                mobileQuoteSummary.hidden = false;
                mobileQuoteTotal.value = quote.total;
                estimate.textContent = priceFormatter.format(quote.total / 100);
                estimateLabel.textContent = mechanicCopy.total;
                submit.textContent = bookingSubmitLabel;
                mobileQuoteSummary.scrollIntoView({ block: 'center' });
            } catch (error) {
                if (version !== quoteVersion) return;
                mobileQuoteError.textContent = error instanceof SyntaxError ? mechanicCopy.quote_unavailable : error.message;
                mobileQuoteError.hidden = false;
            } finally {
                submit.disabled = false;
            }
        });
        resetMobileQuote();
        vehicleForm.addEventListener('formdata', event => {
            for (const [name, value] of new FormData(bookingForm)) {
                if (!['_token', 'vehicle_id'].includes(name)) {
                    const multiple = name.endsWith('[]');
                    const key = multiple ? name.slice(0, -2) : name;
                    event.formData.append('booking_draft[' + key + ']' + (multiple ? '[]' : ''), value);
                }
            }
        });
        const errorStep = steps.findIndex(step => step.hasAttribute('data-step-error'));
        progress.closest('[data-booking-progress]').hidden = false;
        showStep(errorStep < 0 ? 0 : errorStep, false);
        if (errorStep >= 0) {
            const field = steps[errorStep].querySelector('.is-invalid, [aria-invalid="true"]');
            (field ?? steps[errorStep].querySelector('h2')).focus();
        }
    </script>
</x-layouts.app>
