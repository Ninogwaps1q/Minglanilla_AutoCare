<x-layouts.app :title="$vehicle->exists ? 'Edit vehicle' : 'Add vehicle'">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>{{ $vehicle->exists ? 'Edit vehicle' : 'Add vehicle' }}</h1>
            <p>Enter the details used for booking, service records, and maintenance tracking.</p>
        </div>
        <form method="POST" enctype="multipart/form-data"
            action="{{ $vehicle->exists ? route('customer.vehicles.update', $vehicle) : route('customer.vehicles.store') }}"
            class="surface p-4 p-lg-5 row g-3">@csrf @if ($vehicle->exists)@method('PUT')@endif
            <div class="col-md-6"><label for="plate_number" class="form-label">Plate number</label><input
                    id="plate_number" class="form-control @error('plate_number') is-invalid @enderror"
                    name="plate_number" value="{{ old('plate_number', $vehicle->plate_number) }}"
                    required>@error('plate_number')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label for="current_mileage" class="form-label">Current mileage (optional)</label><input
                    id="current_mileage" class="form-control @error('current_mileage') is-invalid @enderror"
                    type="number" min="0" name="current_mileage"
                    value="{{ old('current_mileage', $vehicle->current_mileage) }}">@error('current_mileage')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><x-vehicle-picker :vehicle="$vehicle" /></div>
            <div class="col-md-4"><label for="color" class="form-label">Color</label><input id="color"
                    class="form-control" name="color" value="{{ old('color', $vehicle->color) }}"></div>
            <div class="col-12"><x-uploaded-image id="vehicle-photo-preview" class="vehicle-form-preview" :path="$vehicle->photo" :alt="($vehicle->plate_number ?: 'Your').' vehicle photo'" fallback-icon="car-front" /><p class="small text-secondary mt-2 mb-0">{{ $vehicle->photo ? 'Current photo shown. A new selection previews here until you save.' : 'Choose a photo to preview it before saving.' }}</p></div>
            <div class="col-12"><label for="photo" class="form-label">Vehicle photo</label><input id="photo"
                    class="form-control @error('photo') is-invalid @enderror" type="file" name="photo"
                    accept="image/png,image/jpeg,image/webp" aria-describedby="vehicle-photo-help" data-image-preview-target="vehicle-photo-preview" data-image-max-size="2097152">@error('photo')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                <small id="vehicle-photo-help" class="form-text">JPG, PNG, or WebP up to 2 MB. A new photo replaces the current one.</small>
            </div>
            <div class="col-12"><button class="btn btn-primary">Save vehicle</button><a class="btn btn-link"
                    href="{{ route('customer.vehicles.index') }}">Cancel</a></div>
        </form>
    </div>
</x-layouts.app>
