<x-layouts.app title="Repair jobs">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3"><div><h1>Repair jobs</h1><p>Manage diagnosis, customer approval, repairs, and vehicle release.</p></div><div><a class="btn btn-primary" href="{{ route('shop.repair-jobs.create') }}">Create repair job</a></div></div>
    @include('components.form-errors')
    @forelse ($repairJobs as $repairJob)
        <article class="surface p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5">{{ $repairJob->job_number }}</h2><span class="text-capitalize">{{ str_replace('_', ' ', $repairJob->status) }}</span></div>
            <p>{{ $repairJob->customer->name }} · {{ $repairJob->vehicle->plate_number }}</p><p>{{ $repairJob->description }}</p>
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
