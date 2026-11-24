<x-layouts.app :title="auth()->user()->role === 'mechanic' ? 'Register as a mechanic' : 'Register your shop'">
    @php($isMechanic = auth()->user()->role === 'mechanic')
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>{{ $isMechanic ? 'Set up your independent mechanic profile' : 'Register your service shop' }}</h1>
            <p>{{ $isMechanic ? 'Tell customers what you can repair and where you travel. Your profile appears after document review.' : 'Registrations remain pending until an administrator verifies the business.' }}</p>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('shop.register.store') }}" class="surface p-4 row g-3">@csrf <div
                class="col-md-6"><label class="form-label">{{ $isMechanic ? 'Public mechanic name' : 'Shop name' }}</label><input class="form-control" name="name"
                    value="{{ old('name') }}" required></div>
            @unless ($isMechanic)
            <div class="col-md-6"><label class="form-label">Business permit number</label><input class="form-control"
                    name="business_permit_number" value="{{ old('business_permit_number') }}"></div>
            @endunless
            <div class="col-md-6"><label class="form-label">Contact number</label><input class="form-control"
                    name="phone" value="{{ old('phone') }}" required></div>
            <div class="col-md-6"><label class="form-label">Contact email</label><input class="form-control"
                    name="email" type="email" value="{{ old('email') }}" required></div>
            <div class="col-12"><h2 class="h5 mb-2">{{ $isMechanic ? 'Private base address' : 'Shop address' }}</h2><x-address-picker prefix="shop-registration" :required="true" /></div>
            <div class="col-12"><label class="form-label">{{ $isMechanic ? 'Skills and mobile service description' : 'Business description' }}</label><textarea class="form-control"
                    name="description" rows="4">{{ old('description') }}</textarea></div><x-location-picker :label="$isMechanic ? 'Service-area center' : 'Shop location'" :hint="$isMechanic ? 'Click the map to set the center of the area you can travel to. Avoid your exact home location.' : 'Click the map to place your exact shop pin.'" />
            @if ($isMechanic)<p class="col-12 form-text mb-0">Choose the center of your service area on the map. Customers see this area, so avoid pinpointing your home. After registration, upload a government ID and proof of mechanic qualifications or work history.</p>@endif
            <div class="col-12"><div class="shop-profile-media"><div id="registration-cover-preview" class="shop-cover-preview uploaded-image-fallback" role="img" aria-label="{{ $isMechanic ? 'Mechanic' : 'Shop' }} cover photo preview"><i class="bi bi-shop" aria-hidden="true"></i></div><div id="registration-logo-preview" class="shop-logo-preview uploaded-image-fallback" role="img" aria-label="{{ $isMechanic ? 'Mechanic' : 'Shop' }} logo preview"><i class="bi bi-wrench-adjustable-circle" aria-hidden="true"></i></div></div><p class="small text-secondary mt-2 mb-0">Selected images preview here. Submit registration to save them.</p></div>
            <div class="col-md-6"><label for="logo" class="form-label">{{ $isMechanic ? 'Mechanic logo or profile photo' : 'Shop logo' }}</label><input id="logo" class="form-control @error('logo') is-invalid @enderror" type="file" name="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help" data-image-preview-target="registration-logo-preview" data-image-max-size="2097152">@error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="logo-help" class="form-text">Square JPG, PNG, or WebP up to 2 MB.</small></div>
            <div class="col-md-6"><label for="cover_photo" class="form-label">Shop cover photo</label><input id="cover_photo" class="form-control @error('cover_photo') is-invalid @enderror" type="file" name="cover_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="cover-help" data-image-preview-target="registration-cover-preview" data-image-max-size="4194304">@error('cover_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="cover-help" class="form-text">Wide JPG, PNG, or WebP up to 4 MB.</small></div>
            <fieldset class="col-12">
                <legend class="h5 mb-1">Services offered</legend>
                <p class="text-secondary small mb-3">Select the services you provide. You can set prices and availability after registration.</p>
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
