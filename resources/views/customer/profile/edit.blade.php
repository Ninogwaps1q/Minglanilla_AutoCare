<x-layouts.app title="My profile">
    <div class="page-heading"><div><h1>My profile</h1><p>Keep your contact and location details current for smoother bookings and service updates.</p></div></div>
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" enctype="multipart/form-data" action="{{ route('customer.profile.update') }}" class="surface p-4 p-lg-5">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6"><label for="name" class="form-label">Full name</label><input id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="phone" class="form-label">Mobile number</label><input id="phone" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label for="address" class="form-label">Street address</label><input id="address" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $user->address) }}" autocomplete="street-address">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="barangay_id" class="form-label">Barangay</label><select id="barangay_id" class="form-select @error('barangay_id') is-invalid @enderror" name="barangay_id"><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->id }}" @selected(old('barangay_id', $user->barangay_id) === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select>@error('barangay_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label for="profile_photo" class="form-label">Profile photo</label><input id="profile_photo" class="form-control @error('profile_photo') is-invalid @enderror" type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="profile-photo-help" data-image-preview-target="profile-photo-preview" data-image-max-size="2097152">@error('profile_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="profile-photo-help" class="form-text">JPG, PNG, or WebP up to 2 MB. Preview before saving.</small></div>
                </div>
                <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Save profile</button><a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Cancel</a></div>
            </form>
        </div>
        <aside class="col-lg-4"><div class="surface p-4 h-100"><x-uploaded-image id="profile-photo-preview" class="profile-photo-preview mb-4" :path="$user->profile_photo" :alt="$user->name.' profile photo'" fallback-icon="person" /><h2 class="h5">Account email</h2><p class="mb-0 text-secondary text-break">{{ $user->email }}</p><p class="small text-secondary mt-3 mb-0">Your email is managed through the account security flow and is not changed here.</p></div></aside>
    </div>
</x-layouts.app>
