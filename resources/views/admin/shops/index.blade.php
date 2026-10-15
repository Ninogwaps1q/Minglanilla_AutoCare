<x-layouts.app title="Shop verification">
    <div class="page-heading">
        <h1>Shop verification</h1>
        <p>Review business documents, decide on registrations, and manage approved shops.</p>
    </div>
    <x-form-errors />

    <form method="GET" action="{{ route('admin.shops.index') }}" class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-2">
        <div><label for="verification-status-filter" class="form-label">Show shops</label><select id="verification-status-filter" name="status" class="form-select"><option value="">All statuses</option>@foreach (['pending' => 'Awaiting review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach</select></div>
        <button class="btn btn-outline-primary">Apply filter</button>
        @if ($selectedStatus)<a class="btn btn-link" href="{{ route('admin.shops.index') }}">Clear</a>@endif
    </form>

    <div class="d-grid gap-3">
        @forelse ($shops as $shop)
            @php
                $currentVerifiedTypes = $shop->documents->where('verification_status', 'verified')->filter(fn ($document) => ! $document->expires_at || $document->expires_at->isToday() || $document->expires_at->isFuture())->pluck('document_type');
                $documentsComplete = $currentVerifiedTypes->contains('business_permit') && $currentVerifiedTypes->contains('mayors_permit');
            @endphp
            <article class="surface p-4" aria-labelledby="shop-review-{{ $shop->id }}">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div><h2 id="shop-review-{{ $shop->id }}" class="h5 mb-1">{{ $shop->name }}</h2><p class="text-secondary mb-0">{{ $shop->owner->name }} · {{ $shop->barangay->name }}</p></div>
                    <div class="d-flex flex-wrap gap-2"><span class="badge text-bg-{{ $shop->verification_status === 'approved' ? 'success' : ($shop->verification_status === 'rejected' ? 'danger' : 'warning') }} text-capitalize">{{ $shop->verification_status }}</span><span class="badge text-bg-secondary text-capitalize">{{ $shop->shop_status }}</span></div>
                </div>
                <div class="admin-review-check mb-3"><strong>Document check:</strong> {{ $documentsComplete ? 'Business and mayor’s permits are verified and current.' : 'One or both required permits are missing, expired, or not verified.' }} @unless ($documentsComplete)<span>Approval still requires your own review and decision.</span>@endunless</div>

                <details class="repair-details mb-3"><summary>Review documents ({{ $shop->documents->count() }})</summary>
                    @forelse ($shop->documents as $document)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2"><strong class="text-capitalize">{{ str_replace('_', ' ', $document->document_type) }}</strong><span class="text-capitalize">{{ $document->verification_status }}</span></div>
                            <p class="small text-secondary mb-2">{{ $document->expires_at ? 'Expires '.$document->expires_at->format('M j, Y') : 'No expiry provided' }}</p>
                            <a href="{{ route('admin.shop-documents.download', $document) }}">Download securely</a>
                            @if ($document->verification_status === 'pending')
                                <form method="POST" action="{{ route('admin.shop-documents.update', $document) }}" class="mt-3">@csrf @method('PATCH')
                                    <label class="form-label" for="document-notes-{{ $document->id }}">Reason if rejecting this document</label><textarea id="document-notes-{{ $document->id }}" class="form-control mb-2" name="admin_notes" maxlength="2000" rows="2" placeholder="Tell the shop owner what to correct"></textarea>
                                    <div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-success" name="verification_status" value="verified">Verify document</button><button class="btn btn-sm btn-outline-danger" name="verification_status" value="rejected">Reject document</button></div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-secondary mb-0">No documents uploaded yet.</p>
                    @endforelse
                </details>

                <div class="d-flex flex-wrap align-items-end gap-2">
                    @if ($shop->verification_status !== 'approved')
                        <form method="POST" action="{{ route('admin.shops.approve', $shop) }}">@csrf @method('PATCH')<button class="btn btn-success" @unless($documentsComplete) data-confirm="Some required permits are not currently verified. Approve this shop based on your administrative review?" @endunless>Approve shop</button></form>
                        @if ($shop->verification_status !== 'rejected')<form method="POST" action="{{ route('admin.shops.reject', $shop) }}">@csrf @method('PATCH')<button class="btn btn-outline-danger">Reject registration</button></form>@endif
                    @elseif ($shop->shop_status === 'active')
                        <form method="POST" action="{{ route('admin.shops.status', $shop) }}" class="d-flex flex-wrap align-items-end gap-2">@csrf @method('PATCH')<input type="hidden" name="shop_status" value="suspended"><div><label class="form-label" for="suspend-reason-{{ $shop->id }}">Reason for suspending {{ $shop->name }}</label><input id="suspend-reason-{{ $shop->id }}" class="form-control" name="reason" maxlength="1000" placeholder="Shared with the shop owner" required></div><button class="btn btn-outline-danger">Suspend shop</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.shops.status', $shop) }}">@csrf @method('PATCH')<input type="hidden" name="shop_status" value="active"><button class="btn btn-outline-primary">Reactivate shop</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="surface p-4"><h2 class="h5">{{ $selectedStatus ? 'No shops with this status' : 'No shop registrations yet' }}</h2><p class="text-secondary mb-2">{{ $selectedStatus ? 'Choose another status or view all shops.' : 'New applications will appear here for review.' }}</p>@if ($selectedStatus)<a href="{{ route('admin.shops.index') }}">View all shops</a>@endif</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $shops->links() }}</div>
</x-layouts.app>
