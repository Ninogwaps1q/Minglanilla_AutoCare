<x-layouts.app title="My repairs">
    <div class="page-heading">
        <h1>My repairs</h1>
        <p>Track your vehicle's repair status and review the shop's updates.</p>
    </div>
    @forelse ($repairJobs as $repairJob)
        <article class="surface p-4 mb-3" aria-labelledby="repair-{{ $repairJob->id }}">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 id="repair-{{ $repairJob->id }}" class="h5 mb-1">{{ $repairJob->job_number }}</h2>
                    <p class="text-secondary mb-0">{{ $repairJob->shop->name }} · {{ $repairJob->vehicle->plate_number }}</p>
                </div>
                <div><span class="badge {{ in_array($repairJob->status, ['completed', 'released'], true) ? 'text-bg-success' : 'text-bg-secondary' }} text-capitalize">{{ str_replace('_', ' ', $repairJob->status) }}</span></div>
            </div>
            <p>{{ $repairJob->description }}</p>
            @if ($repairJob->diagnosis)
                <p><strong>Diagnosis:</strong> {{ $repairJob->diagnosis }}</p>
            @endif
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
