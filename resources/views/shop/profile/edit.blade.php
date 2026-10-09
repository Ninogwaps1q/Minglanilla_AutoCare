<x-layouts.app title="Shop profile">
    <div class="page-heading">
        <h1>Shop profile</h1>
        <p>Update the public information shown to customers after verification.</p>
    </div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('shop.profile.update') }}" class="surface p-4 row g-3">@csrf @method('PUT')
        <div class="col-12"><div class="shop-profile-media"><x-uploaded-image class="shop-cover-preview" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" /><x-uploaded-image class="shop-logo-preview" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" /></div></div>
        <div class="col-md-6"><label class="form-label">Shop name</label><input class="form-control" name="name"
                value="{{ old('name', $shop->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"
                value="{{ old('phone', $shop->phone) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email"
                value="{{ old('email', $shop->email) }}" required></div>
        <div class="col-md-6"><label class="form-label">Barangay</label><select class="form-select" name="barangay_id"
                required>@foreach($barangays as $barangay)
                    <option value="{{ $barangay->id }}" @selected($shop->barangay_id === $barangay->id)>{{ $barangay->name }}
                </option>@endforeach
            </select></div>
        <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address"
                value="{{ old('address', $shop->address) }}" required></div>
        <div class="col-md-6"><label class="form-label">Opening time</label><input class="form-control" type="time"
                name="opening_time"
                value="{{ old('opening_time', $shop->opening_time ? substr($shop->opening_time, 0, 5) : '') }}"></div>
        <div class="col-md-6"><label class="form-label">Closing time</label><input class="form-control" type="time"
                name="closing_time"
                value="{{ old('closing_time', $shop->closing_time ? substr($shop->closing_time, 0, 5) : '') }}"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control"
                name="description" rows="4">{{ old('description', $shop->description) }}</textarea></div>
        <div class="col-md-6"><label for="logo" class="form-label">Shop logo</label><input id="logo" class="form-control @error('logo') is-invalid @enderror" type="file" name="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help">@error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="logo-help" class="form-text">JPG, PNG, or WebP up to 2 MB.</small></div>
        <div class="col-md-6"><label for="cover_photo" class="form-label">Cover photo</label><input id="cover_photo" class="form-control @error('cover_photo') is-invalid @enderror" type="file" name="cover_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="cover-help">@error('cover_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="cover-help" class="form-text">Wide JPG, PNG, or WebP up to 4 MB.</small></div>
        <div class="col-12"><button class="btn btn-primary">Save profile</button></div>
    </form>
</x-layouts.app>
