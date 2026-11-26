@php $attempts = $dispatchAttempts->get($emergencyRequest->id, collect()); @endphp
<article class="py-3 border-bottom" aria-label="{{ str_replace('_', ' ', $emergencyRequest->emergency_type) }} request">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{{ ucfirst(str_replace('_', ' ', $emergencyRequest->emergency_type)) }}</strong><span class="badge text-bg-{{ $emergencyRequest->status === 'completed' ? 'success' : ($emergencyRequest->status === 'cancelled' ? 'secondary' : 'warning') }} text-capitalize">{{ str_replace('_', ' ', $emergencyRequest->status) }}</span></div>
    <p class="small text-secondary mb-2">Requested {{ $emergencyRequest->requested_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }} · {{ $emergencyRequest->pickupAddress() ?: 'Location coordinates shared' }}</p>
    @if ($emergencyRequest->assignedShop)
        <p class="mb-1"><strong>Assigned {{ $emergencyRequest->assignedShop->isIndependentMechanic() ? 'mechanic' : 'shop' }}:</strong> {{ $emergencyRequest->assignedShop->name }}</p>
        @if ($emergencyRequest->assignedShop->phone)<p class="mb-1"><strong>Contact:</strong> <a href="tel:{{ preg_replace('/[^+0-9]/', '', $emergencyRequest->assignedShop->phone) }}">{{ $emergencyRequest->assignedShop->phone }}</a></p>@endif
        @if ($emergencyRequest->status === 'pending')<p class="small mb-1">Waiting for the provider to accept. You will receive a notification when its status changes.</p>@endif
    @else
        <p class="mb-1"><strong>No provider assigned.</strong> {{ $emergencyRequest->status === 'cancelled' ? 'No eligible provider could take this request. If urgent, contact local emergency services or another assistance provider.' : 'We are looking for an eligible provider. If urgent, contact local emergency services.' }}</p>
    @endif
    @if ($attempts->count() > 1)<p class="small text-secondary mb-1">Reassigned {{ $attempts->count() - 1 }} {{ $attempts->count() === 2 ? 'time' : 'times' }} after a provider could not assist.</p>@endif
    @if ($attempts->isNotEmpty())<details class="small mt-2"><summary>Dispatch history ({{ $attempts->count() }})</summary><ol class="mb-0 mt-2">@foreach ($attempts as $attempt)<li>{{ $attempt->shop_name }} · {{ ucfirst(str_replace('_', ' ', $attempt->status)) }} · {{ \Illuminate\Support\Carbon::parse($attempt->assigned_at)->timezone('Asia/Manila')->format('M j, g:i A') }}</li>@endforeach</ol></details>@endif
    @if (in_array($emergencyRequest->status, ['pending', 'accepted'], true))
        <form method="POST" action="{{ route('customer.emergency-requests.destroy', $emergencyRequest) }}" class="mt-3">@csrf @method('DELETE')
            <button class="btn btn-outline-danger" type="submit" data-confirm="Cancel this roadside request? The assigned provider will be notified.">Cancel request</button>
        </form>
    @endif
</article>
