<x-layouts.app title="Book an appointment">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>Book an appointment</h1>
            <p>Choose a verified shop and service, then request a time. The shop will confirm your visit.</p>
        </div>

        @if ($vehicles->isEmpty())
            <div class="alert alert-info" role="status">Add a vehicle before booking. <a href="{{ route('customer.vehicles.create') }}" class="alert-link">Add my vehicle</a></div>
        @elseif ($shops->isEmpty())
            <div class="alert alert-info" role="status">No verified shops are available right now. <a href="{{ route('shops.index') }}" class="alert-link">Browse the directory</a></div>
        @endif

        <form method="POST" action="{{ route('customer.appointments.store') }}" class="surface p-4 p-lg-5 row g-3">
            @csrf
            <div class="col-md-6">
                <label for="shop_id" class="form-label">Service shop</label>
                <select id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" name="shop_id" required>
                    <option value="">Choose a shop</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected(old('shop_id', $selectedShopId) == $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="service_id" class="form-label">Service</label>
                <select id="service_id" class="form-select @error('service_id') is-invalid @enderror" name="service_id" required>
                    <option value="">Choose a service</option>
                    @foreach ($shops as $shop)
                        @foreach ($shop->services->where('availability', true) as $offer)
                            <option value="{{ $offer->service_id }}" data-shop-id="{{ $shop->id }}" data-price="{{ $offer->price }}" data-service-name="{{ $offer->service->name }}" @selected(old('service_id') == $offer->service_id && old('shop_id') == $shop->id)>{{ $offer->service->name }} — PHP {{ number_format($offer->price, 2) }}</option>
                        @endforeach
                    @endforeach
                </select>
                @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
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
            <div class="col-md-3">
                <label for="appointment_date" class="form-label">Preferred date</label>
                <input id="appointment_date" class="form-control @error('appointment_date') is-invalid @enderror" type="date" name="appointment_date" min="{{ today()->toDateString() }}" value="{{ old('appointment_date') }}" required>
                @error('appointment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="appointment_time" class="form-label">Preferred time</label>
                <input id="appointment_time" class="form-control @error('appointment_time') is-invalid @enderror" type="time" name="appointment_time" value="{{ old('appointment_time') }}" aria-describedby="slot-feedback" required>
                @error('appointment_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div id="slot-feedback" class="form-text" aria-live="polite">Choose a shop and date to check its hours and booked times.</div>
                <div id="time-suggestions" class="d-flex flex-wrap gap-2 mt-2" aria-label="Suggested unbooked start times"></div>
            </div>
            <div class="col-12">
                <label for="reason" class="form-label">What does your vehicle need?</label>
                <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="4" placeholder="Describe the concern or request (optional).">{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="booking-summary" aria-live="polite">
                    <h2 class="h5 mb-2">Before you request</h2>
                    <p class="mb-1" id="booking-service-summary">Choose a shop and service to see the listed price.</p>
                    <p class="mb-0 text-secondary">This is the listed service price. Extra repair work needs a separate estimate and your approval. Your preferred time is not confirmed until the shop accepts the request.</p>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" @disabled($vehicles->isEmpty() || $shops->isEmpty())>Request appointment</button>
                <a class="btn btn-link" href="{{ route('customer.appointments.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const shopSelect = document.querySelector('#shop_id');
        const serviceSelect = document.querySelector('#service_id');
        const dateInput = document.querySelector('#appointment_date');
        const timeInput = document.querySelector('#appointment_time');
        const slotFeedback = document.querySelector('#slot-feedback');
        const timeSuggestions = document.querySelector('#time-suggestions');
        const serviceSummary = document.querySelector('#booking-service-summary');
        const priceFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        let availability = null;
        let availabilityRequest = 0;

        const updateSummary = () => {
            const selected = serviceSelect.selectedOptions[0];
            serviceSummary.textContent = selected?.dataset.price
                ? `${selected.dataset.serviceName} at ${priceFormatter.format(Number(selected.dataset.price))}.`
                : 'Choose a shop and service to see the listed price.';
        };

        const checkTime = () => {
            const time = timeInput.value;
            slotFeedback.classList.remove('text-danger', 'text-success');
            if (!shopSelect.value || !dateInput.value) {
                slotFeedback.textContent = 'Choose a shop and date to check its hours and booked times.';
            } else if (!availability) {
                slotFeedback.textContent = 'Checking the shop schedule…';
            } else if (time && availability.opening_time && time < availability.opening_time) {
                slotFeedback.textContent = `This shop opens at ${availability.opening_time}. Choose a later time.`;
                slotFeedback.classList.add('text-danger');
            } else if (time && availability.closing_time && time >= availability.closing_time) {
                slotFeedback.textContent = `This shop closes at ${availability.closing_time}. Choose an earlier time.`;
                slotFeedback.classList.add('text-danger');
            } else if (time && availability.taken_times.includes(time)) {
                slotFeedback.textContent = 'That time is already requested or booked. Choose another time.';
                slotFeedback.classList.add('text-danger');
            } else if (time) {
                slotFeedback.textContent = 'No conflicting booking is shown. The shop still needs to confirm your request.';
                slotFeedback.classList.add('text-success');
            } else {
                const hours = availability.opening_time && availability.closing_time
                    ? `Shop hours: ${availability.opening_time}–${availability.closing_time}. `
                    : 'Shop hours are not listed. ';
                slotFeedback.textContent = `${hours}${availability.taken_times.length} time(s) already requested or booked for this date.`;
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
                if (!availability.taken_times.includes(time)) {
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
            if (!shopSelect.value || !dateInput.value) return;

            const url = new URL(@json(route('customer.appointments.availability')));
            url.searchParams.set('shop_id', shopSelect.value);
            url.searchParams.set('date', dateInput.value);
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
            [...serviceSelect.options].forEach(option => {
                if (!option.dataset.shopId) return;
                option.hidden = option.dataset.shopId !== shopSelect.value;
                option.disabled = option.hidden;
            });
            if (serviceSelect.selectedOptions[0]?.disabled) serviceSelect.value = '';
            updateSummary();
            refreshAvailability();
        };
        shopSelect.addEventListener('change', syncServices);
        serviceSelect.addEventListener('change', updateSummary);
        dateInput.addEventListener('change', refreshAvailability);
        timeInput.addEventListener('change', checkTime);
        syncServices();
    </script>
</x-layouts.app>
