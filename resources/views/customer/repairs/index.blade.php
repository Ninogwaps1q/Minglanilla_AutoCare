<x-layouts.app title="My repairs">
    <div class="page-heading">
        <h1>My repairs</h1>
        <p>Review estimates, approve work, and track your vehicle's repair status.</p>
    </div>
    @if (request()->integer('appointment_id') > 0)<p><a href="{{ route('customer.repairs.index') }}">View all repairs</a></p>@endif
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
            <dl class="row mb-0">
                <dt class="col-sm-4">Estimated cost</dt>
                <dd class="col-sm-8">{{ $repairJob->estimated_cost !== null ? 'PHP '.number_format($repairJob->estimated_cost, 2) : 'Awaiting estimate' }}</dd>
                <dt class="col-sm-4">Actual cost</dt>
                <dd class="col-sm-8">{{ $repairJob->actual_cost !== null ? 'PHP '.number_format($repairJob->actual_cost, 2) : 'Not finalized' }}</dd>
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
