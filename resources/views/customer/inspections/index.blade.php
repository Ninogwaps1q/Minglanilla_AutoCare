<x-layouts.app title="My inspections">
    <div class="page-heading"><h1>My inspections</h1><p>Review vehicle conditions and recommendations recorded by your shop.</p></div>
    @forelse ($inspections as $inspection)
        <article class="surface p-4 mb-3">
            <h2 class="h5">{{ $inspection->vehicle->plate_number }} · {{ $inspection->shop->name }}</h2>
            <p><strong>{{ $inspection->overall_condition }}</strong></p>
            <x-inspection-checklist :inspection="$inspection" />
        </article>
    @empty
        <div class="surface p-4">No vehicle inspections recorded yet.</div>
    @endforelse
    {{ $inspections->links() }}
</x-layouts.app>
