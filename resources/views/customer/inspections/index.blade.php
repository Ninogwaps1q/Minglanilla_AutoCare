<x-layouts.app title="My inspections">
    <div class="page-heading"><h1>My inspections</h1><p>Review vehicle conditions and recommendations recorded by your shop.</p></div>
    @forelse ($inspections as $inspection)
        <article class="surface p-4 mb-3">
            <h2 class="h5">{{ $inspection->vehicle->plate_number }} · {{ $inspection->shop->name }}</h2>
            <p>{{ $inspection->inspection_date->format('M j, Y') }} · <strong>{{ $inspection->overall_condition }}</strong></p>
            @if ($inspection->notes)<p>{{ $inspection->notes }}</p>@endif
            <div class="table-responsive"><table class="table mb-0"><caption class="visually-hidden">Inspection results</caption><thead><tr><th scope="col">Item</th><th scope="col">Condition</th><th scope="col">Recommendation</th></tr></thead><tbody>
                @foreach ($inspection->items as $item)<tr><td>{{ $item->category }}: {{ $item->item_name }}</td><td>{{ $item->condition }}</td><td>{{ $item->recommendation ?? 'None recorded' }}</td></tr>@endforeach
            </tbody></table></div>
        </article>
    @empty
        <div class="surface p-4">No vehicle inspections recorded yet.</div>
    @endforelse
    {{ $inspections->links() }}
</x-layouts.app>
