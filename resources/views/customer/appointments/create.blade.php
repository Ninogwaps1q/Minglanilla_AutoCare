<x-layouts.app title="Book an appointment">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>Book an appointment</h1>
            <p>Select a verified shop, an available service, and the vehicle that needs attention.</p>
        </div>

        <form method="POST" action="{{ route('customer.appointments.store') }}" class="surface p-4 p-lg-5 row g-3">
            @csrf
            <div class="col-md-6">
                <label for="shop_id" class="form-label">Service shop</label>
                <select id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" name="shop_id" required>
                    <option value="">Choose a shop</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
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
                            <option value="{{ $offer->service_id }}" data-shop-id="{{ $shop->id }}" @selected(old('service_id') == $offer->service_id && old('shop_id') == $shop->id)>{{ $offer->service->name }} — {{ $shop->name }}</option>
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
                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }} — {{ $vehicle->make }} {{ $vehicle->model }}</option>
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
                <input id="appointment_time" class="form-control @error('appointment_time') is-invalid @enderror" type="time" name="appointment_time" value="{{ old('appointment_time') }}" required>
                @error('appointment_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="reason" class="form-label">What does your vehicle need?</label>
                <textarea id="reason" class="form-control @error('reason') is-invalid @enderror" name="reason" rows="4" placeholder="Describe the concern or request (optional).">{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Request appointment</button>
                <a class="btn btn-link" href="{{ route('customer.appointments.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const shopSelect = document.querySelector('#shop_id');
        const serviceSelect = document.querySelector('#service_id');
        const syncServices = () => {
            [...serviceSelect.options].forEach(option => {
                if (!option.dataset.shopId) return;
                option.hidden = option.dataset.shopId !== shopSelect.value;
                option.disabled = option.hidden;
            });
            if (serviceSelect.selectedOptions[0]?.disabled) serviceSelect.value = '';
        };
        shopSelect.addEventListener('change', syncServices);
        syncServices();
    </script>
</x-layouts.app>
