<x-layouts.app title="Shop appointments">
    <div class="page-heading">
        <h1>Shop appointments</h1>
        <p>Confirm requests, then start the repair from the same appointment.</p>
    </div>
    <x-form-errors />

    <form method="GET" action="{{ route('shop.appointments.index') }}" class="surface p-3 mb-3 appointment-filter">
        <div>
            <label class="form-label" for="shop-appointment-status">Show appointments</label>
            <div class="appointment-filter-fields">
                <select id="shop-appointment-status" class="form-select" name="status"><option value="">All statuses</option>@foreach (['pending' => 'Needs decision', 'confirmed' => 'Ready for repair', 'in_progress' => 'In repair', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach</select>
                <button class="btn btn-outline-primary">Apply filter</button>
                @if ($selectedStatus)<a class="btn btn-link" href="{{ route('shop.appointments.index') }}">Clear</a>@endif
            </div>
        </div>
        <p class="appointment-filter-count">{{ $appointments->total() }} {{ str('appointment')->plural($appointments->total()) }}</p>
    </form>

    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table appointment-table align-middle mb-0">
                <thead>
                    <tr><th scope="col">Schedule</th><th scope="col">Customer & vehicle</th><th scope="col">Service</th><th scope="col">Status</th><th scope="col" class="text-end">Next action</th></tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td data-label="Schedule"><strong>{{ $appointment->appointment_date->format('M j, Y') }}</strong><br><span class="text-secondary small">{{ $appointment->appointment_time->format('g:i A') }}</span></td>
                            <td data-label="Customer & vehicle">{{ $appointment->customer->name }}<br><span class="text-secondary small">{{ $appointment->vehicle->plate_number }}</span></td>
                            <td data-label="Service">{{ $appointment->service->name }}@if ($appointment->reason)<br><span class="text-secondary small">{{ $appointment->reason }}</span>@endif</td>
                            <td data-label="Status"><span class="badge text-bg-{{ $appointment->status === 'confirmed' ? 'success' : ($appointment->status === 'pending' ? 'warning' : 'secondary') }}">{{ match ($appointment->status) { 'pending' => 'Needs decision', 'confirmed' => 'Ready for repair', 'in_progress' => 'In repair', 'completed' => 'Completed', default => 'Cancelled' } }}</span></td>
                            <td data-label="Next action" class="appointment-action-cell">
                                <div class="appointment-actions">
                                    @if ($appointment->repairJob)
                                        <a class="btn btn-sm btn-outline-primary appointment-main-action" href="{{ route('shop.repair-jobs.index', ['appointment_id' => $appointment->id]) }}">View repair</a>
                                    @elseif ($appointment->status === 'confirmed')
                                        <a class="btn btn-sm btn-primary appointment-main-action" href="{{ route('shop.repair-jobs.create', ['appointment_id' => $appointment->id]) }}">Start repair job</a>
                                    @endif
                                    @if ($appointment->availableStatusTransitions() && ! $appointment->repairJob)
                                        @if ($appointment->status === 'confirmed')
                                            <details class="appointment-more-actions">
                                                <summary>Other appointment actions</summary>
                                        @endif
                                        <form method="POST" action="{{ route('shop.appointments.update', $appointment) }}" class="appointment-status-form">
                                            @csrf
                                            @method('PATCH')
                                            <select class="form-select form-select-sm" name="status" aria-label="Appointment status" required>
                                                <option value="" selected disabled>Choose action</option>
                                                @foreach ($appointment->availableStatusTransitions() as $status => $label)
                                                    <option value="{{ $status }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Update status</button>
                                        </form>
                                        @if ($appointment->status === 'confirmed')
                                            </details>
                                        @endif
                                    @elseif (! $appointment->repairJob)
                                        <span class="small text-secondary">No action needed</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><strong>{{ $selectedStatus ? 'No appointments with this status' : 'No appointment requests yet' }}</strong><br><span class="text-secondary">{{ $selectedStatus ? 'Choose another status or clear the filter.' : 'New customer requests will appear here.' }}</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $appointments->links() }}</div>
</x-layouts.app>
