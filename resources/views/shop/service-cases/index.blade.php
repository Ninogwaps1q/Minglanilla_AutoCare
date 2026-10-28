<x-layouts.app title="Customer aftercare">
    <div class="page-heading"><h1>Customer aftercare</h1><p>Reply to follow-up requests and warranty claims. Admin reviews complaint resolutions.</p></div>
    <x-form-errors />
    @forelse($cases as $case)
        <article class="surface p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div><h2 class="h5 mb-1">{{ $case->subject }}</h2><p class="text-secondary small mb-0">{{ $case->customer->name }} · {{ $case->repairJob->job_number }} · {{ $case->repairJob->vehicle->plate_number }}</p></div>
                <div><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $case->type)) }}</span> <span class="badge text-bg-{{ in_array($case->status, ['resolved', 'declined', 'dismissed'], true) ? 'secondary' : 'primary' }}">{{ ucfirst(str_replace('_', ' ', $case->status)) }}</span></div>
            </div>
            <p class="mt-3">{{ $case->description }}</p>
            @if($case->scheduled_at)<p class="small mb-2"><strong>Proposed visit:</strong> {{ $case->scheduled_at->format('M j, Y g:i A') }} · {{ ucfirst($case->schedule_status ?? 'pending') }} by customer</p>@endif
            @if($case->schedule_status === 'confirmed' && $case->appointment)
                <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('shop.appointments.index', ['focus' => $case->appointment->id]) }}">View confirmed appointment</a>
            @endif
            @if($case->admin_resolution)<div class="alert alert-info"><strong>Admin resolution</strong><p class="mb-0">{{ $case->admin_resolution }}</p></div>@endif
            @if(in_array($case->appointment?->status, ['confirmed', 'in_progress'], true))
                <p class="small text-secondary mb-3">This visit is confirmed. Finish it from Appointments before closing the aftercare request.</p>
            @elseif(!in_array($case->status, ['resolved', 'declined', 'dismissed'], true))
                <form method="POST" action="{{ route('shop.service-cases.update', $case) }}" class="row g-3 border-top pt-3">
                    @csrf @method('PATCH')
                    <div class="col-lg-8"><label class="form-label" for="case-response-{{ $case->id }}">Your response to the customer</label><textarea class="form-control" id="case-response-{{ $case->id }}" name="shop_response" rows="3" maxlength="3000" required>{{ old('shop_response', $case->shop_response) }}</textarea></div>
                    <div class="col-lg-4"><label class="form-label" for="case-status-{{ $case->id }}">Next step</label><select class="form-select" id="case-status-{{ $case->id }}" name="status"><option value="reviewing">Reviewing</option>@if($case->type !== 'complaint')@if($case->appointment?->status !== 'completed')<option value="scheduled" @selected($case->status === 'scheduled')>Propose follow-up visit</option>@endif<option value="resolved">Resolved</option><option value="declined">Declined with explanation</option>@endif</select><p class="small text-secondary mt-2">{{ $case->type === 'complaint' ? 'Admin decides the complaint outcome after your response.' : 'Choose resolved only after the customer concern has been handled.' }}</p></div>
                    @if($case->type !== 'complaint' && $case->appointment?->status !== 'completed')<div class="col-lg-6"><label class="form-label" for="case-time-{{ $case->id }}">Follow-up visit time (required if proposing a visit)</label><input id="case-time-{{ $case->id }}" class="form-control" type="datetime-local" name="scheduled_at" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $case->scheduled_at?->format('Y-m-d\TH:i') }}"></div>@endif
                    <div class="col-12"><button class="btn btn-primary">Save response</button></div>
                </form>
            @endif
            <details class="mt-3"><summary>Case history · {{ $case->events->count() }} updates</summary><ol class="mt-3 mb-0">@foreach($case->events as $event)<li class="mb-2"><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong> · {{ $event->actor?->name ?? 'Former user' }} · {{ $event->created_at->format('M j, Y g:i A') }}<br><span class="text-secondary">{{ $event->note }}</span></li>@endforeach</ol></details>
        </article>
    @empty
        <div class="surface p-4"><h2 class="h5">No aftercare requests</h2><p class="text-secondary mb-0">Customer follow-ups and complaints will appear here after completed repairs.</p></div>
    @endforelse
    {{ $cases->links() }}
</x-layouts.app>
