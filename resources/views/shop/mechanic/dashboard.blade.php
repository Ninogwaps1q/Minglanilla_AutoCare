<x-layouts.app :title="__('mechanic.dashboard')">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div><h1>{{ __('mechanic.dashboard') }}</h1><p class="text-break">{{ $shop?->name ?? auth()->user()->name }}</p></div>
        @if ($shop)
            <a class="btn btn-outline-primary" href="{{ route('shop.mechanic.availability.index') }}">{{ __($shop->mechanicOnline() ? 'mechanic.online' : 'mechanic.offline') }}</a>
        @endif
    </div>
    @if (! $shop)
        <a class="btn btn-primary" href="{{ route('shop.register') }}">{{ __('account_ui.send_registration') }}</a>
    @else
        @php($applicationStatus = $shop->shop_status === 'suspended' ? 'suspended' : $shop->verification_status)
        @if ($applicationStatus !== 'approved')
            <div class="alert alert-info">{{ __('mechanic.verification_status') }} · {{ __('mechanic.verification.'.$applicationStatus) }} <a class="alert-link ms-2" href="{{ route('shop.profile.edit') }}">{{ __('mechanic.certifications') }}</a></div>
        @endif
        <section class="surface rounded p-3 p-lg-4 mb-3">
            <h2 class="h5 mb-3">{{ __('mechanic.today') }}</h2>
            <ul class="list-unstyled mb-0">
                @forelse ($todayAppointments as $appointment)
                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom">
                        <a class="text-break" href="{{ route('shop.appointments.index', ['focus' => $appointment->id]) }}">{{ $appointment->customer->name }} · {{ $appointment->vehicle->plate_number }}</a>
                        <span class="text-secondary">{{ $appointment->appointment_time->format('g:i A') }}</span>
                    </li>
                @empty
                    <li class="text-secondary">{{ __('mechanic.no_appointments') }}</li>
                @endforelse
            </ul>
        </section>
        <div class="row g-3">
            @foreach ([
                ['pending', $pendingCount, route('shop.appointments.index', ['status' => 'pending']), 'calendar-event'],
                ['upcoming', $upcomingCount, route('shop.appointments.index', ['status' => 'confirmed']), 'house'],
                ['active_repairs', $activeRepairs, route('shop.repair-jobs.index'), 'wrench'],
                ['emergencies', $emergencies, route('shop.emergency-requests.index'), 'life-preserver'],
                ['completed', $completed, route('shop.repair-jobs.index', ['status' => 'completed']), 'check-circle'],
                ['receipts_this_month', 'PHP '.number_format($confirmedReceipts, 2), route('shop.mechanic.earnings.index'), 'wallet2'],
                ['rating', $shop->total_reviews ? number_format($shop->average_rating, 1).'/5 ('.$shop->total_reviews.')' : __('mechanic.no_reviews'), route('shop.reviews.index'), 'star'],
                ['subscription', ucfirst($shop->activePlanSlug()), route('shop.pricing'), 'tags'],
            ] as [$label, $value, $url, $icon])
                <div class="col-12 col-sm-6 col-xl-4">
                    <a class="surface rounded d-flex align-items-start gap-3 h-100 p-3 text-decoration-none" href="{{ $url }}">
                        <i class="bi bi-{{ $icon }} text-primary" aria-hidden="true"></i>
                        <span><span class="d-block text-body fw-semibold">{{ __('mechanic.'.$label) }}</span><span class="text-secondary">{{ $value }}</span></span>
                    </a>
                </div>
            @endforeach
        </div>
        @if ($reminders->isNotEmpty())
            <section class="surface rounded p-3 p-lg-4 mt-3">
                <h2 class="h5">{{ __('mechanic.reminders') }}</h2>
                <ul class="list-unstyled mb-0">
                    @foreach ($reminders as $reminder)
                        <li class="border-top py-2 text-break"><a href="{{ $reminder->destinationFor(auth()->user()) }}">{{ $reminder->localizedTitle() }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-layouts.app>
