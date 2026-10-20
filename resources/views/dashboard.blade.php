<x-layouts.app title="Dashboard">
    @if ($user->role === 'customer')
        <section class="customer-welcome mb-4" aria-labelledby="customer-welcome-title">
            <div>
                <p class="customer-welcome-date mb-2">{{ now()->format('l, F j') }}</p>
                <h1 id="customer-welcome-title">Good
                    {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }},
                    {{ str($user->name)->before(' ') }}.</h1>
                <p>@if (! $primaryVehicle) Add your vehicle first so you can book a service and keep its repair history together.
                    @elseif ($unpaidInvoiceCount > 0) You have a payment to review. Open your invoices to see the amount and next step.
                    @elseif ($activeRepair) Your repair is underway. Check its progress and any estimate awaiting your decision.
                    @elseif ($nextAppointment) Your next visit is scheduled. Open your appointments for the date, time, and shop.
                    @else Ready for your next service? Find a shop near you or choose a booking time.
                    @endif</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if (! $primaryVehicle)
                        <a class="btn btn-accent" href="{{ route('customer.vehicles.create') }}"><i class="bi bi-car-front me-2" aria-hidden="true"></i>Add my vehicle</a>
                        <a class="btn btn-light" href="{{ route('shops.index') }}">Find nearby shops</a>
                    @elseif ($unpaidInvoiceCount > 0)
                        <a class="btn btn-accent" href="{{ route('customer.invoices.index') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Review payments</a>
                        <a class="btn btn-light" href="{{ route('customer.appointments.create') }}">Book a service</a>
                    @elseif ($activeRepair)
                        <a class="btn btn-accent" href="{{ route('customer.repairs.index') }}"><i class="bi bi-tools me-2" aria-hidden="true"></i>Track my repair</a>
                        <a class="btn btn-light" href="{{ route('customer.appointments.create') }}">Book a service</a>
                    @elseif ($nextAppointment)
                        <a class="btn btn-accent" href="{{ route('customer.appointments.index') }}"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>View appointment</a>
                        <a class="btn btn-light" href="{{ route('shops.index') }}">Find nearby shops</a>
                    @else
                        <a class="btn btn-accent" href="{{ route('shops.index') }}"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Find a nearby shop</a>
                        <a class="btn btn-light" href="{{ route('customer.appointments.create') }}">Book a service</a>
                    @endif
                </div>
            </div>
            <div class="customer-welcome-mark" aria-hidden="true"><i class="bi bi-car-front-fill"></i><span>Ready for the
                    road</span></div>
        </section>

        <section aria-labelledby="care-summary-title" class="mb-4">
            <div class="section-heading">
                <div>
                    <h2 id="care-summary-title">Your care summary</h2>
                    <p>A quick look at what needs your attention.</p>
                </div>
            </div>
            <div class="customer-stats">
                @foreach ($metrics as $label => $value)
                    @php $icon = match ($label) { 'My vehicles' => 'car-front', 'Upcoming visits' => 'calendar-check', 'Active repairs' => 'tools', default => 'heart'}; @endphp
                    <a class="customer-stat"
                        href="{{ match ($label) { 'My vehicles' => route('customer.vehicles.index'), 'Upcoming visits' => route('customer.appointments.index'), 'Active repairs' => route('customer.repairs.index'), default => route('customer.favorites.index')} }}">
                        <i class="bi bi-{{ $icon }}"
                            aria-hidden="true"></i><span><strong>{{ $value }}</strong>{{ $label }}</span><i
                            class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="surface p-4 h-100" aria-labelledby="next-step-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="next-step-title">What’s next</h2>
                            <p>Your nearest scheduled activity and current repair progress.</p>
                        </div><a href="{{ route('customer.appointments.index') }}">All appointments</a>
                    </div>
                    @if ($nextAppointment)
                        <article class="customer-next-step">
                            <div class="customer-date-tile">
                                <strong>{{ $nextAppointment->appointment_date->format('d') }}</strong><span>{{ $nextAppointment->appointment_date->format('M') }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h5 mb-0">{{ $nextAppointment->service->name }}</h3><span
                                        class="badge text-bg-{{ $nextAppointment->status === 'confirmed' ? 'success' : 'warning' }} text-capitalize">{{ $nextAppointment->status }}</span>
                                </div>
                                <p class="mb-1">{{ $nextAppointment->shop->name }}</p>
                                <p class="small text-secondary mb-0">{{ $nextAppointment->appointment_time->format('g:i A') }} ·
                                    {{ $nextAppointment->vehicle->plate_number }}</p>
                            </div>
                        </article>
                    @else
                        <div class="customer-empty-state"><i class="bi bi-calendar2-plus" aria-hidden="true"></i>
                            <div>
                                <h3 class="h5">No upcoming service visit</h3>
                                <p>Choose a verified shop and request a time that works for you.</p><a class="btn btn-primary"
                                    href="{{ route('customer.appointments.create') }}">Book your first service</a>
                            </div>
                        </div>
                    @endif

                    @if ($activeRepair)
                        <article class="repair-glance mt-4">
                            <div><span class="status-dot"
                                    aria-hidden="true"></span><strong>{{ str($activeRepair->status)->replace('_', ' ')->title() }}</strong>
                            </div>
                            <h3 class="h5 mt-2 mb-1">{{ $activeRepair->vehicle->plate_number }} is with
                                {{ $activeRepair->shop->name }}</h3>
                            <p class="text-secondary mb-3">{{ str($activeRepair->description)->limit(120) }}</p><a
                                class="btn btn-outline-primary btn-sm" href="{{ route('customer.repairs.index') }}">Track repair
                                progress</a>
                        </article>
                    @endif
                </section>
            </div>
            <div class="col-xl-4">
                <section class="surface p-4 mb-4" aria-labelledby="garage-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="garage-title">My garage</h2>
                            <p>Your most recently added vehicle.</p>
                        </div>
                    </div>
                    @if ($primaryVehicle)
                        <div class="garage-vehicle"><x-uploaded-image class="garage-vehicle-photo"
                                :path="$primaryVehicle->photo" :alt="$primaryVehicle->plate_number . ' vehicle photo'"
                                fallback-icon="car-front" />
                            <div><strong>{{ $primaryVehicle->year }} {{ $primaryVehicle->make }}
                                    {{ $primaryVehicle->model }}</strong><span>{{ $primaryVehicle->plate_number }} ·
                                    {{ number_format($primaryVehicle->current_mileage) }} km</span></div>
                        </div>
                        <a class="btn btn-outline-primary w-100 mt-3"
                            href="{{ route('customer.vehicles.show', $primaryVehicle) }}">Open vehicle details</a>
                    @else
                        <div class="customer-empty-compact"><i class="bi bi-car-front" aria-hidden="true"></i>
                            <p>Add your vehicle once, then use it for bookings, repairs, and service history.</p><a
                                class="btn btn-primary w-100" href="{{ route('customer.vehicles.create') }}">Add my vehicle</a>
                        </div>
                    @endif
                </section>
                <section class="surface p-4" aria-labelledby="attention-title">
                    <h2 id="attention-title" class="h5">Need attention</h2>
                    <a class="attention-link" href="{{ route('customer.invoices.index') }}"><span><i class="bi bi-receipt"
                                aria-hidden="true"></i>Unpaid invoices</span><strong>{{ $unpaidInvoiceCount }}</strong></a>
                    <a class="attention-link" href="{{ route('customer.notifications.index') }}"><span><i class="bi bi-bell"
                                aria-hidden="true"></i>Updates</span><i class="bi bi-chevron-right"
                            aria-hidden="true"></i></a>
                    <a class="attention-link attention-link-emergency"
                        href="{{ route('customer.emergency-requests.index') }}"><span><i class="bi bi-life-preserver"
                                aria-hidden="true"></i>Roadside help</span><i class="bi bi-chevron-right"
                            aria-hidden="true"></i></a>
                </section>
            </div>
        </div>
    @elseif ($user->role === 'admin')
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h1>System overview</h1>
                <p>Review platform activity, resolve pending work, and keep operations healthy.</p>
            </div>
            @if ($metrics['Pending verifications'] > 0)
                <a class="btn btn-primary" href="{{ route('admin.shops.index', ['status' => 'pending']) }}"><i class="bi bi-shop me-2" aria-hidden="true"></i>Review {{ $metrics['Pending verifications'] }} pending {{ str('shop')->plural($metrics['Pending verifications']) }}</a>
            @else
                <a class="btn btn-primary" href="{{ route('admin.monitoring.index') }}"><i class="bi bi-activity me-2" aria-hidden="true"></i>Open operations monitor</a>
            @endif
        </div>
        <section class="workspace-stats mb-4" aria-label="Platform totals">
            @foreach ($metrics as $label => $value)@php $icon = match ($label) { 'Users' => 'people', 'Shops' => 'shop', 'Pending verifications' => 'hourglass-split', default => 'car-front'};
                $route = match ($label) { 'Users' => route('admin.users.index'), 'Shops', 'Pending verifications' => route('admin.shops.index'), default => route('admin.monitoring.index')}; @endphp<a
                    class="workspace-stat" href="{{ $route }}"><i class="bi bi-{{ $icon }}"
                        aria-hidden="true"></i><span><strong>{{ $value }}</strong>{{ $label }}</span><i
            class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>@endforeach</section>
        <div class="row g-4">
            <div class="col-xl-7">
                <section class="surface p-4 h-100" aria-labelledby="pending-shops-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="pending-shops-title">Shop registrations to review</h2>
                            <p>Newest businesses waiting for an administrator decision.</p>
                        </div><a href="{{ route('admin.shops.index') }}">View all</a>
                    </div>
                    <div class="workspace-list">@forelse ($pendingShops as $shop)
                        <article>
                            <div class="workspace-list-icon"><i class="bi bi-shop" aria-hidden="true"></i></div>
                            <div>
                                <h3>{{ $shop->name }}</h3>
                                <p>Owner: {{ $shop->owner->name }} · Submitted {{ $shop->created_at->diffForHumans() }}</p>
                            </div><a class="btn btn-sm btn-outline-primary"
                                href="{{ route('admin.shops.index') }}">Review</a>
                    </article>@empty<div class="workspace-empty"><i class="bi bi-patch-check" aria-hidden="true"></i>
                            <div>
                                <h3>All registrations reviewed</h3>
                                <p>There are no pending shop applications right now.</p>
                            </div>
                        </div>@endforelse
                    </div>
                </section>
            </div>
            <div class="col-xl-5">
                <section class="surface p-4 h-100" aria-labelledby="admin-attention-title">
                    <div class="section-heading">
                        <div>
                            <h2 id="admin-attention-title">Operations requiring attention</h2>
                            <p>Live counts across customer and shop workflows.</p>
                        </div>
                    </div>
                    <div class="admin-attention"><a
                            href="{{ route('admin.monitoring.index', ['module' => 'appointments']) }}"><span><i
                                    class="bi bi-calendar-check" aria-hidden="true"></i>Today’s
                                appointments</span><strong>{{ $todayAppointmentCount }}</strong></a><a
                            href="{{ route('admin.monitoring.index', ['module' => 'repairs']) }}"><span><i
                                    class="bi bi-tools" aria-hidden="true"></i>Active
                                repairs</span><strong>{{ $activeRepairCount }}</strong></a><a
                            href="{{ route('admin.monitoring.index', ['module' => 'payments', 'status' => 'pending']) }}"><span><i
                                    class="bi bi-credit-card" aria-hidden="true"></i>Pending
                                payments</span><strong>{{ $pendingPaymentCount }}</strong></a><a class="danger"
                            href="{{ route('admin.monitoring.index', ['module' => 'emergencies']) }}"><span><i
                                    class="bi bi-life-preserver" aria-hidden="true"></i>Active roadside
                                requests</span><strong>{{ $activeEmergencyCount }}</strong></a></div>
                </section>
            </div>
        </div>
        <section class="surface p-4 mt-4" aria-labelledby="admin-actions-title">
            <div class="section-heading mb-2">
                <div>
                    <h2 id="admin-actions-title">Administration tools</h2>
                    <p>Go directly to the area you need.</p>
                </div>
            </div>
            <div class="workspace-actions"><a href="{{ route('admin.users.index') }}"><i class="bi bi-people"
                        aria-hidden="true"></i><span><strong>Users</strong>Accounts and access</span></a><a
                    href="{{ route('admin.services.index') }}"><i class="bi bi-wrench"
                        aria-hidden="true"></i><span><strong>Services</strong>Manage the catalog</span></a><a
                    href="{{ route('admin.reports.index') }}"><i class="bi bi-bar-chart"
                        aria-hidden="true"></i><span><strong>Reports</strong>Platform analytics</span></a><a
                    href="{{ route('admin.activity-logs.index') }}"><i class="bi bi-clock-history"
                        aria-hidden="true"></i><span><strong>Audit trail</strong>Review admin activity</span></a></div>
        </section>
    @else
        <div class="page-heading">
            <h1>Welcome back, {{ $user->name }}</h1>
            <p>Continue to your shop workspace.</p>
        </div><a class="btn btn-primary" href="{{ route('shop.dashboard') }}">Open shop dashboard</a>
    @endif
</x-layouts.app>
