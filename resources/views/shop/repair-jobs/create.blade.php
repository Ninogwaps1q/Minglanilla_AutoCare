<x-layouts.app title="Create repair job">
    <div class="col-lg-9 mx-auto">
        <div class="page-heading">
            <h1>Create repair job</h1>
            <p>Start a job from a confirmed customer appointment.</p>
        </div>
        @if ($appointments->isEmpty())
            <div class="surface p-4 p-lg-5">
                <h2 class="h5">No confirmed appointments yet</h2>
                <p class="text-secondary">Ask the customer to book a service with your shop, then confirm their request. The
                    appointment will appear here when it is ready for a repair job.</p><a class="btn btn-primary"
                    href="{{ route('shop.appointments.index') }}">Review appointments</a>
            </div>
        @else
            <form method="POST" action="{{ route('shop.repair-jobs.store') }}" class="surface p-4 p-lg-5 row g-3">@csrf
                <div class="col-12"><label class="form-label" for="appointment_id">Confirmed appointment</label><select
                        id="appointment_id" class="form-select @error('appointment_id') is-invalid @enderror"
                        name="appointment_id" required>
                        <option value="">Choose appointment</option>@foreach ($appointments as $appointment)
                            <option value="{{ $appointment->id }}" @selected(old('appointment_id', $selectedAppointmentId) == $appointment->id)>{{ $appointment->customer->name }} —
                                {{ $appointment->vehicle->plate_number }} · {{ $appointment->serviceCase ? 'Follow-up visit' : ($appointment->service?->name ?? 'Vehicle service') }}
                            </option>
                        @endforeach
                    </select>@error('appointment_id')
                    <div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><label class="form-label" for="description">Work description</label><textarea
                        id="description" class="form-control" name="description" rows="3"
                        required>{{ old('description') }}</textarea></div>
                <div class="col-md-6"><label class="form-label" for="diagnosis">Diagnosis</label><textarea id="diagnosis"
                        class="form-control" name="diagnosis" rows="3">{{ old('diagnosis') }}</textarea></div>
                <div class="col-12">
                    <p class="text-secondary mb-0">A booked service and its price are added when available. Follow-up visits do not charge the original service again. Add any new parts or labor before sending an estimate for customer approval.</p>
                </div>
                <div class="col-12"><button class="btn btn-primary">Create repair job</button></div>
            </form>
        @endif
    </div>
</x-layouts.app>
