<x-layouts.app title="My repairs">
    <div class="page-heading">
        <h1>My repairs</h1>
        <p>Review estimates, approve work, and track your vehicle's repair status.</p>
    </div>
    @if (request()->integer('appointment_id') > 0 || request()->integer('repair_job_id') > 0)<p><a href="{{ route('customer.repairs.index') }}">View all repairs</a></p>@endif
    <x-form-errors />
    @forelse ($repairJobs as $repairJob)
        <article id="repair-{{ $repairJob->id }}" class="surface p-4 mb-3" aria-labelledby="repair-title-{{ $repairJob->id }}">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 id="repair-title-{{ $repairJob->id }}" class="h5 mb-1">{{ $repairJob->job_number }}</h2>
                    <p class="text-secondary mb-0">{{ $repairJob->shop->name }} · {{ $repairJob->vehicle->plate_number }}</p>
                </div>
                <div><span class="badge {{ in_array($repairJob->status, ['completed', 'released'], true) ? 'text-bg-success' : 'text-bg-secondary' }} text-capitalize">{{ str_replace('_', ' ', $repairJob->status) }}</span></div>
            </div>
            <p>{{ $repairJob->description }}</p>
            @if ($repairJob->quotation?->status === 'sent' && ! $repairJob->quotation->valid_until?->isBefore(today()))
                <div class="alert alert-info mb-3">
                    <strong>Your approval is needed before work starts.</strong>
                    <p class="mb-2">Review the estimate of PHP {{ number_format($repairJob->quotation->total, 2) }}@if ($repairJob->quotation->valid_until) (valid until {{ $repairJob->quotation->valid_until->format('M j, Y') }})@endif.</p>
                    <ul class="mb-3">@foreach ($repairJob->quotation->items as $item)<li>{{ $item->description }}: {{ $item->quantity }} × PHP {{ number_format($item->unit_price, 2) }}</li>@endforeach</ul>
                    <form method="POST" action="{{ route('customer.quotations.update', $repairJob->quotation) }}" class="d-flex flex-wrap gap-2">
                        @csrf @method('PATCH')
                        <button class="btn btn-success" name="status" value="accepted">Approve estimate</button>
                        <button class="btn btn-outline-danger" name="status" value="rejected">Decline estimate</button>
                    </form>
                </div>
            @elseif ($repairJob->status === 'waiting_approval')
                <p class="text-secondary">{{ $repairJob->quotation?->status === 'sent' ? 'This estimate has expired. Ask the shop for an updated estimate.' : 'Waiting for the shop to send an estimate.' }}</p>
            @elseif ($repairJob->status === 'approved')
                <p class="text-secondary">You approved the estimate. The shop will start the repair next; no work has been marked as started yet.</p>
            @endif
            @if ($repairJob->diagnosis)
                <p><strong>Diagnosis:</strong> {{ $repairJob->diagnosis }}</p>
            @endif
            @if ($repairJob->items->isNotEmpty())
                <div class="table-responsive mb-3"><table class="table table-sm"><caption>Itemized repair work</caption><thead><tr><th>Item</th><th class="text-end">Quantity</th><th class="text-end">Subtotal</th></tr></thead><tbody>@foreach($repairJob->items as $item)<tr><td>{{ $item->description }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">PHP {{ number_format($item->subtotal, 2) }}</td></tr>@endforeach<tr><th colspan="2">Itemized total</th><th class="text-end">PHP {{ number_format($repairJob->items->sum('subtotal'), 2) }}</th></tr></tbody></table></div>
            @endif
            @if ($repairJob->technicianAssignments->isNotEmpty())
                <p class="mb-3"><strong>Technician{{ $repairJob->technicianAssignments->count() > 1 ? 's' : '' }}:</strong> {{ $repairJob->technicianAssignments->pluck('technician.name')->unique()->implode(', ') }}</p>
            @endif
            <x-repair-timeline :repair-job="$repairJob" />
            @if($repairJob->photos->isNotEmpty())
                <details class="repair-details mb-3"><summary>Before & after photos · {{ $repairJob->photos->count() }}</summary><div class="repair-evidence-grid mt-3">@foreach($repairJob->photos as $photo)<figure class="mb-0"><a href="{{ route('repair-photos.show', $photo) }}" target="_blank" rel="noopener"><img class="repair-evidence-image" src="{{ route('repair-photos.show', $photo) }}" alt="{{ ucfirst($photo->stage) }} repair photo: {{ $photo->caption ?: $repairJob->vehicle->plate_number }}" loading="lazy"></a><figcaption class="small mt-1"><strong>{{ ucfirst($photo->stage) }}</strong>@if($photo->caption) · {{ $photo->caption }}@endif</figcaption></figure>@endforeach</div></details>
            @endif
            @if(in_array($repairJob->status, ['completed', 'released'], true))
                <section class="border rounded p-3 mb-3" aria-label="Warranty and aftercare">
                    <h3 class="h6">Warranty & aftercare</h3>
                    @if($repairJob->warranty)
                        <p class="mb-1"><strong>{{ $repairJob->warranty->valid_until->isBefore(today()) ? 'Warranty ended' : 'Warranty active until '.$repairJob->warranty->valid_until->format('M j, Y') }}</strong></p>
                        <p class="text-secondary mb-2">{{ $repairJob->warranty->coverage }}</p>
                    @else
                        <p class="text-secondary mb-2">No warranty has been issued for this repair.</p>
                    @endif
                    @if($repairJob->serviceCases->isNotEmpty())<p class="small"><a href="{{ route('customer.service-cases.index') }}">Track {{ $repairJob->serviceCases->count() }} aftercare {{ str('request')->plural($repairJob->serviceCases->count()) }}</a></p>@endif
                    <details><summary>Request a follow-up or report a concern</summary>
                        <form method="POST" action="{{ route('customer.service-cases.store', $repairJob) }}" class="row g-3 mt-1">@csrf
                            <div class="col-md-4"><label class="form-label" for="case-type-{{ $repairJob->id }}">Request type</label><select id="case-type-{{ $repairJob->id }}" class="form-select" name="type" required><option value="follow_up">Follow-up repair</option>@if($repairJob->warranty && ! $repairJob->warranty->valid_until->isBefore(today()))<option value="warranty_claim">Warranty claim</option>@endif<option value="complaint">Complaint for admin review</option></select></div>
                            <div class="col-md-8"><label class="form-label" for="case-subject-{{ $repairJob->id }}">Short subject</label><input id="case-subject-{{ $repairJob->id }}" class="form-control" name="subject" maxlength="150" required placeholder="Example: Noise returned after the repair"></div>
                            <div class="col-12"><label class="form-label" for="case-description-{{ $repairJob->id }}">What happened?</label><textarea id="case-description-{{ $repairJob->id }}" class="form-control" name="description" rows="3" minlength="10" maxlength="3000" required placeholder="Describe the issue and when it started."></textarea></div>
                            <div class="col-12"><button class="btn btn-outline-primary">Send request</button></div>
                        </form>
                    </details>
                </section>
            @endif
            <dl class="row mb-0">
                <dt class="col-sm-4">Estimated cost</dt>
                <dd class="col-sm-8">{{ $repairJob->estimated_cost !== null ? 'PHP '.number_format($repairJob->estimated_cost, 2) : 'Awaiting estimate' }}</dd>
                <dt class="col-sm-4">Actual cost</dt>
                <dd class="col-sm-8">{{ in_array($repairJob->status, ['completed', 'released'], true) && $repairJob->actual_cost !== null ? 'PHP '.number_format($repairJob->actual_cost, 2) : 'Not finalized' }}</dd>
                <dt class="col-sm-4">Started</dt>
                <dd class="col-sm-8">{{ $repairJob->started_at?->format('M j, Y g:i A') ?? 'Not started' }}</dd>
                <dt class="col-sm-4">Completed</dt>
                <dd class="col-sm-8">{{ $repairJob->completed_at?->format('M j, Y g:i A') ?? 'Not completed' }}</dd>
            </dl>
        </article>
    @empty
        <div class="surface p-4">
            <h2 class="h5">No repair jobs yet</h2>
            <p>Your shop's repair jobs will appear here once work is arranged.</p>
            <a class="btn btn-outline-primary" href="{{ route('customer.appointments.index') }}">View appointments</a>
        </div>
    @endforelse
    {{ $repairJobs->links() }}
</x-layouts.app>
