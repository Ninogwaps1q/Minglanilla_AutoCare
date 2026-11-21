<x-layouts.app :title="$shop->isIndependentMechanic() ? 'Mechanic profile' : 'Shop profile'">
    @php
        $missingShopLogo = $shop->logo && !\Illuminate\Support\Facades\Storage::disk('public')->exists($shop->logo);
        $missingShopCover = $shop->cover_photo && !\Illuminate\Support\Facades\Storage::disk('public')->exists($shop->cover_photo);
    @endphp
    @if ($missingShopLogo || $missingShopCover)
        <div class='alert alert-warning' role='status'>
            <strong>Some saved profile photos are missing.</strong>
            @if ($missingShopLogo)
            <p class='mb-1'>Choose a new profile photo below, then save your profile.</p>@endif
            @if ($missingShopCover)
            <p class='mb-0'>Choose a new cover photo below, then save your profile.</p>@endif
        </div>
    @endif
    <div class="page-heading">
        <h1>{{ $shop->isIndependentMechanic() ? 'Independent mechanic profile' : 'Shop profile' }}</h1>
        <p>Update the information shown to customers after verification.{{ $shop->isIndependentMechanic() ? ' Your base address stays private; customers see your service area.' : '' }}</p>
    </div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('shop.profile.update') }}"
        class="surface p-4 row g-3">@csrf @method('PUT')
        <div class="col-12">
            <div class="shop-profile-media"><x-uploaded-image id="shop-cover-preview" class="shop-cover-preview"
                    :path="$shop->cover_photo" :alt="$shop->name . ' cover photo'"
                    fallback-icon="shop" /><x-uploaded-image id="shop-logo-preview" class="shop-logo-preview"
                    :path="$shop->logo" :alt="$shop->name . ' logo'" fallback-icon="wrench-adjustable-circle" /></div>
            <p class="small text-secondary mt-2 mb-0">Your selected photos preview here. Save profile to publish them.
            </p>
        </div>
        <div class="col-md-6"><label class="form-label">{{ $shop->isIndependentMechanic() ? 'Public mechanic name' : 'Shop name' }}</label><input class="form-control" name="name"
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
        <div class="col-12"><label class="form-label">{{ $shop->isIndependentMechanic() ? 'Base address (private)' : 'Address' }}</label><input class="form-control" name="address"
                value="{{ old('address', $shop->address) }}" required></div>
        @if ($shop->isIndependentMechanic())
            <x-location-picker :latitude="$shop->latitude" :longitude="$shop->longitude" label="Service-area center" hint="This approximate area appears on the public map. Avoid pinpointing your home." />
        @endif
        <div class="col-md-6"><label class="form-label">Opening time</label><input class="form-control" type="time"
                name="opening_time"
                value="{{ old('opening_time', $shop->opening_time ? substr($shop->opening_time, 0, 5) : '') }}"></div>
        <div class="col-md-6"><label class="form-label">Closing time</label><input class="form-control" type="time"
                name="closing_time"
                value="{{ old('closing_time', $shop->closing_time ? substr($shop->closing_time, 0, 5) : '') }}"></div>
        <fieldset class="col-12">
            <legend class="form-label">Days open for bookings</legend>
            <input type="hidden" name="operating_days_present" value="1">
            <div class="d-flex flex-wrap gap-3">
                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                    <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="operating_days[]" value="{{ $day }}" @checked(in_array($day, old('operating_days', $shop->operating_days ?? [1, 2, 3, 4, 5, 6, 7])))>{{ $label }}</label>
                @endforeach
            </div>
            <p class="form-text">Uncheck a day to stop new bookings on that weekday. Existing appointments remain visible.</p>
        </fieldset>
        <div class="col-md-6"><input type="hidden" name="roadside_available" value="0"><label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="roadside_available" value="1" @checked(old('roadside_available', $shop->roadside_available ?? true))>Accept roadside requests now</label><div class="form-text">Turn off when you are unavailable.</div></div>
        <div class="col-md-6"><label class="form-label" for="roadside-radius">Roadside service radius (km)</label><input class="form-control" id="roadside-radius" type="number" name="roadside_radius_km" min="1" max="200" value="{{ old('roadside_radius_km', $shop->roadside_radius_km) }}"><div class="form-text">{{ $shop->isIndependentMechanic() ? 'Required when you accept roadside requests. Set how far you can travel from your service-area center.' : 'Leave blank for no distance limit.' }}</div></div>
        @unless ($shop->isIndependentMechanic())
        <div class="col-md-6"><label class="form-label" for="booking_capacity">Appointments at the same
                time</label><input id="booking_capacity"
                class="form-control @error('booking_capacity') is-invalid @enderror" type="number"
                name="booking_capacity" min="1" max="10"
                value="{{ old('booking_capacity', $shop->booking_capacity ?? 1) }}" required>
            <div class="form-text">Set this to the number of service bays or teams that can work at once.</div>
            @error('booking_capacity')
            <div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @else
            <input type="hidden" name="booking_capacity" value="1">
        @endunless
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control"
                name="description" rows="4">{{ old('description', $shop->description) }}</textarea></div>
        @if (config('services.paymongo.legacy_manual_payments'))
        <div class="col-12">
            <h2 class="h5 mb-1">Payment instructions</h2>
            <p class="text-secondary mb-0">Only customers with an invoice can see these details. Leave a method blank if
                your shop does not accept it.</p>
        </div>
        <div class="col-md-6"><label class="form-label" for="gcash_number">GCash number</label><input id="gcash_number"
                class="form-control @error('gcash_number') is-invalid @enderror" name="gcash_number"
                value="{{ old('gcash_number', $shop->gcash_number) }}" inputmode="tel"
                maxlength="30">@error('gcash_number')
                <div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6"><label class="form-label" for="bank_transfer_details">Bank transfer
                details</label><textarea id="bank_transfer_details"
                class="form-control @error('bank_transfer_details') is-invalid @enderror" name="bank_transfer_details"
                rows="2" maxlength="1000"
                placeholder="Bank name, account name, and account number">{{ old('bank_transfer_details', $shop->bank_transfer_details) }}</textarea>@error('bank_transfer_details')
                <div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @endif
        <div class="col-md-6"><label for="logo" class="form-label">Shop logo</label><input id="logo"
                class="form-control @error('logo') is-invalid @enderror" type="file" name="logo"
                accept="image/png,image/jpeg,image/webp" aria-describedby="logo-help"
                data-image-preview-target="shop-logo-preview" data-image-max-size="2097152">@error('logo')
                <div class="invalid-feedback">{{ $message }}</div>@enderror<small id="logo-help" class="form-text">JPG, PNG,
                or WebP up to 2 MB.</small>
        </div>
        <div class="col-md-6"><label for="cover_photo" class="form-label">Cover photo</label><input id="cover_photo"
                class="form-control @error('cover_photo') is-invalid @enderror" type="file" name="cover_photo"
                accept="image/png,image/jpeg,image/webp" aria-describedby="cover-help"
                data-image-preview-target="shop-cover-preview" data-image-max-size="4194304">@error('cover_photo')
                <div class="invalid-feedback">{{ $message }}</div>@enderror<small id="cover-help" class="form-text">Wide
                JPG, PNG, or WebP up to 4 MB.</small>
        </div>
        <div class="col-12"><button class="btn btn-primary">Save profile</button></div>
    </form>
    <section class="surface p-4 mt-4" aria-labelledby="closed-times-title">
        <h2 id="closed-times-title" class="h4">Holidays and blocked times</h2>
        <p class="text-secondary">Block a whole day with 00:00 to 23:59, or choose a shorter time. New bookings will be refused during that period.</p>
        <form method="POST" action="{{ route('shop.profile.schedule-blocks.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label" for="block-date">Date</label><input class="form-control" id="block-date" type="date" name="date" min="{{ today()->toDateString() }}" required></div>
            <div class="col-md-2"><label class="form-label" for="block-start">From</label><input class="form-control" id="block-start" type="time" name="start" value="00:00" required></div>
            <div class="col-md-2"><label class="form-label" for="block-end">Until</label><input class="form-control" id="block-end" type="time" name="end" value="23:59" required></div>
            <div class="col-md-3"><label class="form-label" for="block-reason">Reason</label><input class="form-control" id="block-reason" name="reason" maxlength="160" placeholder="Holiday or team unavailable" required></div>
            <div class="col-md-2"><button class="btn btn-primary" type="submit">Block time</button></div>
        </form>
        <ul class="list-group mt-3">
            @forelse (collect($shop->schedule_blocks ?? [])->sortBy('date') as $block)
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2"><span>{{ $block['date'] }} · {{ $block['start'] }}–{{ $block['end'] }} · {{ $block['reason'] }}</span><form method="POST" action="{{ route('shop.profile.schedule-blocks.destroy', $block['id']) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form></li>
            @empty
                <li class="list-group-item text-secondary">No blocked times.</li>
            @endforelse
        </ul>
    </section>
    <section class="surface p-4 mt-4" aria-labelledby="verification-documents-title">
        <div class="mb-3">
            <h2 class="h4 mb-1" id="verification-documents-title">Verification documents</h2>
            <p class="text-secondary mb-0">Upload {{ $shop->isIndependentMechanic() ? 'your government ID and proof of mechanic qualifications or work history' : 'permits' }} securely for administrator review. PDF, JPG, or PNG up to 5
                MB.</p>
        </div>
        <div class="row g-2 mb-3" aria-label="Required verification documents">
            @foreach ($shop->permitChecklist() as $permit)
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <strong>{{ $permit['label'] }}</strong><span
                                class="badge text-bg-{{ $permit['status'] === 'verified' ? 'success' : ($permit['status'] === 'rejected' || $permit['status'] === 'expired' ? 'danger' : 'warning') }} text-capitalize">{{ $permit['status'] }}</span>
                        </div>
                        <p class="small text-secondary mb-0 mt-2">{{ $permit['action'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        @unless ($shop->hasCurrentVerifiedPermits())
            <p class="small text-secondary">@if ($shop->verification_status === 'approved') Your profile is approved, but its
            required documents are not currently verified. @else Both required documents must be current and verified before
                admin can approve your profile. @endif If a document is rejected or expired, upload a corrected copy below.</p>
        @endunless
        <form method="POST" enctype="multipart/form-data" action="{{ route('shop.documents.store') }}"
            class="row g-3 align-items-end mb-4">@csrf
            <div class="col-md-4"><label class="form-label" for="document_type">Document type</label><select
                    class="form-select" id="document_type" name="document_type" required>
                    @if ($shop->isIndependentMechanic())
                        <option value="government_id">Government ID</option>
                        <option value="mechanic_credentials">Mechanic qualifications or work history</option>
                    @else
                        <option value="business_permit">Business permit</option>
                        <option value="bir_registration">BIR registration</option>
                        <option value="dti_registration">DTI registration</option>
                        <option value="mayors_permit">Mayor's permit</option>
                    @endif
                    <option value="other">Other</option>
                </select></div>
            <div class="col-md-4"><label class="form-label" for="document">Document file</label><input
                    class="form-control @error('document') is-invalid @enderror" id="document" type="file"
                    name="document" accept="application/pdf,image/jpeg,image/png" required>@error('document')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2"><label class="form-label" for="expires_at">Expiry date <span
                        class="text-secondary">(optional)</span></label><input class="form-control" id="expires_at"
                    type="date" name="expires_at" min="{{ today()->toDateString() }}"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">Upload</button></div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Admin notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shop->documents as $document)
                        <tr>
                            <td class="text-capitalize">{{ str_replace('_', ' ', $document->document_type) }}</td>
                            <td class="text-capitalize">{{ $document->verification_status }}</td>
                            <td>{{ $document->expires_at?->format('M j, Y') ?? 'No expiry' }}@if($document->expires_at?->isPast())<br><span
                            class="text-danger small">Expired</span>@endif</td>
                            <td>{{ $document->admin_notes ?: '—' }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('shop.documents.download', $document) }}">Download</a>@if ($document->verification_status !== 'verified')
                                        <form class="d-inline" method="POST"
                                            action="{{ route('shop.documents.destroy', $document) }}">@csrf @method('DELETE')<button
                                    class="btn btn-sm btn-outline-danger">Remove</button></form>@endif
                            </td>
                    </tr>@empty<tr>
                        <td colspan="5" class="text-secondary">No verification documents uploaded.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
