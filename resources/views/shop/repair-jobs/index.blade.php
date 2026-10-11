<x-layouts.app title="Repair jobs">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3"><div><h1>Repair jobs</h1><p>Manage diagnosis, customer approval, repairs, and vehicle release.</p></div><div><a class="btn btn-primary" href="{{ route('shop.repair-jobs.create') }}">Create repair job</a></div></div>
    @include('components.form-errors')
    @forelse ($repairJobs as $repairJob)
        <article class="surface p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5">{{ $repairJob->job_number }}</h2><span class="text-capitalize">{{ str_replace('_', ' ', $repairJob->status) }}</span></div>
            <p>{{ $repairJob->customer->name }} · {{ $repairJob->vehicle->plate_number }}</p><p>{{ $repairJob->description }}</p>
            <div class="table-responsive mb-3"><table class="table table-sm align-middle"><thead><tr><th>Work item</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Subtotal</th><th></th></tr></thead><tbody>@forelse ($repairJob->items as $item)<tr><td>{{ $item->description }}</td><td class="text-capitalize">{{ $item->type }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">₱{{ number_format($item->unit_price, 2) }}</td><td class="text-end">₱{{ number_format($item->subtotal, 2) }}</td><td class="text-end">@if (! in_array($repairJob->status, ['released', 'cancelled'], true))<form method="POST" action="{{ route('shop.repair-job-items.destroy', $item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="Remove {{ $item->description }}">Remove</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="text-secondary">No itemized work yet.</td></tr>@endforelse @if ($repairJob->items->isNotEmpty())<tr><th colspan="4">Itemized total</th><th class="text-end">₱{{ number_format($repairJob->items->sum('subtotal'), 2) }}</th><td></td></tr>@endif</tbody></table></div>
            @if (! in_array($repairJob->status, ['released', 'cancelled'], true))<form method="POST" action="{{ route('shop.repair-job-items.store', $repairJob) }}" class="row g-2 align-items-end mb-3">@csrf<div class="col-md-2"><label class="form-label">Type<select class="form-select" name="type" required><option value="labor">Labor</option><option value="part">Part</option><option value="service">Service</option><option value="other">Other</option></select></label></div><div class="col-md-4"><label class="form-label">Description<input class="form-control" name="description" maxlength="1000" required></label></div><div class="col-md-2"><label class="form-label">Quantity<input class="form-control" type="number" name="quantity" min="1" value="1" required></label></div><div class="col-md-2"><label class="form-label">Unit price<input class="form-control" type="number" name="unit_price" min="0" step="0.01" required></label></div><div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Add item</button></div></form>@endif
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('shop.inspections.create', $repairJob) }}">Inspect</a>
                @if (! in_array($repairJob->status, ['completed', 'released', 'cancelled'], true))<a class="btn btn-sm btn-outline-primary" href="{{ route('shop.quotations.create', $repairJob) }}">Send estimate</a>@endif
                @if (in_array($repairJob->status, ['completed', 'released'], true) && ! $repairJob->invoice)<form method="POST" action="{{ route('shop.invoices.store', $repairJob) }}">@csrf<button class="btn btn-sm btn-success">Issue invoice</button></form>@endif
            </div>
            @if ($repairJob->status === 'waiting_approval')<p class="text-secondary">Waiting for the customer to accept an estimate. Send a revised estimate if needed.</p>@endif
            @if ($repairJob->availableStatuses())
                <form method="POST" action="{{ route('shop.repair-jobs.update', $repairJob) }}" class="row g-3">
                    @csrf @method('PATCH')
                    <div class="col-md-4"><label class="form-label" for="status-{{ $repairJob->id }}">Next stage</label><select id="status-{{ $repairJob->id }}" class="form-select" name="status" required>@foreach ($repairJob->availableStatuses() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="cost-{{ $repairJob->id }}">Actual cost (PHP)</label><input id="cost-{{ $repairJob->id }}" class="form-control" type="number" min="0" step="0.01" name="actual_cost" value="{{ $repairJob->actual_cost }}"></div>
                    <div class="col-md-4"><label class="form-label" for="notes-{{ $repairJob->id }}">Technician notes</label><textarea id="notes-{{ $repairJob->id }}" class="form-control" name="technician_notes" rows="2" maxlength="5000">{{ $repairJob->technician_notes }}</textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Update repair</button></div>
                </form>
            @endif
        </article>
    @empty
        <div class="surface p-4">No repair jobs yet. Start with a confirmed appointment or accepted quotation.</div>
    @endforelse
</x-layouts.app>
