<x-layouts.app title="Book a service">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>Book a service</h1>
            <p>Choose a verified shop or mobile mechanic, then request a time. The provider will confirm your visit.</p>
        </div>

        @if ($vehicles->isEmpty())
            <div class="alert alert-info" role="status">Add a vehicle before booking. Open “Add a vehicle here” below to continue.</div>
        @elseif ($shops->isEmpty())
            <div class="alert alert-info" role="status">No verified providers are available right now. <a href="{{ route('shops.index') }}" class="alert-link">Browse the directory</a></div>
        @endif

        <details class="surface p-4 mb-4">
            <summary class="fw-semibold">Add a vehicle here</summary>
            <form method="POST" action="{{ route('customer.vehicles.store') }}" class="row g-3 mt-2">
                @csrf
                <input type="hidden" name="return_to_booking" value="1">
                <div class="col-md-4"><label for="booking-plate" class="form-label">Plate number</label><input id="booking-plate" class="form-control" name="plate_number" maxlength="30" required></div>
                <div class="col-md-4"><label for="booking-color" class="form-label">Color (optional)</label><input id="booking-color" class="form-control" name="color" maxlength="50"></div>
                <div class="col-md-4"><label for="booking-mileage" class="form-label">Mileage (optional)</label><input id="booking-mileage" class="form-control" type="number" min="0" name="current_mileage"></div>
                <div class="col-12"><x-vehicle-picker :vehicle="$newVehicle" /></div>
                <div class="col-12"><button class="btn btn-outline-primary">Save vehicle and continue booking</button></div>
            </form>
        </details>

        <form method="POST" action="{{ route('customer.appointments.store') }}" class="surface booking-form p-4 p-lg-5 row g-3">
            @csrf
            <div class="col-12 booking-form-section-head">
                <h2>Choose your service</h2>
                <p>Pick a provider, the work you need, and the vehicle being serviced.</p>
            </div>
            <div class="col-md-6">
                <label for="shop_id" class="form-label">Service provider</label>
                <select id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" name="shop_id" required>
                    <option value="">Choose a shop or mechanic</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" data-provider-type="{{ $shop->provider_type }}" @selected(old('shop_id', $selectedShopId) == $shop->id)>{{ $shop->name }} · {{ $shop->providerLabel() }}</option>
                    @endforeach
                </select>
                @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Need to compare providers? <a href="{{ route('shops.index') }}">Browse the directory</a>.</div>
            </div>
            <fieldset class="col-12" aria-describedby="service-help">
                <legend class="h5">Services</legend>
                <p id="service-help" class="form-text">Choose one or more services. Prices and time are combined below.</p>
                <div class="row g-2" id="booking-services">
                    @foreach ($shops as $shop)
                        @foreach ($shop->services->where('availability', true) as $offer)
                            <div class="col-sm-6" data-service-shop="{{ $shop->id }}" @if(old('shop_id', $selectedShopId) != $shop->id) hidden @endif>
                                <label class="form-check surface p-3 h-100 d-flex gap-2">
                                    <input class="form-check-input mt-1" type="checkbox" name="service_ids[]" value="{{ $offer->service_id }}" data-shop-id="{{ $shop->id }}" data-price="{{ $offer->price }}" data-service-name="{{ $offer->service->name }}" data-labor-included="{{ $offer->labor_included === null ? '' : (int) $offer->labor_included }}" data-parts-included="{{ $offer->parts_included === null ? '' : (int) $offer->parts_included }}" data-scope="{{ $offer->description }}" @checked(in_array($offer->service_id, old('service_ids', [])))>
                                    <span>{{ $offer->service->name }} <span class="text-secondary">· PHP {{ number_format($offer->price, 2) }}</span></span>
                                </label>
                            </div>
                        @endforeach
                    @endforeach
                </div>
                @error('service_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @error('service_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </fieldset>
            <div class="col-md-6">
                <label for="vehicle_id" class="form-label">Vehicle</label>
                <select id="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror" name="vehicle_id" required>
                    <option value="">Choose a vehicle</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $selectedVehicleId) == $vehicle->id)>{{ $vehicle->plate_number }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
                    @endforeach
                </select>
                @error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @php($selectedProvider = $shops->firstWhere('id', old('shop_id', $selectedShopId)))
            <div class="col-12" id="service-address-field" @if(! $selectedProvider?->isIndependentMechanic()) hidden @endif>
                <h2 class="h5">Where should the mechanic meet you?</h2>
                <x-address-picker prefix="service" barangay-name="service_barangay_id" street-name="service_street" landmark-name="service_landmark" :selected-barangay="auth()->user()->barangay_id" :street="auth()->user()->address" :landmark="auth()->user()->landmark" :street-max="255" :required="$selectedProvider?->isIndependentMechanic()" />
            </div>
            <div class="col-12 booking-form-section-head">
                <h2>Choose a visit time</h2>
                <p>Select a date and preferred start time. Available times appear after you choose a provider and service.</p>
            </div>
            <div class="col-md-6">
                <label for="appointment_date" class="form-label">Preferred date</label>
                <input id="appointment_date" class="form-control @error('appointment_date') is-invalid @enderror" type="date" name="appointment_date" min="{{ today()->toDateString() }}" value="{{ old('appointment_date') }}" required>
                @error('appointment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="appointment_time" class="form-label">Preferred time</label>
                <input id="appointment_time" class="form-control @error('appointment_time') is-invalid @enderror" type="time" name="appointment_time" value="{{ old('appointment_time') }}" aria-describedby="slot-feedback" required>
                @error('appointment_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="slot-feedback" class="form-text" aria-live="polite">Choose a shop, service, and date to check available times.</div>
                <div id="time-suggestions" class="booking-time-suggestions d-flex flex-wrap gap-2 mt-2" aria-label="Suggested available start times"></div>
            </div>
            <div class="col-12 booking-form-section-head">
                <h2>Review your request</h2>
                <p>Add a short note if needed, then check the listed price before sending.</p>
            </div>
            <div class="col-12">
                <label for="reason" class="form-label">Message to the provider (optional)</label>
                <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="3" placeholder="For example, a noise when braking (optional).">{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="booking-summary" aria-live="polite">
                    <h3 class="h6 mb-2">Price and confirmation</h3>
                    <p class="mb-1" id="booking-service-summary">Choose a shop and service to see the listed price.</p>
                    <p class="mb-1" id="booking-inclusions">Ask the shop what labor and parts are included if they have not specified them.</p>
                    <p class="mb-0 text-secondary">This is the listed service price. Extra repair work needs a separate estimate and your approval. Your preferred time is not confirmed until the shop accepts the request.</p>
                </div>
            </div>
            <div class="col-12 booking-form-actions d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-primary" @disabled($vehicles->isEmpty() || $shops->isEmpty())>Request appointment</button>
                <a class="btn btn-link" href="{{ route('customer.appointments.index') }}">Back to appointments</a>
            </div>
        </form>
    </div>

    <script>
        const shopSelect = document.querySelector('#shop_id');
        const serviceAddressField = document.querySelector('#service-address-field');
        const serviceAddressInputs = serviceAddressField.querySelectorAll('[data-address-choice], [data-address-street]');
        const serviceChecks = [...document.querySelectorAll('#booking-services input[type="checkbox"]')];
        const selectedServices = () => serviceChecks.filter(input => input.checked && !input.disabled);
        const dateInput = document.querySelector('#appointment_date');
        const timeInput = document.querySelector('#appointment_time');
        const slotFeedback = document.querySelector('#slot-feedback');
        const timeSuggestions = document.querySelector('#time-suggestions');
        const serviceSummary = document.querySelector('#booking-service-summary');
        const bookingInclusions = document.querySelector('#booking-inclusions');
        const priceFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        let availability = null;
        let availabilityRequest = 0;

        const updateSummary = () => {
            const selected = selectedServices();
            const total = selected.reduce((sum, input) => sum + Number(input.dataset.price), 0);
            serviceSummary.textContent = selected.length
                ? `${selected.length} service${selected.length === 1 ? '' : 's'}: ${selected.map(input => input.dataset.serviceName).join(', ')}. Listed total ${priceFormatter.format(total)}.`
                : 'Choose a provider and at least one service to see the listed total.';
            bookingInclusions.textContent = selected.length
                ? selected.map(input => `${input.dataset.serviceName}: labor ${input.dataset.laborIncluded === '1' ? 'included' : 'quoted separately'}, parts ${input.dataset.partsIncluded === '1' ? 'included' : 'quoted separately'}`).join(' | ')
                : 'Ask the shop what labor and parts are included if they have not specified them.';
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
                slotFeedback.textContent = 'Choose a shop, service, and date to check available times.';
            } else if (!availability) {
                slotFeedback.textContent = 'Checking the shop schedule…';
            } else if (time && new Date(`${dateInput.value}T${time}:00`).getTime() <= Date.now()) {
                slotFeedback.textContent = 'Choose a future appointment time.';
                slotFeedback.classList.add('text-danger');
            } else if (time && !fitsDuration(time)) {
                slotFeedback.textContent = availability.is_open_day ? `This time is outside shop hours or blocked. Allow ${availability.duration_minutes} minutes for this service.` : 'The shop is closed on this day.';
                slotFeedback.classList.add('text-danger');
            } else if (time && !hasSpace(time)) {
                slotFeedback.textContent = 'The shop is fully booked during this service. Choose another time.';
                slotFeedback.classList.add('text-danger');
            } else if (time) {
                slotFeedback.textContent = 'No conflicting booking is shown. The shop still needs to confirm your request.';
                slotFeedback.classList.add('text-success');
            } else {
                const hours = availability.opening_time && availability.closing_time
                    ? `Shop hours: ${availability.opening_time}–${availability.closing_time}. `
                    : 'Shop hours are not listed. ';
                slotFeedback.textContent = `${hours}Allow ${availability.duration_minutes} minutes for this service. Up to ${availability.capacity} appointment(s) can run together.`;
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
                if (fitsDuration(time) && hasSpace(time) && new Date(`${dateInput.value}T${time}:00`).getTime() > Date.now()) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-secondary';
                    button.textContent = time;
                    button.setAttribute('aria-label', `Choose ${time} as preferred start time`);
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
                slotFeedback.textContent = 'Schedule preview is unavailable. You can still request a time; the shop will confirm it.';
            }
        };
        const syncServices = () => {
            const isMechanic = shopSelect.selectedOptions[0]?.dataset.providerType === 'independent_mechanic';
            serviceAddressField.hidden = !isMechanic;
            serviceAddressInputs.forEach(input => { input.required = isMechanic; });
            serviceChecks.forEach(input => {
                const unavailable = input.dataset.shopId !== shopSelect.value;
                input.closest('[data-service-shop]').hidden = unavailable;
                input.disabled = unavailable;
                if (unavailable) input.checked = false;
            });
            updateSummary();
            refreshAvailability();
        };
        shopSelect.addEventListener('change', syncServices);
        serviceChecks.forEach(input => input.addEventListener('change', () => { updateSummary(); refreshAvailability(); }));
        dateInput.addEventListener('change', refreshAvailability);
        timeInput.addEventListener('change', checkTime);
        syncServices();
    </script>
</x-layouts.app>
