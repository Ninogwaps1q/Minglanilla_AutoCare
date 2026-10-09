<x-layouts.app title="Shop appointments">
    <div class="page-heading">
        <h1>Shop appointments</h1>
        <p>Review service requests and keep customers informed as their booking status changes.</p>
    </div>

    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Schedule</th><th>Customer & vehicle</th><th>Service</th><th>Status</th><th class="text-end">Update</th></tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td><strong>{{ $appointment->appointment_date->format('M j, Y') }}</strong><br><span class="text-secondary small">{{ $appointment->appointment_time->format('g:i A') }}</span></td>
                            <td>{{ $appointment->customer->name }}<br><span class="text-secondary small">{{ $appointment->vehicle->plate_number }}</span></td>
                            <td>{{ $appointment->service->name }}@if ($appointment->reason)<br><span class="text-secondary small">{{ $appointment->reason }}</span>@endif</td>
                            <td><span class="badge text-bg-{{ $appointment->status === 'confirmed' ? 'success' : ($appointment->status === 'pending' ? 'warning' : 'secondary') }} text-capitalize">{{ $appointment->status }}</span></td>
                            <td>
                                @if ($appointment->availableStatusTransitions())
                                    <form method="POST" action="{{ route('shop.appointments.update', $appointment) }}" class="d-flex justify-content-end gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select class="form-select form-select-sm" name="status" aria-label="Appointment status">
                                            @foreach ($appointment->availableStatusTransitions() as $status => $label)
                                                <option value="{{ $status }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-secondary">No appointment requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
