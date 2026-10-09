<x-layouts.app title="My vehicles">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>My vehicles</h1>
            <p>Keep each vehicle ready for bookings and service tracking.</p>
        </div><a class="btn btn-primary" href="{{ route('customer.vehicles.create') }}">Add vehicle</a>
    </div>
    <div class="row g-3">@forelse ($vehicles as $vehicle)
        <div class="col-md-6 col-xl-4">
            <article class="surface vehicle-card h-100 overflow-hidden">
                <x-uploaded-image class="vehicle-card-photo" :path="$vehicle->photo" :alt="$vehicle->plate_number.' vehicle photo'" fallback-icon="car-front" />
                <div class="p-4"><p class="text-secondary mb-2">{{ $vehicle->plate_number }}</p>
                <h2 class="h4">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h2>
                <p class="mb-3">{{ number_format($vehicle->current_mileage) }} km</p><a class="btn btn-outline-primary"
                    href="{{ route('customer.vehicles.show', $vehicle) }}">View details</a></div>
            </article>
        </div>@empty<div class="col-12">
            <div class="surface p-4 text-secondary">No vehicles yet. Add your first vehicle to start tracking its
                service history.</div>
        </div>@endforelse
    </div>
</x-layouts.app>
