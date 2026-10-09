<x-layouts.app title="Register your shop">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>Register your service shop</h1>
            <p>Registrations remain pending until an administrator verifies the business.</p>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('shop.register.store') }}" class="surface p-4 row g-3">@csrf <div
                class="col-md-6"><label class="form-label">Shop name</label><input class="form-control" name="name"
                    value="{{ old('name') }}" required></div>
            <div class="col-md-6"><label class="form-label">Business permit number</label><input class="form-control"
                    name="business_permit_number" value="{{ old('business_permit_number') }}"></div>
            <div class="col-md-6"><label class="form-label">Contact number</label><input class="form-control"
                    name="phone" value="{{ old('phone') }}" required></div>
            <div class="col-md-6"><label class="form-label">Business email</label><input class="form-control"
                    name="email" type="email" value="{{ old('email') }}" required></div>
            <div class="col-12"><label class="form-label">Street address</label><input class="form-control"
                    name="address" value="{{ old('address') }}" required></div>
            <div class="col-md-6"><label class="form-label">Barangay</label><select class="form-select"
                    name="barangay_id" required>
                    <option value="">Select barangay</option>@foreach($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected(old('barangay_id') == $barangay->id)>
                    {{ $barangay->name }}</option>@endforeach
                </select></div>
            <div class="col-12"><label class="form-label">Business description</label><textarea class="form-control"
                    name="description" rows="4">{{ old('description') }}</textarea></div><x-location-picker />
            <div class="col-md-6"><label for="logo" class="form-label">Shop logo</label><input id="logo" class="form-control @error('logo') is-invalid @enderror" type="file" name="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help">@error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="logo-help" class="form-text">Square images work best. Maximum 2 MB.</small></div>
            <div class="col-md-6"><label for="cover_photo" class="form-label">Shop cover photo</label><input id="cover_photo" class="form-control @error('cover_photo') is-invalid @enderror" type="file" name="cover_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="cover-help">@error('cover_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="cover-help" class="form-text">Use a wide storefront or workshop photo. Maximum 4 MB.</small></div>
            <fieldset class="col-12">
                <legend class="h5 mb-1">Services offered</legend>
                <p class="text-secondary small mb-3">Select all services your shop currently provides. You can set their prices and availability after registration.</p>
                <div class="row g-2">
                    @forelse ($services as $service)
                        <div class="col-md-6 col-xl-4">
                            <label class="form-check surface p-3 h-100 d-flex align-items-start gap-2">
                                <input class="form-check-input mt-1" type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids', [])))>
                                <span>
                                    <span class="d-block fw-semibold">{{ $service->name }}</span>
                                    <span class="d-block text-secondary small">{{ $service->category?->name }}@if ($service->default_price !== null) · ₱{{ number_format($service->default_price, 2) }}@endif</span>
                                </span>
                            </label>
                        </div>
                    @empty
                        <div class="col-12 text-secondary">No services are available in the catalog yet. You can add services after registering.</div>
                    @endforelse
                </div>
                @error('service_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @error('service_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </fieldset>
            <div class="col-12"><button class="btn btn-primary">Submit registration</button></div>
        </form>
    </div>
</x-layouts.app>
