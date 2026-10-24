<x-layouts.app title="Service history">
    <div class="page-heading">
        <h1>Service history</h1>
        <p>A chronological record of completed vehicle services.</p>
    </div>
    <div class="surface list-group list-group-flush">
        @forelse ($records as $record)
            <article class="list-group-item p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="h5 mb-0">{{ $record->service_type }}</h2>
                    <time datetime="{{ $record->service_date->toDateString() }}">{{ $record->service_date->format('M j, Y') }}</time>
                </div>
                <p class="text-secondary mt-2 mb-0">{{ $record->vehicle->plate_number }} · {{ $record->shop->name }} · PHP {{ number_format($record->total_cost, 2) }}</p>
                @if ($record->description)<p class="mt-2 mb-1">{{ $record->description }}</p>@endif
                @if ($record->parts_used)<p class="small mb-1"><strong>Parts replaced:</strong> {{ $record->parts_used }}</p>@endif
                <p class="small mb-2">Labor and services: PHP {{ number_format($record->labor_cost, 2) }} · Parts: PHP {{ number_format($record->parts_cost, 2) }}</p>
                @if ($record->technician_notes)<p class="small text-secondary mb-2"><strong>Technician notes:</strong> {{ $record->technician_notes }}</p>@endif
                <div class="d-flex flex-wrap gap-3">
                    @if ($record->repairJob)<a href="{{ route('customer.repairs.index', ['repair_job_id' => $record->repairJob->id]) }}#repair-{{ $record->repairJob->id }}">View repair photos and warranty</a>@endif
                    @if ($record->repairJob?->invoice)<a href="{{ route('customer.invoices.show', $record->repairJob->invoice) }}">View invoice</a>@endif
                    <a href="{{ route('customer.reminders.index') }}">Maintenance planner</a>
                </div>
            </article>
        @empty
            <div class="list-group-item p-4 text-secondary">No service records yet. Completed repairs will appear here.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
