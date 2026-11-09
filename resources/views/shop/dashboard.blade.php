<x-layouts.app title="Shop dashboard">
    @if ($shop === null)
        <div class="page-heading">
            <h1>Set up your service shop</h1>
            <p>Register the business once, then manage bookings, repairs, staff, and payments from this workspace.</p>
        </div>
        <div class="surface p-4 p-lg-5 shop-onboarding"><i class="bi bi-shop" aria-hidden="true"></i>
            <div>
                <h2 class="h4">Start with your shop registration</h2>
                <p class="text-secondary">An administrator reviews your business details before it appears to customers.</p>
                <a class="btn btn-primary" href="{{ route('shop.register') }}">Register my shop</a>
            </div>
        </div>
    @else
        <section class="workspace-welcome mb-4" aria-labelledby="shop-welcome-title">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><span
                        class="workspace-date">{{ now()->format('l, F j') }}</span><span
                        class="badge text-bg-{{ $shop->verification_status === 'approved' ? 'success' : 'warning' }} text-capitalize">{{ $shop->verification_status }}</span>
                </div>
                <h1 id="shop-welcome-title">{{ $shop->name }}</h1>
                <p>@if ($shop->verification_status !== 'approved') Your shop is not approved yet. Check your business details and verification documents.
                    @elseif ($metrics['Waiting approval'] > 0) You have {{ $metrics['Waiting approval'] }} {{ str('booking')->plural($metrics['Waiting approval']) }} waiting for a decision.
                    @elseif ($pendingPaymentCount > 0) {{ $pendingPaymentCount }} {{ str('payment')->plural($pendingPaymentCount) }} need your confirmation.
                    @else Review today's visits and keep each repair moving.
                    @endif</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if ($shop->verification_status !== 'approved')
                        <a class="btn btn-accent" href="{{ route('shop.profile.edit') }}"><i class="bi bi-file-earmark-check me-2" aria-hidden="true"></i>Check verification details</a>
                    @elseif ($metrics['Waiting approval'] > 0)
                        <a class="btn btn-accent" href="{{ route('shop.appointments.index', ['status' => 'pending']) }}"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Review booking requests</a>
                    @elseif ($pendingPaymentCount > 0)
                        <a class="btn btn-accent" href="{{ route('shop.invoices.index') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Confirm payments</a>
                    @else
                        <a class="btn btn-accent" href="{{ route('shop.appointments.index') }}"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Review appointments</a>
                    @endif
                    @if ($shop->verification_status === 'approved')
                        <a class="btn btn-outline-light" href="{{ route('shop.repair-jobs.index') }}">View repair jobs</a>
                    @endif
                </div>
            </div>
            <x-uploaded-image class="workspace-shop-logo" :path="$shop->logo" :alt="$shop->name . ' logo'"
                fallback-icon="wrench-adjustable-circle" />
        </section>

        <x-action-queue :items="[
            ['count' => $metrics['Waiting approval'], 'label' => 'Booking requests', 'detail' => 'Confirm or decline a customer visit.', 'href' => route('shop.appointments.index', ['status' => 'pending'])],
            ['count' => $confirmedAppointmentCount, 'label' => 'Visits ready for a job', 'detail' => 'Open a confirmed visit and start diagnosis.', 'href' => route('shop.appointments.index', ['status' => 'confirmed'])],
            ['count' => $approvedRepairCount, 'label' => 'Approved repairs', 'detail' => 'Start work at the customer-approved price.', 'href' => route('shop.repair-jobs.index', ['status' => 'approved'])],
            ['count' => $pendingPaymentCount, 'label' => 'Payments to confirm', 'detail' => 'Verify money received before approving.', 'href' => route('shop.invoices.index', ['status' => 'pending'])],
        ]" :empty-route="route('shop.today-work.index')" empty-label="Open work list" />

        <section class="mb-4" aria-labelledby="shop-summary-title">
            <div class="section-heading">
                <div>
                    <h2 id="shop-summary-title">Today at a glance</h2>
                    <p>The work and issues that need your team’s attention.</p>
                </div>
            </div>
            <div class="workspace-stats">
                @foreach ($metrics as $label => $value)@php $icon = match ($label) { 'Today’s visits' => 'calendar-check', 'Waiting approval' => 'hourglass-split', 'Active repairs' => 'tools', default => 'box-seam'};
                    $route = match ($label) { 'Today’s visits', 'Waiting approval' => route('shop.appointments.index'), 'Active repairs' => route('shop.repair-jobs.index'), default => route('shop.inventory.index')}; @endphp<a
                        class="workspace-stat" href="{{ $route }}"><i class="bi bi-{{ $icon }}"
                            aria-hidden="true"></i><span><strong>{{ $value }}</strong>{{ $label }}</span><i
                class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>@endforeach</div>
        </section>

        <div class="row g-4">
            <div class="col-xl-7">
                <section class="surface p-4 h-100" aria-labelledby="today-schedule-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="today-schedule-title">Today’s schedule</h2>
                            <p>Upcoming customers in appointment order.</p>
                        </div><a href="{{ route('shop.appointments.index') }}">Full schedule</a>
                    </div>
                    <div class="workspace-list">@forelse ($todayAppointments as $appointment)
                        <article><time>{{ $appointment->appointment_time->format('g:i A') }}</time>
                            <div>
                                <h3><a class="workspace-row-link" href="{{ route('shop.appointments.index', ['focus' => $appointment->id]) }}">{{ $appointment->serviceCase ? 'Follow-up visit' : ($appointment->service?->name ?? 'General service') }}</a></h3>
                                <p>{{ $appointment->customer->name }} · {{ $appointment->vehicle->plate_number }}</p>
                            </div><span
                                class="badge text-bg-{{ $appointment->status === 'confirmed' ? 'success' : 'warning' }} text-capitalize">{{ $appointment->status }}</span>
                    </article>@empty<div class="workspace-empty"><i class="bi bi-calendar2-check"
                                aria-hidden="true"></i>
                            <div>
                                <h3>No visits scheduled today</h3>
                                <p>New booking requests will appear here once customers choose your shop.</p>
                            </div>
                        </div>@endforelse
                    </div>
                </section>
            </div>
            <div class="col-xl-5">
                <section class="surface p-4 h-100" aria-labelledby="active-work-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="active-work-title">Repairs in progress</h2>
                            <p>Recent jobs that are not yet released.</p>
                        </div><a href="{{ route('shop.repair-jobs.index') }}">View all</a>
                    </div>
                    <div class="workspace-list compact">@forelse ($activeRepairs as $repair)
                        <article>
                            <div>
                                <h3><a class="workspace-row-link" href="{{ route('shop.repair-jobs.index', ['repair_job_id' => $repair->id]) }}#repair-{{ $repair->id }}">{{ $repair->vehicle->plate_number }} · {{ $repair->customer->name }}</a></h3>
                                <p>{{ str($repair->description)->limit(65) }}</p>
                            </div><span
                                class="badge text-bg-secondary text-capitalize">{{ str_replace('_', ' ', $repair->status) }}</span>
                    </article>@empty<div class="workspace-empty"><i class="bi bi-tools" aria-hidden="true"></i>
                            <div>
                                <h3>No active repairs</h3>
                                <p>Start a repair job when a vehicle enters the workshop.</p>
                            </div>
                        </div>@endforelse
                    </div>
                </section>
            </div>
        </div>
        <section class="surface p-4 mt-4" aria-labelledby="shop-actions-title">
            <div class="section-heading mb-2">
                <div>
                    <h2 id="shop-actions-title">Quick actions</h2>
                    <p>Open estimates, payments, and parts.</p>
                </div>
            </div>
            <div class="workspace-actions"><a href="{{ route('shop.quotations.index') }}"><i class="bi bi-file-text"
                        aria-hidden="true"></i><span><strong>Quotations</strong>Review estimates</span></a><a
                    href="{{ route('shop.invoices.index') }}"><i class="bi bi-receipt"
                        aria-hidden="true"></i><span><strong>Payments</strong>{{ $pendingPaymentCount }} awaiting
                        verification</span></a><a href="{{ route('shop.inventory.index') }}"><i class="bi bi-box-seam"
                        aria-hidden="true"></i><span><strong>Inventory</strong>Check parts and stock</span></a></div>
        </section>
    @endif
</x-layouts.app>
