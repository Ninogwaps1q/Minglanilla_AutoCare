<x-layouts.app title="Complaint resolution">
    <div class="page-heading"><h1>Complaint resolution</h1><p>Review customer concerns, shop replies, repair evidence, and the recorded outcome.</p></div>
    <form method="GET" action="{{ route('admin.service-cases.index') }}" class="surface p-3 mb-4 d-flex flex-wrap align-items-end gap-3">
        <div><label class="form-label" for="complaint-filter">Show complaints</label><select class="form-select" id="complaint-filter" name="status"><option value="">All statuses</option>@foreach(['open', 'reviewing', 'resolved', 'dismissed'] as $status)<option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <button class="btn btn-outline-primary">Apply filter</button><span class="text-secondary small ms-auto">{{ $openCount }} need review</span>
    </form>
    <x-form-errors />
    @forelse($cases as $case)
        <article class="surface p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div><h2 class="h5 mb-1">{{ $case->subject }}</h2><p class="text-secondary small mb-0">{{ $case->customer->name }} · {{ $case->shop->name }} · {{ $case->repairJob->job_number }} · {{ $case->repairJob->vehicle->plate_number }}</p></div>
                <span class="badge text-bg-{{ in_array($case->status, ['resolved', 'dismissed'], true) ? 'secondary' : 'primary' }}">{{ ucfirst($case->status) }}</span>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-lg-6"><h3 class="h6">Customer's concern</h3><p>{{ $case->description }}</p></div>
                <div class="col-lg-6"><h3 class="h6">Shop response</h3><p class="text-secondary">{{ $case->shop_response ?: 'No shop response yet.' }}</p></div>
            </div>
            @if($case->repairJob->photos->isNotEmpty())
                <details class="mb-3"><summary>Repair photos · {{ $case->repairJob->photos->count() }}</summary><div class="repair-evidence-grid mt-3">@foreach($case->repairJob->photos as $photo)<figure class="mb-0"><a href="{{ route('repair-photos.show', $photo) }}" target="_blank" rel="noopener"><img src="{{ route('repair-photos.show', $photo) }}" alt="{{ ucfirst($photo->stage) }} repair photo" class="repair-evidence-image" loading="lazy"></a><figcaption class="small mt-1">{{ ucfirst($photo->stage) }}</figcaption></figure>@endforeach</div></details>
            @endif
            @if($case->admin_resolution)<div class="alert alert-info"><strong>Recorded resolution</strong><p class="mb-0 mt-1">{{ $case->admin_resolution }}</p><span class="small">By {{ $case->resolvedBy?->name ?? 'Former admin' }} · {{ $case->resolved_at?->format('M j, Y g:i A') }}</span></div>@endif
            @unless(in_array($case->status, ['resolved', 'dismissed'], true))
                <form method="POST" action="{{ route('admin.service-cases.update', $case) }}" class="row g-3 border-top pt-3">
                    @csrf @method('PATCH')
                    <div class="col-lg-3"><label class="form-label" for="admin-case-status-{{ $case->id }}">Decision</label><select class="form-select" id="admin-case-status-{{ $case->id }}" name="status" required><option value="reviewing">Keep reviewing</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select></div>
                    <div class="col-lg-9"><label class="form-label" for="admin-case-note-{{ $case->id }}">Resolution or reason</label><textarea class="form-control" id="admin-case-note-{{ $case->id }}" name="admin_resolution" rows="3" maxlength="3000" placeholder="Explain the outcome to the customer and shop. Required when closing the case."></textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Save decision</button></div>
                </form>
            @endunless
            <details class="mt-3"><summary>Resolution history · {{ $case->events->count() }} updates</summary><ol class="mt-3 mb-0">@foreach($case->events as $event)<li class="mb-2"><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong> · {{ $event->actor?->name ?? 'Former user' }} · {{ $event->created_at->format('M j, Y g:i A') }}<br><span class="text-secondary">{{ $event->note }}</span></li>@endforeach</ol></details>
        </article>
    @empty
        <div class="surface p-4"><h2 class="h5">No complaints match this filter</h2><p class="text-secondary mb-0">Customer complaints will appear here when submitted from a completed repair.</p></div>
    @endforelse
    {{ $cases->links() }}
</x-layouts.app>
