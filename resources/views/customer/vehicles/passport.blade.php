<x-layouts.app title="Vehicle service passport">
    <div class="page-heading"><h1>Vehicle service passport</h1><p>Your private vehicle profile and recorded service history.</p></div>
    <section class="surface p-4 mb-4" aria-labelledby="passport-vehicle-title">
        <h2 id="passport-vehicle-title" class="h3">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h2>
        <p class="mb-3">{{ $vehicle->plate_number }} · {{ number_format($vehicle->current_mileage) }} km</p>
        <div class="d-flex flex-wrap gap-2 d-print-none"><button class="btn btn-outline-primary" type="button" onclick="window.print()">Print my passport</button><a class="btn btn-outline-secondary" href="{{ route('customer.vehicles.show', $vehicle) }}">Back to vehicle</a></div>
    </section>
    <section class="surface p-4 mb-4 d-print-none" aria-labelledby="passport-share-title">
        <h2 id="passport-share-title" class="h4">Share with a verified shop</h2>
        <p class="text-secondary">Create a read-only link when you want a shop to review this vehicle's service history. Only an approved shop owner who signs in can open it. The link expires after 24 hours, and you can revoke it earlier.</p>
        <p class="small text-secondary">For a shop to scan the QR on another device, this site must have an address that device can reach. A localhost link works only on this computer.</p>
        @if ($activeShare)
            <div class="d-flex flex-column flex-md-row gap-4 align-items-start">
                <img src="{{ route('customer.vehicles.passport.shares.qr', [$vehicle, $activeShare]) }}" width="220" height="220" class="img-fluid" alt="QR code for the temporary shop sharing link">
                <div class="flex-grow-1 min-w-0 w-100">
                    <p class="fw-semibold mb-2">Available until {{ $activeShare->expires_at->format('M j, Y g:i A') }}</p>
                    <label class="form-label" for="passport-share-url">Shop sharing link</label>
                    <div class="input-group mb-2"><input id="passport-share-url" class="form-control" type="text" value="{{ $shareUrl }}" readonly aria-describedby="passport-share-help"><button class="btn btn-outline-primary" type="button" data-copy-target="passport-share-url">Copy link</button></div>
                    <p id="passport-share-help" class="small text-secondary mb-3">Anyone holding this link can share it with an approved shop owner. Do not post it publicly. The shop must sign in to view it.</p>
                    <p class="small mb-3" data-copy-status aria-live="polite"></p>
                    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" download="shared-vehicle-passport.svg" href="{{ route('customer.vehicles.passport.shares.qr', [$vehicle, $activeShare]) }}">Download shop QR</a><form method="POST" action="{{ route('customer.vehicles.passport.shares.destroy', [$vehicle, $activeShare]) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit" data-confirm="Revoke this shop sharing link now?">Revoke access</button></form></div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('customer.vehicles.passport.shares.store', $vehicle) }}">@csrf<button class="btn btn-primary" type="submit">Create 24-hour shop link</button></form>
        @endif
    </section>
    <details class="surface p-4 mb-4 d-print-none">
        <summary class="fw-semibold">My private QR (owner login only)</summary>
        <div class="d-flex flex-column flex-md-row gap-3 align-items-start mt-3"><img src="{{ route('customer.vehicles.passport.qr', $vehicle) }}" width="180" height="180" class="img-fluid" alt="QR code linking to your private vehicle passport"><div><p class="text-secondary">This QR opens only for you when you are signed in. Use the shop sharing link above if a shop needs to review the history.</p><a class="btn btn-outline-primary" download="vehicle-passport.svg" href="{{ route('customer.vehicles.passport.qr', $vehicle) }}">Download QR</a></div></div>
    </details>
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
