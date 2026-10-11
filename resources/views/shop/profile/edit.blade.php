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
    <section class="surface p-4 mt-4" aria-labelledby="verification-documents-title">
        <div class="mb-3"><h2 class="h4 mb-1" id="verification-documents-title">Verification documents</h2><p class="text-secondary mb-0">Upload permits securely for administrator review. PDF, JPG, or PNG up to 5 MB.</p></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('shop.documents.store') }}" class="row g-3 align-items-end mb-4">@csrf
            <div class="col-md-4"><label class="form-label" for="document_type">Document type</label><select class="form-select" id="document_type" name="document_type" required><option value="business_permit">Business permit</option><option value="bir_registration">BIR registration</option><option value="dti_registration">DTI registration</option><option value="mayors_permit">Mayor's permit</option><option value="other">Other</option></select></div>
            <div class="col-md-4"><label class="form-label" for="document">Document file</label><input class="form-control @error('document') is-invalid @enderror" id="document" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required>@error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-2"><label class="form-label" for="expires_at">Expiry date <span class="text-secondary">(optional)</span></label><input class="form-control" id="expires_at" type="date" name="expires_at" min="{{ today()->toDateString() }}"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">Upload</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Type</th><th>Status</th><th>Expiry</th><th>Admin notes</th><th></th></tr></thead><tbody>
            @forelse ($shop->documents as $document)<tr><td class="text-capitalize">{{ str_replace('_', ' ', $document->document_type) }}</td><td class="text-capitalize">{{ $document->verification_status }}</td><td>{{ $document->expires_at?->format('M j, Y') ?? 'No expiry' }}@if($document->expires_at?->isPast())<br><span class="text-danger small">Expired</span>@endif</td><td>{{ $document->admin_notes ?: '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('shop.documents.download', $document) }}">Download</a>@if ($document->verification_status !== 'verified')<form class="d-inline" method="POST" action="{{ route('shop.documents.destroy', $document) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="text-secondary">No verification documents uploaded.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
