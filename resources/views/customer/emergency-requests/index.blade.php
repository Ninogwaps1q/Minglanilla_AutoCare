<x-layouts.app title="Roadside assistance">
    <div class="page-heading"><h1>Roadside assistance</h1><p>Tell us where you are and what happened. We will send the request to an eligible nearby shop.</p></div>
    <div class="row g-4">
        <div class="col-lg-5"><section class="surface p-4" aria-labelledby="request-help-title">
            <h2 class="h4" id="request-help-title">Request help</h2><x-form-errors />
            <form method="POST" action="{{ route('customer.emergency-requests.store') }}" class="vstack gap-3">@csrf
                <div><label class="form-label" for="vehicle_id">Vehicle</label><select id="vehicle_id" class="form-select" name="vehicle_id"><option value="">No vehicle selected</option>@foreach ($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }} · {{ $vehicle->make }} {{ $vehicle->model }}</option>@endforeach</select></div>
                <div><label class="form-label" for="emergency_type">Type of emergency</label><select id="emergency_type" class="form-select" name="emergency_type" required>@foreach (['breakdown' => 'Breakdown', 'flat_tire' => 'Flat tire', 'battery' => 'Battery', 'overheating' => 'Overheating', 'accident' => 'Accident', 'towing' => 'Towing', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('emergency_type') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="form-label" for="address">Location details</label><input id="address" class="form-control" name="address" maxlength="500" value="{{ old('address') }}" placeholder="Street, landmark, or barangay"></div>
                <div><label class="form-label" for="description">What happened?</label><textarea id="description" class="form-control" name="description" rows="3" maxlength="2000" placeholder="Describe your situation briefly">{{ old('description') }}</textarea></div>
                <input id="latitude" type="hidden" name="latitude" value="{{ old('latitude') }}"><input id="longitude" type="hidden" name="longitude" value="{{ old('longitude') }}">
                <div><button id="location-button" class="btn btn-outline-primary" type="button">Use my current location</button><p id="location-status" class="small text-secondary mt-2 mb-0" role="status" aria-live="polite">Location is needed to find the nearest eligible shop.</p></div>
                <button class="btn btn-primary" type="submit">Send assistance request</button>
            </form>
        </section></div>
        <div class="col-lg-7"><section class="surface p-4" aria-labelledby="requests-title">
            <h2 class="h4" id="requests-title">Your requests</h2>
            <div class="vstack gap-3">@forelse ($requests as $emergencyRequest)
                @php $attempts = $dispatchAttempts->get($emergencyRequest->id, collect()); @endphp
                <article class="border rounded p-3" aria-label="{{ str_replace('_', ' ', $emergencyRequest->emergency_type) }} request">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{{ ucfirst(str_replace('_', ' ', $emergencyRequest->emergency_type)) }}</strong><span class="badge text-bg-{{ $emergencyRequest->status === 'completed' ? 'success' : ($emergencyRequest->status === 'cancelled' ? 'secondary' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $emergencyRequest->status) }}</span></div>
                    <p class="small text-secondary mb-2">Requested {{ $emergencyRequest->requested_at?->format('M j, Y · g:i A') }} · {{ $emergencyRequest->address ?: 'Location coordinates shared' }}</p>
                    @if ($emergencyRequest->assignedShop)
                        <p class="mb-1"><strong>Assigned shop:</strong> {{ $emergencyRequest->assignedShop->name }}</p>
                        @if ($emergencyRequest->assignedShop->phone)<p class="mb-1"><strong>Contact:</strong> <a href="tel:{{ preg_replace('/[^+0-9]/', '', $emergencyRequest->assignedShop->phone) }}">{{ $emergencyRequest->assignedShop->phone }}</a></p>@endif
                        @if ($emergencyRequest->status === 'pending')<p class="small mb-1">Waiting for the shop to accept. You will receive a notification when its status changes.</p>@endif
                    @else
                        <p class="mb-1"><strong>No shop assigned.</strong> {{ $emergencyRequest->status === 'cancelled' ? 'No eligible shop could take this request. If urgent, contact local emergency services or another assistance provider.' : 'We are looking for an eligible shop. If urgent, contact local emergency services.' }}</p>
                    @endif
                    @if ($attempts->count() > 1)<p class="small text-secondary mb-1">Reassigned {{ $attempts->count() - 1 }} {{ $attempts->count() === 2 ? 'time' : 'times' }} after a shop could not assist.</p>@endif
                    @if ($attempts->isNotEmpty())<details class="small mt-2"><summary>Dispatch history ({{ $attempts->count() }})</summary><ol class="mb-0 mt-2">@foreach ($attempts as $attempt)<li>{{ $attempt->shop_name }} · {{ ucfirst(str_replace('_', ' ', $attempt->status)) }} · {{ \Illuminate\Support\Carbon::parse($attempt->assigned_at)->format('M j, g:i A') }}</li>@endforeach</ol></details>@endif
                </article>
            @empty<p class="text-secondary mb-0">No roadside assistance requests yet. Your requests and shop responses will appear here.</p>@endforelse</div>
        </section></div>
    </div>
    @push('scripts')
        <script>
            document.querySelector('#location-button')?.addEventListener('click', function () {
                const status = document.querySelector('#location-status');
                if (!navigator.geolocation) { status.textContent = 'This browser does not support location sharing. Try another browser or device.'; return; }
                this.disabled = true;
                status.textContent = 'Finding your location…';
                navigator.geolocation.getCurrentPosition(
                    (position) => { document.querySelector('#latitude').value = position.coords.latitude; document.querySelector('#longitude').value = position.coords.longitude; status.textContent = 'Location ready. You can send your request now.'; this.disabled = false; },
                    () => { status.textContent = 'Location could not be shared. Allow location access in your browser and try again.'; this.disabled = false; },
                    { enableHighAccuracy: true, timeout: 15000 }
                );
            });
        </script>
    @endpush
</x-layouts.app>
