<x-layouts.app title="Repair jobs">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h1>Repair jobs</h1>
            <p>Manage diagnosis, customer approval, repairs, and vehicle release.</p>
        </div>
        <div><a class="btn btn-primary" href="{{ route('shop.repair-jobs.create') }}">Create repair job</a></div>
    </div>
    @if (request()->integer('appointment_id') > 0 || request()->integer('repair_job_id') > 0)
    <p><a href="{{ route('shop.repair-jobs.index') }}">View all repair jobs</a></p>@endif
    @include('components.form-errors')
    <form method="GET" action="{{ route('shop.repair-jobs.index') }}"
        class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-2">
        <div><label for="repair-status-filter" class="form-label">Show repair jobs</label><select
                id="repair-status-filter" class="form-select" name="status">
                <option value="">All stages</option>
                @foreach (['pending' => 'New jobs', 'diagnosis' => 'Diagnosis', 'waiting_approval' => 'Waiting for approval', 'approved' => 'Ready to start', 'repairing' => 'Repairing', 'in_progress' => 'In progress (older jobs)', 'waiting_parts' => 'Waiting for parts', 'completed' => 'Completed', 'released' => 'Released', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach
            </select></div><button class="btn btn-outline-primary">Apply filter</button>@if ($selectedStatus)<a
            class="btn btn-link" href="{{ route('shop.repair-jobs.index') }}">Clear</a>@endif
    </form>
    @forelse ($repairJobs as $repairJob)
    <article id="repair-{{ $repairJob->id }}" class="surface p-4 mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <h2 class="h5">{{ $repairJob->job_number }}</h2><span
                class="text-capitalize">{{ str_replace('_', ' ', $repairJob->status) }}</span>
        </div>
        <p>{{ $repairJob->customer->name }} · {{ $repairJob->vehicle->plate_number }}</p>
        <p>{{ $repairJob->description }}</p>
        @if ($repairJob->hasUnbilledBookedService())
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3"
                role="status">
                <div><strong>Booked service needs review</strong>
                    <p class="mb-0">This older repair does not yet list the service chosen in the appointment. Check the
                        work items to avoid a duplicate charge.</p>
                </div>
                @if (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) && !in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true) && !$repairJob->invoice)
                    <form method="POST" action="{{ route('shop.repair-jobs.booked-service', $repairJob) }}">@csrf<button
                            class="btn btn-outline-primary">Add booked service</button></form>
                @else
                    <span class="small">An existing sent estimate or invoice needs manual review before any billing
                        change.</span>
                @endif
            </div>
        @endif
        @php($nextAction = $repairJob->nextAction())
        <p class="repair-next-step mb-3">
            <strong>{{ $nextAction['actor'] === 'None' ? 'Status' : 'Next step for ' . $nextAction['actor'] }}:</strong>
            {{ $nextAction['action'] }}</p>
        <details class="repair-details mb-3" @if ($repairJob->items->isEmpty() && !in_array($repairJob->status, ['completed', 'released', 'cancelled'], true)) open @endif>
            <summary>Work items and cost · PHP {{ number_format($repairJob->actual_cost ?? 0, 2) }}</summary>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Work item</th>
                            <th>Type</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>@forelse ($repairJob->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="text-capitalize">
                                {{ $item->service_id !== null ? 'Booked service' : $item->type }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">₱{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">₱{{ number_format($item->subtotal, 2) }}</td>
                            <td class="text-end">@if ($item->service_id !== null)<span
                            class="text-secondary small">Included</span>@elseif (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) && !in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true) && !$repairJob->invoice && $item->inventoryTransactions->isEmpty())
                                        <form method="POST" action="{{ route('shop.repair-job-items.destroy', $item) }}">@csrf
                                            @method('DELETE')<button class="btn btn-sm btn-outline-danger"
                                    aria-label="Remove {{ $item->description }}">Remove</button></form>@endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="6" class="text-secondary">No itemized work yet.</td>
                        </tr>@endforelse @if ($repairJob->items->isNotEmpty())
                            <tr>
                                <th colspan="4">
                                    {{ in_array($repairJob->status, ['completed', 'released'], true) ? 'Actual cost' : (in_array($repairJob->quotation?->status, ['accepted', 'approved'], true) ? 'Approved price' : 'Proposed price') }}
                                </th>
                                <th class="text-end">₱{{ number_format($repairJob->actual_cost, 2) }}</th>
                                <td></td>
                        </tr>@endif
                    </tbody>
                </table>
            </div>
            @if (in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true))
                <p class="text-secondary small">
                    {{ $repairJob->quotation->status === 'sent' ? 'Estimate awaiting customer approval. Work items are locked.' : 'Customer-approved price: ₱' . number_format($repairJob->quotation->total, 2) . '. Work items are locked.' }}
                </p>
            @elseif (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) && !$repairJob->invoice)
                <form method="POST" action="{{ route('shop.repair-job-items.store', $repairJob) }}"
                    class="row g-2 align-items-end mb-3">@csrf<div class="col-md-2"><label class="form-label">Type<select
                                class="form-select" name="type" required>
                                <option value="labor">Labor</option>
                                <option value="part">Part</option>
                                <option value="service">Service</option>
                                <option value="other">Other</option>
                            </select></label></div>
                    <div class="col-md-4"><label class="form-label">Description<input class="form-control"
                                name="description" maxlength="1000" required></label></div>
                    <div class="col-md-2"><label class="form-label">Quantity<input class="form-control" type="number"
                                name="quantity" min="1" value="1" required></label></div>
                    <div class="col-md-2"><label class="form-label">Unit price<input class="form-control" type="number"
                                name="unit_price" min="0" step="0.01" required></label></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Add item</button></div>
            </form>@endif
            @include('shop.repair-jobs.partials.stock-parts')
        </details>
        <details class="repair-details mb-3">
            <summary>Technicians · {{ $repairJob->technicianAssignments->whereNull('completed_at')->count() }} assigned
            </summary>
            <section class="mb-3" aria-label="Technician assignments">
                <h3 class="h6">Assigned technicians</h3>
                @forelse ($repairJob->technicianAssignments->whereNull('completed_at') as $assignment)
                    <div class="d-flex align-items-center gap-2 mb-2"><span>{{ $assignment->technician->name }}</span><small
                            class="text-secondary">{{ $assignment->notes }}</small>@if (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true))
                                <form method="POST"
                                    action="{{ route('shop.technicians.unassign', [$repairJob, $assignment->technician]) }}"
                                    class="ms-auto">@csrf @method('DELETE')<button
                            class="btn btn-sm btn-outline-secondary">Unassign</button></form>@endif
                    </div>
                @empty
                    @if ($repairJob->technicianAssignments->isNotEmpty())
                        <p class="text-secondary small">Handled by
                            {{ $repairJob->technicianAssignments->pluck('technician.name')->unique()->implode(', ') }}.</p>
                    @else
                        <p class="text-secondary small">No technician assigned yet.</p>
                    @endif
                @endforelse
                @if (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true))
                    @if ($technicians->isNotEmpty())
                        <form method="POST" action="{{ route('shop.technicians.assign', $repairJob) }}"
                            class="d-flex flex-wrap gap-2">@csrf<label class="visually-hidden"
                                for="assign-{{ $repairJob->id }}">Assign technician to
                                {{ $repairJob->job_number }}</label><select id="assign-{{ $repairJob->id }}"
                                class="form-select w-auto" name="technician_id" required>
                                <option value="">Choose technician</option>@foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach
                            </select><button class="btn btn-outline-primary">Assign technician</button></form>
                    @else<a href="{{ route('shop.technicians.index') }}" class="btn btn-sm btn-outline-primary">Add a
                    technician</a>@endif
                @endif
            </section>
        </details>
        <details class="repair-details mb-3">
            <summary>Before & after photos · {{ $repairJob->photos->count() }}</summary>
            <p class="text-secondary small mt-3">Photos stay private to this customer, this shop, and admins.</p>
            @if($repairJob->photos->isNotEmpty())
                <div class="repair-evidence-grid mb-3">
                    @foreach($repairJob->photos as $photo)
                        <figure class="mb-0"><a href="{{ route('repair-photos.show', $photo) }}" target="_blank"
                                rel="noopener"><img class="repair-evidence-image"
                                    src="{{ route('repair-photos.show', $photo) }}"
                                    alt="{{ ucfirst($photo->stage) }} repair photo: {{ $photo->caption ?: $repairJob->vehicle->plate_number }}"
                                    loading="lazy"></a>
                            <figcaption class="small mt-1"><strong>{{ ucfirst($photo->stage) }}</strong>@if($photo->caption) ·
                            {{ $photo->caption }}@endif</figcaption>
                        </figure>
                    @endforeach
                </div>
            @else<p>No repair photos have been recorded yet.</p>@endif
            @if($repairJob->status !== 'cancelled')
                <form method="POST" action="{{ route('shop.repair-photos.store', $repairJob) }}"
                    enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2"><label class="form-label"
                            for="photo-stage-{{ $repairJob->id }}">Stage</label><select
                            id="photo-stage-{{ $repairJob->id }}" name="stage" class="form-select"
                            required>@if($repairJob->started_at || in_array($repairJob->status, ['repairing', 'in_progress', 'waiting_parts', 'completed', 'released'], true))
                            <option value="after">After</option>@else<option value="before">Before</option>@endif
                        </select></div>
                    <div class="col-md-4"><label class="form-label"
                            for="repair-photo-{{ $repairJob->id }}">Photo</label><input
                            id="repair-photo-{{ $repairJob->id }}" name="photo" type="file" class="form-control"
                            accept="image/jpeg,image/png,image/webp" required><small class="form-text">JPG, PNG, or WebP. Up
                            to 5 MB.</small></div>
                    <div class="col-md-4"><label class="form-label" for="photo-caption-{{ $repairJob->id }}">Caption
                            (optional)</label><input id="photo-caption-{{ $repairJob->id }}" name="caption"
                            class="form-control" maxlength="255" placeholder="What does this photo show?"></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Add photo</button></div>
                </form>
            @endif
        </details>
        @if(in_array($repairJob->status, ['completed', 'released'], true))
            <details class="repair-details mb-3">
                <summary>Warranty {{ $repairJob->warranty ? '· issued' : '· not issued' }}</summary>
                @if($repairJob->warranty)
                    <div class="mt-3">
                        <p class="mb-1"><strong>Coverage:</strong> {{ $repairJob->warranty->coverage }}</p>
                        <p class="small text-secondary mb-0">Issued {{ $repairJob->warranty->valid_from->format('M j, Y') }} ·
                            Ends {{ $repairJob->warranty->valid_until->format('M j, Y') }}</p>
                    </div>
                @else
                    <p class="text-secondary small mt-3">Issue a warranty only if your shop offers one for this completed
                        repair. The customer will see exactly what is covered and when it ends.</p>
                    <form method="POST" action="{{ route('shop.repair-warranties.store', $repairJob) }}"
                        class="row g-2 align-items-end">@csrf<div class="col-md-7"><label class="form-label"
                                for="warranty-coverage-{{ $repairJob->id }}">What is covered?</label><textarea
                                id="warranty-coverage-{{ $repairJob->id }}" name="coverage" class="form-control" rows="2"
                                minlength="10" maxlength="2000" required></textarea></div>
                        <div class="col-md-3"><label class="form-label" for="warranty-days-{{ $repairJob->id }}">Duration in
                                days</label><input id="warranty-days-{{ $repairJob->id }}" name="duration_days"
                                class="form-control" type="number" min="1" max="730" required></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Issue warranty</button></div>
                    </form>
                @endif
            </details>
        @endif
        @if($repairJob->serviceCases->isNotEmpty())
            <p class="small mb-3"><a href="{{ route('shop.service-cases.index') }}">{{ $repairJob->serviceCases->count() }}
                    customer aftercare {{ str('request')->plural($repairJob->serviceCases->count()) }} for this repair</a>
        </p>@endif
        <div class="d-flex flex-wrap gap-2 mb-3">
            @if (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) && !in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true) && !$repairJob->hasUnbilledBookedService())<a
                class="btn btn-primary" href="{{ route('shop.quotations.create', $repairJob) }}">Send estimate for approval</a>@endif
            @if (in_array($repairJob->quotation?->status, ['accepted', 'approved'], true) && !in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) && !$repairJob->pendingSupplementalQuotation)
                <a class="btn btn-outline-primary"
                    href="{{ route('shop.quotations.supplement.create', $repairJob) }}">Request approval for extra work</a>
            @elseif($repairJob->pendingSupplementalQuotation)
                <span class="badge text-bg-warning align-self-center">Revised estimate awaiting customer approval</span>
            @endif
            @if (!in_array($repairJob->status, ['completed', 'released', 'cancelled'], true))<a
                class="btn btn-outline-secondary" href="{{ route('shop.inspections.create', $repairJob) }}">Record
            inspection</a>@endif
            @if (in_array($repairJob->status, ['completed', 'released'], true) && !$repairJob->invoice)
                <form method="POST" action="{{ route('shop.invoices.store', $repairJob) }}">@csrf<button
            class="btn btn-success">Issue invoice</button></form>@endif
            @if ($repairJob->invoice)<a class="btn btn-outline-primary"
            href="{{ route('shop.invoices.show', $repairJob->invoice) }}">View invoice</a>@endif
        </div>
        @if ($repairJob->status === 'waiting_approval')
            <p class="text-secondary">
                {{ $repairJob->quotation?->status === 'sent' ? 'Estimate sent. ' . $repairJob->customer->name . ' can approve it in My repairs or Estimates.' : 'No estimate has been sent for this repair. Send one so the customer can approve it.' }}
        </p>@endif
        @if (in_array($repairJob->status, ['completed', 'released'], true))<x-repair-handover :repair-job="$repairJob"
        context="shop" />@endif
        <x-service-journey :repair-job="$repairJob" />
        <x-repair-timeline :repair-job="$repairJob" />
        @php($nextStages = collect($repairJob->availableStatuses())->except('cancelled'))
        @if ($nextStages->isNotEmpty())
            <form method="POST" action="{{ route('shop.repair-jobs.update', $repairJob) }}" class="row g-3">
                @csrf @method('PATCH')
                @if ($nextStages->count() === 1)
                    <input type="hidden" name="status" value="{{ $nextStages->keys()->first() }}">
                @else
                    <div class="col-md-4"><label class="form-label" for="status-{{ $repairJob->id }}">Next stage</label><select
                            id="status-{{ $repairJob->id }}" class="form-select" name="status" required>
                            <option value="" selected disabled>Choose the next stage</option>
                            @foreach ($nextStages as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select></div>
                @endif
                <div class="col-md-4"><label class="form-label"
                        for="cost-{{ $repairJob->id }}">{{ in_array($repairJob->quotation?->status, ['accepted', 'approved'], true) ? 'Approved price (PHP)' : 'Work items total (PHP)' }}</label><input
                        id="cost-{{ $repairJob->id }}" class="form-control" type="text"
                        value="{{ number_format($repairJob->actual_cost ?? 0, 2) }}" readonly><small
                        class="form-text">{{ in_array($repairJob->quotation?->status, ['accepted', 'approved'], true) ? 'Locked to the customer-approved estimate.' : ($repairJob->quotation?->status === 'sent' ? 'Estimate sent; waiting for customer approval.' : ($repairJob->items->isNotEmpty() ? 'Calculated from work items above.' : 'Add work items to calculate this automatically.')) }}</small>
                </div>
                @if ($repairJob->status === 'pending')
                    <div class="col-md-4"><label class="form-label" for="diagnosis-{{ $repairJob->id }}">Diagnosis for
                            customer</label><textarea id="diagnosis-{{ $repairJob->id }}" class="form-control" name="diagnosis"
                rows="2" maxlength="5000">{{ $repairJob->diagnosis }}</textarea></div>@endif
                <div class="col-md-4"><label class="form-label" for="notes-{{ $repairJob->id }}">Technician
                        notes</label><textarea id="notes-{{ $repairJob->id }}" class="form-control" name="technician_notes"
                        rows="2" maxlength="5000">{{ $repairJob->technician_notes }}</textarea></div>
                <div class="col-12"><button
                        class="btn btn-primary">{{ $nextStages->count() === 1 ? $nextStages->first() : 'Update repair stage' }}</button>
                </div>
            </form>
        @elseif (in_array($repairJob->status, ['diagnosis', 'waiting_approval'], true))
            <form method="POST" action="{{ route('shop.repair-jobs.update', $repairJob) }}" class="row g-3">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $repairJob->status }}">
                @if ($repairJob->status === 'diagnosis')
                    <div class="col-md-6"><label class="form-label" for="diagnosis-{{ $repairJob->id }}">Diagnosis for
                            customer</label><textarea id="diagnosis-{{ $repairJob->id }}" class="form-control" name="diagnosis"
                rows="2" maxlength="5000">{{ $repairJob->diagnosis }}</textarea></div>@endif
                <div class="col-md-6"><label class="form-label" for="notes-{{ $repairJob->id }}">Technician
                        notes</label><textarea id="notes-{{ $repairJob->id }}" class="form-control" name="technician_notes"
                        rows="2" maxlength="5000">{{ $repairJob->technician_notes }}</textarea></div>
                <div class="col-12"><button class="btn btn-outline-primary">Save
                        {{ $repairJob->status === 'diagnosis' ? 'diagnosis and notes' : 'technician notes' }}</button></div>
            </form>
        @endif
        @if (array_key_exists('cancelled', $repairJob->availableStatuses()))
            <details class="mt-3">
                <summary class="text-secondary small">Need to cancel this repair?</summary>
                <form method="POST" action="{{ route('shop.repair-jobs.update', $repairJob) }}" class="mt-2">@csrf
                    @method('PATCH')<input type="hidden" name="status" value="cancelled"><button
                        class="btn btn-sm btn-outline-danger"
                        data-confirm="Cancel this repair job? This cannot be undone.">Cancel repair job</button></form>
            </details>
        @endif
    </article>
    @empty
    <div class="surface p-4">
        <h2 class="h5">{{ $selectedStatus ? 'No repairs in this stage' : 'No repair jobs yet' }}</h2>
        <p class="text-secondary">
            {{ $selectedStatus ? 'Choose another stage or view all repair jobs.' : 'Start with a confirmed appointment or an approved estimate.' }}
        </p><a
            href="{{ $selectedStatus ? route('shop.repair-jobs.index') : route('shop.repair-jobs.create') }}">{{ $selectedStatus ? 'View all repair jobs' : 'Create a repair job' }}</a>
    </div>
    @endforelse
    <div class="mt-3">{{ $repairJobs->links() }}</div>
</x-layouts.app>
