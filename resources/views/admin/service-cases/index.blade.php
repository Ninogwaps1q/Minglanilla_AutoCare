<x-layouts.app title="Complaint resolution">
    <div class="page-heading">
        <h1>Complaint resolution</h1>
        <p>Review the concern and shop response, then record a clear outcome for both sides.</p>
    </div>

    <form method="GET" action="{{ route('admin.service-cases.index') }}" class="surface complaint-toolbar">
        <div class="complaint-toolbar-filter">
            <label class="form-label" for="complaint-filter">Show complaints</label>
            <select class="form-select" id="complaint-filter" name="status">
                <option value="">All statuses</option>
                @foreach (['open', 'reviewing', 'resolved', 'dismissed'] as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-primary" type="submit">Apply filter</button>
        <span class="complaint-review-count">{{ $openCount }} {{ $openCount === 1 ? 'complaint needs' : 'complaints need' }} review</span>
    </form>

    @forelse ($cases as $case)
        @php $caseHasErrors = (int) old('case_id') === $case->id && $errors->any(); @endphp
        <article class="surface complaint-case" id="complaint-{{ $case->id }}">
            <header class="complaint-case-header">
                <div>
                    <h2>{{ $case->subject }}</h2>
                    <p class="complaint-case-meta">{{ $case->customer->name }} &middot; {{ $case->shop->name }} &middot; {{ $case->repairJob->job_number }} &middot; {{ $case->repairJob->vehicle->plate_number }}</p>
                </div>
                <span class="badge text-bg-{{ in_array($case->status, ['resolved', 'dismissed'], true) ? 'secondary' : 'primary' }}">{{ ucfirst($case->status) }}</span>
            </header>

            <div class="complaint-facts">
                <section>
                    <h3>Customer's concern</h3>
                    <p>{{ $case->description }}</p>
                </section>
                <section>
                    <h3>Shop response</h3>
                    <p>{{ $case->shop_response ?: 'No reply from the shop yet.' }}</p>
                </section>
            </div>

            @if ($case->repairJob->photos->isNotEmpty())
                <details class="complaint-evidence">
                    <summary>Repair photos &middot; {{ $case->repairJob->photos->count() }}</summary>
                    <div class="repair-evidence-grid mt-3">
                        @foreach ($case->repairJob->photos as $photo)
                            <figure class="mb-0">
                                <a href="{{ route('repair-photos.show', $photo) }}" target="_blank" rel="noopener"><img src="{{ route('repair-photos.show', $photo) }}" alt="{{ ucfirst($photo->stage) }} repair photo" class="repair-evidence-image" loading="lazy"></a>
                                <figcaption class="small mt-1">{{ ucfirst($photo->stage) }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </details>
            @endif

            @if ($case->admin_resolution)
                <div class="complaint-recorded-resolution">
                    <strong>Recorded decision</strong>
                    <p>{{ $case->admin_resolution }}</p>
                    <span>By {{ $case->resolvedBy?->name ?? 'Former admin' }} &middot; {{ $case->resolved_at?->format('M j, Y g:i A') }}</span>
                </div>
            @endif

            @if ($caseHasErrors)
                <div class="alert alert-danger complaint-decision-error" role="alert">
                    <strong>Decision not saved.</strong>
                    <ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @unless (in_array($case->status, ['resolved', 'dismissed'], true))
                <section class="complaint-decision" aria-labelledby="complaint-decision-title-{{ $case->id }}">
                    <h3 id="complaint-decision-title-{{ $case->id }}">Close this complaint</h3>
                    <p>Choose Resolve if the concern has been addressed, or Dismiss if no further action is warranted.</p>
                    <form method="POST" action="{{ route('admin.service-cases.update', $case) }}" class="complaint-close-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="case_id" value="{{ $case->id }}">
                        <label class="form-label" for="admin-case-note-{{ $case->id }}">Reason for your decision <span class="text-danger">*</span></label>
                        <textarea class="form-control @if($caseHasErrors && $errors->has('admin_resolution')) is-invalid @endif" id="admin-case-note-{{ $case->id }}" name="admin_resolution" rows="3" minlength="10" maxlength="3000" required aria-describedby="admin-case-help-{{ $case->id }}" placeholder="Explain what happened and what happens next." @if($caseHasErrors && $errors->has('admin_resolution')) autofocus @endif>{{ $caseHasErrors ? old('admin_resolution') : '' }}</textarea>
                        <p class="complaint-decision-help" id="admin-case-help-{{ $case->id }}">At least 10 characters. The customer and shop can read this reason.</p>
                        <div class="complaint-decision-actions">
                            <button class="btn btn-primary" type="submit" name="status" value="resolved"><i class="bi bi-check2-circle" aria-hidden="true"></i> Resolve complaint</button>
                            <button class="btn btn-outline-danger" type="submit" name="status" value="dismissed"><i class="bi bi-x-circle" aria-hidden="true"></i> Dismiss complaint</button>
                        </div>
                    </form>
                    @if ($case->status === 'open')
                        <form method="POST" action="{{ route('admin.service-cases.update', $case) }}" class="complaint-review-form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="case_id" value="{{ $case->id }}">
                            <input type="hidden" name="status" value="reviewing">
                            <button class="btn btn-link" type="submit">Not ready to close? Mark as reviewing</button>
                        </form>
                    @endif
                </section>
            @endunless

            <details class="complaint-history">
                <summary>Resolution history &middot; {{ $case->events->count() }} updates</summary>
                <ol>
                    @foreach ($case->events as $event)
                        <li><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong> &middot; {{ $event->actor?->name ?? 'Former user' }} &middot; {{ $event->created_at->format('M j, Y g:i A') }}<br><span>{{ $event->note }}</span></li>
                    @endforeach
                </ol>
            </details>
        </article>
    @empty
        <div class="surface p-4"><h2 class="h5">No complaints match this filter</h2><p class="text-secondary mb-0">Customer complaints will appear here when submitted from a completed repair.</p></div>
    @endforelse

    {{ $cases->links() }}
</x-layouts.app>
