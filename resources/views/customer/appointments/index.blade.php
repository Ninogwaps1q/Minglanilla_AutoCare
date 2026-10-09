<x-layouts.app title="My appointments">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>My appointments</h1>
            <p>Review your booking requests and their current shop-confirmation status.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('customer.appointments.create') }}">Book an appointment</a>
    </div>

    <div class="surface overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse ($appointments as $appointment)
                <article class="list-group-item p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <p class="text-secondary mb-1">{{ $appointment->appointment_date->format('M j, Y') }} at {{ $appointment->appointment_time->format('g:i A') }}</p>
                            <h2 class="h5 mb-2">{{ $appointment->shop->name }}</h2>
                            <p class="mb-0">{{ $appointment->service->name }} · {{ $appointment->vehicle->plate_number }}</p>
                            @if ($appointment->reason)
                                <p class="text-secondary small mb-0 mt-2">{{ $appointment->reason }}</p>
                            @endif
                        </div>
                        <div class="d-flex flex-column align-items-md-end gap-2">
                            <span class="badge text-bg-{{ $appointment->status === 'confirmed' ? 'success' : ($appointment->status === 'pending' ? 'warning' : 'secondary') }} text-capitalize">{{ $appointment->status }}</span>
                            @if ($appointment->status === 'pending')
                                <form method="POST" action="{{ route('customer.appointments.destroy', $appointment) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Cancel request</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-4 text-secondary">No appointments yet. Choose a verified shop and request a convenient service time.</div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
