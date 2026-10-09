<x-layouts.app :title="$vehicle->plate_number">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div>
            <p class="text-secondary mb-1">{{ $vehicle->plate_number }}</p>
            <h1>{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h1>
            <p class="mb-0">{{ number_format($vehicle->current_mileage) }} km</p>
        </div>
        <div><a class="btn btn-primary" href="{{ route('customer.vehicles.passport', $vehicle) }}">Service passport</a>
            <a class="btn btn-outline-primary" href="{{ route('customer.vehicles.edit', $vehicle) }}">Edit</a>
            <form class="d-inline" method="POST" action="{{ route('customer.vehicles.destroy', $vehicle) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">Remove</button></form>
        </div>
    </div>
    <x-uploaded-image class="vehicle-hero-photo mt-4" :path="$vehicle->photo" :alt="$vehicle->plate_number.' vehicle photo'" fallback-icon="car-front" />
    <section class="mt-5">
        <h2 class="h3 mb-3">Service history</h2>
        <div class="surface overflow-hidden">
            <div class="list-group list-group-flush">@forelse ($vehicle->serviceRecords as $record)<div class="list-group-item p-3"><strong>{{ $record->service_type }}</strong><span class="text-secondary"> · {{ $record->service_date->format('M j, Y') }}</span>
                    <p class="mb-0 mt-1">{{ $record->shop->name }} · ₱{{ number_format($record->total_cost, 2) }}</p>
                </div>@empty<div class="list-group-item p-3 text-secondary">No service records yet.</div>@endforelse</div>
        </div>
    </section>
</x-layouts.app>
