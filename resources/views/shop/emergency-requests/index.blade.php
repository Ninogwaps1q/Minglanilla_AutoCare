<x-layouts.app title="Roadside requests">
    <div class="page-heading">
        <h1>Roadside requests</h1>
        <p>Review customers assigned to {{ $shop->isIndependentMechanic() ? 'you' : 'your shop' }} and locate where they requested help.</p>
    </div>
    <x-form-errors />
    <section class="roadside-request-list" aria-label="Assigned roadside requests">
        @forelse ($requests as $emergencyRequest)
            <article class="surface roadside-request p-4" id="roadside-request-{{ $emergencyRequest->id }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $emergencyRequest->customer->name }}</h2>
                        <p class="text-secondary mb-0">{{ ucfirst(str_replace('_', ' ', $emergencyRequest->emergency_type)) }} · {{ $emergencyRequest->vehicle?->plate_number ?: 'Vehicle not specified' }}</p>
                    </div>
                    <span class="badge text-bg-{{ $emergencyRequest->status === 'completed' ? 'success' : ($emergencyRequest->status === 'cancelled' ? 'secondary' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $emergencyRequest->status) }}</span>
                </div>
                <div class="roadside-request-main">
                    <div>
                        <dl class="roadside-request-details">
                            <div><dt>Shared pickup location</dt><dd>{{ $emergencyRequest->pickupAddress() ?: 'GPS location shared by customer' }}</dd></div>
                            <div><dt>GPS coordinates</dt><dd>{{ $emergencyRequest->latitude }}, {{ $emergencyRequest->longitude }}</dd></div>
                            <div><dt>Requested</dt><dd>{{ $emergencyRequest->requested_at?->format('M j, Y · g:i A') ?? 'Time unavailable' }}</dd></div>
                            <div><dt>Contact</dt><dd>@if ($emergencyRequest->customer->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $emergencyRequest->customer->phone) }}">{{ $emergencyRequest->customer->phone }}</a>@else No phone number provided @endif</dd></div>
                        </dl>
                        @if ($emergencyRequest->allowedStatuses() !== [])
                            <form method="POST" action="{{ route('shop.emergency-requests.update', $emergencyRequest) }}" class="roadside-request-action d-flex flex-wrap align-items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="form-label" for="emergency-status-{{ $emergencyRequest->id }}">Next request status</label>
                                    <select id="emergency-status-{{ $emergencyRequest->id }}" class="form-select" name="status" required>
                                        @foreach ($emergencyRequest->allowedStatuses() as $status)
                                            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-primary" type="submit">Update status</button>
                            </form>
                        @else
                            <p class="small text-secondary mb-0">Request closed</p>
                        @endif
                    </div>
                    <div class="roadside-location-overview">
                        @if (in_array($emergencyRequest->status, ['pending', 'accepted', 'en_route'], true))
                            <x-driving-directions :latitude="$emergencyRequest->latitude" :longitude="$emergencyRequest->longitude" :destination-label="$emergencyRequest->customer->name.' pickup'" destination-type="pickup" title="Route to customer pickup" button-label="Show route to pickup" />
                            <p class="small text-secondary mt-2 mb-0">This route starts at your current location. Your service-area or private base location is not shared.</p>
                        @else
                            <p class="small text-secondary mb-0">Directions are unavailable for a closed request.</p>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="surface p-4">
                <h2 class="h5">No assigned roadside requests</h2>
                <p class="text-secondary mb-0">Requests sent to {{ $shop->isIndependentMechanic() ? 'you' : 'your shop' }} will appear here with the customer's shared pickup location.</p>
            </div>
        @endforelse
    </section>
</x-layouts.app>
