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
            </article>
        @empty
            <div class="list-group-item p-4 text-secondary">No service records yet. Completed repairs will appear here.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
