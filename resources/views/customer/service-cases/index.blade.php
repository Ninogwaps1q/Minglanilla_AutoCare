<x-layouts.app title="My aftercare requests">
    <div class="page-heading"><h1>Follow-ups & complaints</h1><p>Track shop replies and admin decisions on completed repairs.</p></div>
    <x-form-errors />
    @forelse($cases as $case)
        <article class="surface p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5 mb-1">{{ $case->subject }}</h2><p class="text-secondary small mb-0">{{ ucfirst(str_replace('_', ' ', $case->type)) }} · {{ $case->repairJob->job_number }} · {{ $case->shop->name }}</p></div><span class="badge text-bg-{{ in_array($case->status, ['resolved', 'declined', 'dismissed'], true) ? 'secondary' : 'primary' }}">{{ ucfirst(str_replace('_', ' ', $case->status)) }}</span></div>
            <p class="mt-3 mb-2">{{ $case->description }}</p>
            @if($case->shop_response)<div class="border rounded p-3 mb-2"><strong>Shop response</strong><p class="mb-0 mt-1">{{ $case->shop_response }}</p></div>@endif
            @if($case->scheduled_at)
                <p class="mb-2"><strong>Follow-up visit:</strong> {{ $case->scheduled_at->format('M j, Y g:i A') }} · {{ ucfirst($case->schedule_status ?? 'pending') }}</p>
                @if($case->schedule_status === 'confirmed' && $case->appointment)
                    <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('customer.appointments.index', ['focus' => $case->appointment->id]) }}">View confirmed appointment</a>
                @endif
                @if($case->schedule_status === 'pending')
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <form method="POST" action="{{ route('customer.service-cases.schedule', $case) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="confirm"><button class="btn btn-primary btn-sm">Confirm visit</button></form>
                        <form method="POST" action="{{ route('customer.service-cases.schedule', $case) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="decline"><button class="btn btn-outline-secondary btn-sm">Ask for another time</button></form>
                    </div>
                @endif
            @endif
            @if($case->admin_resolution)<div class="alert alert-info mb-2"><strong>Admin resolution</strong><p class="mb-0 mt-1">{{ $case->admin_resolution }}</p></div>@endif
            <details><summary>Progress history · {{ $case->events->count() }} updates</summary><ol class="mt-3 mb-0">@foreach($case->events as $event)<li class="mb-2"><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong> · {{ $event->created_at->format('M j, Y g:i A') }} · {{ $event->actor?->name ?? 'Former user' }}<br><span class="text-secondary">{{ $event->note }}</span></li>@endforeach</ol></details>
        </article>
    @empty
        <div class="surface p-4"><h2 class="h5">No requests yet</h2><p class="text-secondary">After a repair is completed, open it from My repairs to request a follow-up or report a concern.</p><a href="{{ route('customer.repairs.index') }}">View my repairs</a></div>
    @endforelse
    {{ $cases->links() }}
</x-layouts.app>
