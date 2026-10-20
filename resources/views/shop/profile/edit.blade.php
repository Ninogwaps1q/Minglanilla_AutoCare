<x-layouts.app title="Shop profile">
    <div class="page-heading">
        <h1>Shop profile</h1>
        <p>Update the public information shown to customers after verification.</p>
    </div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('shop.profile.update') }}" class="surface p-4 row g-3">@csrf @method('PUT')
        <div class="col-12"><div class="shop-profile-media"><x-uploaded-image id="shop-cover-preview" class="shop-cover-preview" :path="$shop->cover_photo" :alt="$shop->name.' cover photo'" fallback-icon="shop" /><x-uploaded-image id="shop-logo-preview" class="shop-logo-preview" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="wrench-adjustable-circle" /></div><p class="small text-secondary mt-2 mb-0">Your selected photos preview here. Save profile to publish them.</p></div>
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
        <div class="col-12"><h2 class="h5 mb-1">Payment instructions</h2><p class="text-secondary mb-0">Only customers with an invoice can see these details. Leave a method blank if your shop does not accept it.</p></div>
        <div class="col-md-6"><label class="form-label" for="gcash_number">GCash number</label><input id="gcash_number" class="form-control @error('gcash_number') is-invalid @enderror" name="gcash_number" value="{{ old('gcash_number', $shop->gcash_number) }}" inputmode="tel" maxlength="30">@error('gcash_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="bank_transfer_details">Bank transfer details</label><textarea id="bank_transfer_details" class="form-control @error('bank_transfer_details') is-invalid @enderror" name="bank_transfer_details" rows="2" maxlength="1000" placeholder="Bank name, account name, and account number">{{ old('bank_transfer_details', $shop->bank_transfer_details) }}</textarea>@error('bank_transfer_details')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label for="logo" class="form-label">Shop logo</label><input id="logo" class="form-control @error('logo') is-invalid @enderror" type="file" name="logo" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help" data-image-preview-target="shop-logo-preview" data-image-max-size="2097152">@error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="logo-help" class="form-text">JPG, PNG, or WebP up to 2 MB.</small></div>
        <div class="col-md-6"><label for="cover_photo" class="form-label">Cover photo</label><input id="cover_photo" class="form-control @error('cover_photo') is-invalid @enderror" type="file" name="cover_photo" accept="image/png,image/jpeg,image/webp" aria-describedby="cover-help" data-image-preview-target="shop-cover-preview" data-image-max-size="4194304">@error('cover_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="cover-help" class="form-text">Wide JPG, PNG, or WebP up to 4 MB.</small></div>
        <div class="col-12"><button class="btn btn-primary">Save profile</button></div>
    </form>
    <section class="surface p-4 mt-4" aria-labelledby="verification-documents-title">
        <div class="mb-3"><h2 class="h4 mb-1" id="verification-documents-title">Verification documents</h2><p class="text-secondary mb-0">Upload permits securely for administrator review. PDF, JPG, or PNG up to 5 MB.</p></div>
        <div class="row g-2 mb-3" aria-label="Required permit checklist">
            @foreach ($shop->permitChecklist() as $permit)
                <div class="col-md-6"><div class="border rounded p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between gap-2"><strong>{{ $permit['label'] }}</strong><span class="badge text-bg-{{ $permit['status'] === 'verified' ? 'success' : ($permit['status'] === 'rejected' || $permit['status'] === 'expired' ? 'danger' : 'warning') }} text-capitalize">{{ $permit['status'] }}</span></div>
                    <p class="small text-secondary mb-0 mt-2">{{ $permit['action'] }}</p>
                </div></div>
            @endforeach
        </div>
        @unless ($shop->hasCurrentVerifiedPermits())<p class="small text-secondary">@if ($shop->verification_status === 'approved') Your shop is approved, but its required permits are not currently verified. @else Both required permits must be current and verified before admin can approve your shop. @endif If a document is rejected or expired, upload a corrected copy below.</p>@endunless
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
