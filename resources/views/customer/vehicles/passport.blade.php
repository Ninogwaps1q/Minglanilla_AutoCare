<x-layouts.app title="Vehicle service passport">
    <div class="page-heading"><h1>Vehicle service passport</h1><p>Your private vehicle profile and recorded service history.</p></div>
    <div class="surface p-4 mb-4 d-flex flex-column flex-md-row gap-4 align-items-start">
        <img src="{{ route('customer.vehicles.passport.qr', $vehicle) }}" width="240" height="240" class="img-fluid" alt="QR code linking to this private vehicle passport">
        <div>
            <h2 class="h3">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h2>
            <p>{{ $vehicle->plate_number }} · {{ number_format($vehicle->current_mileage) }} km</p>
            <p class="text-secondary">Scanning this code requires the vehicle owner's login. It does not grant public access to personal information or service records.</p>
            <p class="small text-secondary">Scanning from another device requires this application's address to be reachable from that device.</p>
            <div class="d-flex flex-wrap gap-2 d-print-none">
                <a class="btn btn-primary" download="vehicle-passport.svg" href="{{ route('customer.vehicles.passport.qr', $vehicle) }}">Download QR</a>
                <button class="btn btn-outline-primary" type="button" onclick="window.print()">Print passport</button>
                <a class="btn btn-outline-secondary" href="{{ route('customer.vehicles.show', $vehicle) }}">Back to vehicle</a>
            </div>
        </div>
    </div>
    <h2 class="h4">Service history</h2>
    <div class="surface table-responsive">
        <table class="table mb-0"><thead><tr><th scope="col">Date</th><th scope="col">Service</th><th scope="col">Shop</th><th scope="col">Cost</th></tr></thead><tbody>
            @forelse ($records as $record)
                <tr><td>{{ $record->service_date->format('M j, Y') }}</td><td>{{ $record->service_type }}</td><td>{{ $record->shop?->name ?? 'Unavailable' }}</td><td>PHP {{ number_format($record->total_cost, 2) }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-4 text-secondary">No service records yet.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
