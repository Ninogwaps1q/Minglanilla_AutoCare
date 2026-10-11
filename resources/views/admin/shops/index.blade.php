<x-layouts.app title="Shop verification">
    <div class="page-heading">
        <h1>Shop verification</h1>
        <p>Review registered service providers and control their visibility.</p>
    </div>
    <x-form-errors />
    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Owner</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>@forelse($shops as $shop)<tr>
                        <td>{{ $shop->name }}
                            @php $currentVerifiedTypes = $shop->documents->where('verification_status', 'verified')->filter(fn ($document) => ! $document->expires_at || $document->expires_at->isFuture())->pluck('document_type'); @endphp
                            <div class="small {{ $currentVerifiedTypes->contains('business_permit') && $currentVerifiedTypes->contains('mayors_permit') ? 'text-success' : 'text-warning' }}">{{ $currentVerifiedTypes->contains('business_permit') && $currentVerifiedTypes->contains('mayors_permit') ? 'Required documents complete' : 'Required documents incomplete' }}</div>
                            @if ($shop->documents->isNotEmpty())
                                <details class="mt-2"><summary class="small fw-semibold">{{ $shop->documents->count() }} document(s)</summary><div class="vstack gap-2 mt-2">@foreach ($shop->documents as $document)<div class="border rounded p-2 small"><div class="d-flex justify-content-between gap-2"><span class="text-capitalize">{{ str_replace('_', ' ', $document->document_type) }}</span><span class="text-capitalize">{{ $document->verification_status }}</span></div><div>{{ $document->expires_at ? 'Expires '.$document->expires_at->format('M j, Y') : 'No expiry provided' }}</div><a href="{{ route('admin.shop-documents.download', $document) }}">Download securely</a>@if ($document->verification_status === 'pending')<form method="POST" action="{{ route('admin.shop-documents.update', $document) }}" class="vstack gap-1 mt-2">@csrf @method('PATCH')<textarea class="form-control form-control-sm" name="admin_notes" maxlength="2000" placeholder="Required reason when rejecting"></textarea><div><button class="btn btn-sm btn-success" name="verification_status" value="verified">Verify</button> <button class="btn btn-sm btn-outline-danger" name="verification_status" value="rejected">Reject</button></div></form>@endif</div>@endforeach</div></details>
                            @endif
                        </td>
                        <td>{{ $shop->owner->name }}</td>
                        <td>{{ $shop->barangay->name }}</td>
                        <td class="text-capitalize">{{ $shop->verification_status }}<br><small class="text-secondary">{{ $shop->shop_status }}</small></td>
                        <td class="text-end">
                            @if ($shop->verification_status !== 'approved')
                                <form class="d-inline" method="POST" action="{{ route('admin.shops.approve', $shop) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Approve</button></form>
                                @if ($shop->verification_status !== 'rejected')
                                    <form class="d-inline" method="POST" action="{{ route('admin.shops.reject', $shop) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Reject</button></form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('admin.shops.status', $shop) }}" class="d-flex flex-wrap justify-content-end gap-2">
                                    @csrf @method('PATCH')
                                    @if ($shop->shop_status === 'active')
                                        <input type="hidden" name="shop_status" value="suspended">
                                        <label class="visually-hidden" for="suspend-reason-{{ $shop->id }}">Reason for suspending {{ $shop->name }}</label>
                                        <input id="suspend-reason-{{ $shop->id }}" class="form-control form-control-sm" name="reason" maxlength="1000" placeholder="Reason shared with the owner" required>
                                        <button class="btn btn-sm btn-outline-danger">Suspend shop</button>
                                    @else
                                        <input type="hidden" name="shop_status" value="active">
                                        <button class="btn btn-sm btn-outline-primary">Reactivate shop</button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>@empty<tr>
                        <td colspan="5" class="text-secondary p-4">No shop registrations found.</td>
                    </tr>@endforelse</tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $shops->links() }}</div>
</x-layouts.app>
