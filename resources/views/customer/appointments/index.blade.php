<x-layouts.app title="My appointments">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>My appointments</h1>
            <p>Review your booking requests and their current shop-confirmation status.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('customer.appointments.create') }}">Book a service</a>
    </div>

    <form method="GET" action="{{ route('customer.appointments.index') }}" class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-2">
        <div><label for="appointment-status-filter" class="form-label">Show appointments</label><select id="appointment-status-filter" class="form-select" name="status"><option value="">All statuses</option>@foreach (['pending' => 'Waiting for shop', 'confirmed' => 'Confirmed', 'in_progress' => 'In repair', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach</select></div>
        <button class="btn btn-outline-primary">Apply filter</button>
        @if ($selectedStatus)<a class="btn btn-link" href="{{ route('customer.appointments.index') }}">Clear</a>@endif
    </form>

    <div class="surface overflow-hidden">
        <div class="list-group list-group-flush">
            <x-form-errors />
            @forelse ($appointments as $appointment)
                <article class="list-group-item p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <p class="text-secondary mb-1">{{ $appointment->appointment_date->format('M j, Y') }} at {{ $appointment->appointment_time->format('g:i A') }}</p>
                            <h2 class="h5 mb-2">{{ $appointment->shop->name }}</h2>
                            <p class="mb-0">{{ $appointment->serviceCase ? 'Follow-up visit' : ($appointment->service?->name ?? 'Vehicle service') }} · {{ $appointment->vehicle->plate_number }}</p>
                            @if ($appointment->reason)
                                <p class="text-secondary small mb-0 mt-2">{{ $appointment->reason }}</p>
                            @endif
                            @if ($appointment->serviceCase)
                                <a class="small" href="{{ route('customer.service-cases.index', ['focus' => $appointment->serviceCase->id]) }}">View follow-up request</a>
                            @endif
                        </div>
                        <div class="d-flex flex-column align-items-md-end gap-2">
                            <span class="badge text-bg-{{ $appointment->status === 'confirmed' ? 'success' : ($appointment->status === 'pending' ? 'warning' : 'secondary') }}">{{ match ($appointment->status) { 'pending' => 'Waiting for shop', 'confirmed' => 'Confirmed', 'in_progress' => 'In repair', 'completed' => 'Completed', default => 'Cancelled' } }}</span>
                            @if ($appointment->repairJob)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('customer.repairs.index', ['appointment_id' => $appointment->id]) }}">View repair progress</a>
                            @elseif ($appointment->status === 'confirmed')
                                <span class="small text-secondary">The shop will start the service.</span>
                            @endif
                            @if ($appointment->status === 'confirmed' && ! $appointment->repairJob)
                                @if ($appointment->latestRescheduleRequest?->status === 'pending')
                                    <p class='small text-secondary mb-0'>Time change requested for {{ $appointment->latestRescheduleRequest->requested_date->format('M j') }} at {{ $appointment->latestRescheduleRequest->requested_time->format('g:i A') }}. Waiting for shop approval.</p>
                                @else
                                    @if ($appointment->latestRescheduleRequest?->status === 'declined')
                                        <p class='small text-secondary mb-0'>The shop kept your original time. You can request a different slot.</p>
                                    @endif
                                    <details class='appointment-more-actions'>
                                        <summary>Need another time?</summary>
                                        <form method='POST' action='{{ route('customer.appointments.reschedule.request', $appointment) }}' class='mt-2 d-grid gap-2'>
                                            @csrf
                                            <label class='form-label mb-0' for='reschedule-date-{{ $appointment->id }}'>New date</label>
                                            <input id='reschedule-date-{{ $appointment->id }}' name='requested_date' type='date' min='{{ today()->toDateString() }}' class='form-control form-control-sm' required>
                                            <label class='form-label mb-0' for='reschedule-time-{{ $appointment->id }}'>New time</label>
                                            <input id='reschedule-time-{{ $appointment->id }}' name='requested_time' type='time' class='form-control form-control-sm' required>
                                            <label class='form-label mb-0' for='reschedule-reason-{{ $appointment->id }}'>Reason (optional)</label>
                                            <input id='reschedule-reason-{{ $appointment->id }}' name='reason' type='text' maxlength='500' class='form-control form-control-sm' placeholder='For example, my availability changed'>
                                            <button class='btn btn-sm btn-outline-primary' type='submit'>Ask shop to change time</button>
                                            <small class='text-secondary'>Your original time stays reserved. The new slot is not held until approved.</small>
                                        </form>
                                    </details>
                                @endif
                            @endif
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
                <div class="p-4"><h2 class="h5">{{ $selectedStatus ? 'No appointments with this status' : 'No appointments yet' }}</h2><p class="text-secondary mb-2">{{ $selectedStatus ? 'Try another status or view all appointments.' : 'Choose a verified shop and request a convenient service time.' }}</p><a href="{{ $selectedStatus ? route('customer.appointments.index') : route('customer.appointments.create') }}">{{ $selectedStatus ? 'View all appointments' : 'Book a service' }}</a></div>
            @endforelse
        </div>
    </div>
    <div class="mt-3">{{ $appointments->links() }}</div>
</x-layouts.app>
