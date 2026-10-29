<x-layouts.app title="Maintenance planner">
    <div class="page-heading"><h1>Maintenance planner</h1><p>Keep reminders, recurring service schedules, and smart recommendations together.</p></div>
    <x-form-errors />
    @if ($vehicles->isEmpty())
        <section class="surface p-4 mb-4">
            <h2 class="h5">Add your vehicle to get started</h2>
            <p class="text-secondary">Link reminders and service schedules to a vehicle in your garage.</p>
            <a class="btn btn-primary" href="{{ route('customer.vehicles.create') }}">Add a vehicle</a>
        </section>
    @else
    <div class="vstack gap-3 mb-4">
        <details class="surface workspace-disclosure" @if (old('_planner_form') === 'reminder') open @endif>
            <summary>Add a reminder</summary>
            <div class="workspace-disclosure__body">
                <p class="text-secondary">Choose a date for a one-time reminder. All categories, including mileage notes, use your selected date.</p>
                <form method="POST" action="{{ route('customer.reminders.store') }}" class="row g-3">@csrf<input type="hidden" name="_planner_form" value="reminder">
                    <div class="col-md-6"><label class="form-label mb-0">Vehicle<select class="form-select mt-1" name="vehicle_id" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('_planner_form') === 'reminder' && old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }} · {{ $vehicle->make }}</option>@endforeach</select>
                    </label></div>
                    <div class="col-md-6"><label class="form-label mb-0">Title<input class="form-control mt-1" name="title" maxlength="255" required value="{{ old('_planner_form') === 'reminder' ? old('title') : '' }}">
                    </label></div>
                    <div class="col-md-6"><label class="form-label mb-0">Remind me on<input class="form-control mt-1" type="date" name="reminder_date" required value="{{ old('_planner_form') === 'reminder' ? old('reminder_date') : '' }}">
                    </label></div>
                    <div class="col-md-6"><label class="form-label mb-0">Reminder category<select class="form-select mt-1" name="type">
                    <option value="date" @selected(old('_planner_form') === 'reminder' && old('type') === 'date')>Date-based</option>
                    <option value="mileage" @selected(old('_planner_form') === 'reminder' && old('type') === 'mileage')>Mileage note</option>
                    <option value="custom" @selected(old('_planner_form') === 'reminder' && old('type') === 'custom')>Custom</option>
                    </select>
                    </label></div>
                    <div class="col-12"><label class="form-label mb-0">Details<textarea class="form-control mt-1" name="message" rows="3" maxlength="1000" required>{{ old('_planner_form') === 'reminder' ? old('message') : '' }}</textarea>
                    </label></div>
                    <div class="col-12"><button class="btn btn-primary">Save reminder</button></div>
                    </form>
            </div>
        </details>
        <details class="surface workspace-disclosure" @if (old('_planner_form') === 'schedule') open @endif>
            <summary>Set a recurring service schedule</summary>
            <div class="workspace-disclosure__body">
                <p class="text-secondary">Enter a days interval with the last service date, a kilometre interval with the last service mileage, or both. A starting mileage of 0 km is valid.</p>
                <form method="POST" action="{{ route('customer.maintenance-schedules.store') }}" class="row g-3">@csrf<input type="hidden" name="_planner_form" value="schedule">
                    <div class="col-md-6">
                    <label class="form-label">Vehicle<select class="form-select" name="vehicle_id" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('_planner_form') === 'schedule' && old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }}</option>@endforeach</select>
                    </label>
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Service<select class="form-select" name="service_id">
                    <option value="">General maintenance</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('_planner_form') === 'schedule' && old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach</select>
                    </label>
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Last service date<input class="form-control" type="date" name="last_service_date" max="{{ today()->toDateString() }}" value="{{ old('_planner_form') === 'schedule' ? old('last_service_date') : '' }}">
                    </label>
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Last service mileage (km)<input class="form-control" type="number" name="last_service_mileage" min="0" value="{{ old('_planner_form') === 'schedule' ? old('last_service_mileage') : '' }}">
                    </label>
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Repeat every (days)<input class="form-control" type="number" name="recommended_interval_days" min="1" max="3650" value="{{ old('_planner_form') === 'schedule' ? old('recommended_interval_days') : '' }}">
                    </label>
                    </div>
                    <div class="col-md-6">
                    <label class="form-label">Repeat every (kilometres)<input class="form-control" type="number" name="recommended_interval_mileage" min="1" max="500000" value="{{ old('_planner_form') === 'schedule' ? old('recommended_interval_mileage') : '' }}">
                    </label>
                    </div>
                    <div class="col-12">
                    <button class="btn btn-primary">Create schedule</button>
                    </div>
                    </form>
            </div>
        </details>
    </div>
    @endif
    <section class="surface overflow-hidden mb-4"><div class="p-4 pb-2"><h2 class="h5">Upcoming reminders</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Reminder</th><th>Vehicle</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>@forelse($reminders as $reminder)<tr><td><strong>{{ $reminder->title }}</strong><br><span class="small text-secondary">{{ $reminder->message }}</span></td><td>{{ $reminder->vehicle->plate_number }}</td><td>{{ $reminder->reminder_date->format('M j, Y') }}</td><td class="text-capitalize">{{ $reminder->status }}</td><td>@if($reminder->status === 'pending')<form method="POST" action="{{ route('customer.reminders.update', $reminder) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success" name="status" value="completed">Complete</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="p-4 text-secondary">No reminders yet.</td></tr>@endforelse</tbody></table></div></section>
    <div class="row g-4"><div class="col-lg-6"><section class="surface overflow-hidden h-100"><div class="p-4 pb-2"><h2 class="h5">Service schedules</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Vehicle & service</th><th>Next due</th><th></th></tr></thead><tbody>@forelse($schedules as $schedule)<tr><td>{{ $schedule->vehicle->plate_number }}<br><small class="text-secondary">{{ $schedule->service?->name ?? 'General maintenance' }}</small></td><td>{{ $schedule->next_service_date?->format('M j, Y') ?? '—' }}@if($schedule->next_service_mileage)<br><small>{{ number_format($schedule->next_service_mileage) }} km</small>@endif</td><td><form method="POST" action="{{ route('customer.maintenance-schedules.destroy', $schedule) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>@empty<tr><td colspan="3" class="p-4 text-secondary">No recurring schedules yet.</td></tr>@endforelse</tbody></table></div></section></div>
        <div class="col-lg-6"><section class="surface p-4 h-100"><h2 class="h5">Maintenance recommendations</h2><div class="vstack gap-3">@forelse($recommendations as $recommendation)<article class="border rounded p-3"><div class="d-flex justify-content-between gap-2"><strong>{{ $recommendation->recommendation }}</strong><span class="text-capitalize">{{ $recommendation->priority }}</span></div><p class="small text-secondary my-2">{{ $recommendation->vehicle->plate_number }} · {{ $recommendation->reason }}</p><form method="POST" action="{{ route('customer.maintenance-recommendations.update', $recommendation) }}" class="d-flex gap-2">@csrf @method('PATCH')<select class="form-select form-select-sm" name="status" aria-label="Recommendation status"><option value="pending" @selected($recommendation->status === 'pending')>Pending</option><option value="accepted" @selected($recommendation->status === 'accepted')>Accepted</option><option value="completed" @selected($recommendation->status === 'completed')>Completed</option><option value="dismissed" @selected($recommendation->status === 'dismissed')>Dismissed</option></select><button class="btn btn-sm btn-outline-primary">Update</button></form></article>@empty<p class="text-secondary mb-0">No maintenance recommendations yet.</p>@endforelse</div></section></div></div>
</x-layouts.app>
