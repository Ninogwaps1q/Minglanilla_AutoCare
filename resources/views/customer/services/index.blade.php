<x-layouts.app title="My services">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3">
        <div><h1>My services</h1><p>Your bookings, work in progress, completed visits, and aftercare in one place.</p></div>
        <a class="btn btn-primary align-self-start" href="{{ route('customer.appointments.create') }}">Book a service</a>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            <section class="surface p-4 h-100">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Coming up</h2><a href="{{ route('customer.appointments.index') }}">All appointments</a></div>
                @forelse($appointments as $appointment)
                    <article class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-2"><strong>{{ $appointment->service?->name ?? 'Vehicle service' }}</strong><span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></div>
                        <p class="text-secondary small mb-0 mt-1">{{ $appointment->appointment_date->format('M j, Y') }} · {{ $appointment->shop->name }} · {{ $appointment->vehicle->plate_number }}</p>
                    </article>
                @empty
                    <p class="text-secondary mb-0">No upcoming appointments. Find a shop and book when you are ready.</p>
                @endforelse
            </section>
        </div>
        <div class="col-xl-5">
            <section class="surface p-4 h-100">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">In the workshop</h2><a href="{{ route('customer.repairs.index') }}">All repairs</a></div>
                @forelse($repairs as $repair)
                    <article class="border rounded p-3 mb-2">
                        <a class="fw-semibold" href="{{ route('customer.repairs.index', ['repair_job_id' => $repair->id]) }}#repair-{{ $repair->id }}">{{ $repair->job_number }}</a>
                        <p class="text-secondary small mb-1">{{ $repair->vehicle->plate_number }} · {{ $repair->shop->name }}</p>
                        <span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $repair->status)) }}</span>
                    </article>
                @empty
                    <p class="text-secondary mb-0">No active repairs. The shop will show updates here after work begins.</p>
                @endforelse
            </section>
        </div>
    </div>
    <section class="surface p-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><h2 class="h5 mb-1">Completed services</h2><p class="text-secondary small mb-0">Open a repair to see photos, warranty, or ask for help afterward.</p></div>
            <a href="{{ route('customer.service-history.index') }}">Full service history</a>
        </div>
        @forelse($records as $record)
            <article class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top py-3">
                <div><strong>{{ $record->service_type }}</strong><p class="text-secondary small mb-0">{{ $record->service_date->format('M j, Y') }} · {{ $record->vehicle->plate_number }} · {{ $record->shop->name }}</p></div>
                <div class="d-flex align-items-center gap-2">
                    @if($record->repairJob?->warranty)<span class="badge text-bg-{{ $record->repairJob->warranty->valid_until->isBefore(today()) ? 'secondary' : 'success' }}">Warranty {{ $record->repairJob->warranty->valid_until->isBefore(today()) ? 'ended' : 'active' }}</span>@endif
                    @if($record->repairJob)<a class="btn btn-sm btn-outline-primary" href="{{ route('customer.repairs.index', ['repair_job_id' => $record->repairJob->id]) }}#repair-{{ $record->repairJob->id }}">View repair</a>@endif
                </div>
            </article>
        @empty
            <p class="text-secondary mb-0">Completed repairs will appear here after the shop finishes the work.</p>
        @endforelse
    </section>
    <section class="surface p-4 mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><h2 class="h5 mb-1">Need help after a repair?</h2><p class="text-secondary mb-0">{{ $openCaseCount }} open {{ str('request')->plural($openCaseCount) }}. Track a follow-up, warranty claim, or complaint.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('customer.service-cases.index') }}">View my requests</a>
    </section>
</x-layouts.app>
