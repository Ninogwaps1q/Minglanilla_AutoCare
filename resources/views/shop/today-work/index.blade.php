<x-layouts.app title="Today's work">
    <div class="page-heading"><h1>Today's work</h1><p>Work through today's visits, active repairs, and customer follow-ups.</p></div>
    <div class="row g-4">
        <div class="col-xl-6">
            <section class="surface p-4 h-100">
                <div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5 mb-0">Today's visits · {{ $appointments->count() }}</h2><a href="{{ route('shop.appointments.index') }}">All appointments</a></div>
                @forelse($appointments as $appointment)
                    <article class="border-top py-3">
                        <div class="d-flex justify-content-between gap-2"><strong>{{ $appointment->appointment_time->format('g:i A') }} · {{ $appointment->customer->name }}</strong><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></div>
                        <p class="text-secondary small mb-2">{{ $appointment->vehicle->plate_number }} · {{ $appointment->serviceCase ? 'Follow-up visit' : ($appointment->service?->name ?? 'Vehicle service') }}</p>
                        <a href="{{ $appointment->repairJob ? route('shop.repair-jobs.index', ['appointment_id' => $appointment->id]) : route('shop.appointments.index') }}">{{ $appointment->repairJob ? 'Open repair' : 'Manage appointment' }}</a>
                    </article>
                @empty<p class="text-secondary mb-0">No visits scheduled today.</p>@endforelse
            </section>
        </div>
        <div class="col-xl-6">
            <section class="surface p-4 h-100">
                <div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5 mb-0">Active repairs · {{ $repairs->count() }}</h2><a href="{{ route('shop.repair-jobs.index') }}">All repair jobs</a></div>
                @forelse($repairs as $repair)
                    <article class="border-top py-3">
                        <div class="d-flex justify-content-between gap-2"><strong>{{ $repair->job_number }}</strong><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $repair->status)) }}</span></div>
                        <p class="text-secondary small mb-2">{{ $repair->customer->name }} · {{ $repair->vehicle->plate_number }}</p>
                        <a href="{{ route('shop.repair-jobs.index', ['repair_job_id' => $repair->id]) }}#repair-{{ $repair->id }}">Continue repair</a>
                    </article>
                @empty<p class="text-secondary mb-0">No repairs need action right now.</p>@endforelse
            </section>
        </div>
    </div>
    <section class="surface p-4 mt-4">
        <div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5 mb-0">Customer aftercare · {{ $cases->count() }}</h2><a href="{{ route('shop.service-cases.index') }}">Handle requests</a></div>
        @forelse($cases as $case)
            <article class="border-top py-3"><strong>{{ $case->subject }}</strong><span class="badge text-bg-secondary ms-2">{{ ucfirst(str_replace('_', ' ', $case->status)) }}</span><p class="text-secondary small mb-0">{{ $case->customer->name }} · {{ $case->repairJob->job_number }} · {{ ucfirst(str_replace('_', ' ', $case->type)) }}</p></article>
        @empty<p class="text-secondary mb-0">No open aftercare requests.</p>@endforelse
    </section>
</x-layouts.app>
