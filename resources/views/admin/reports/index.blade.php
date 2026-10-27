<x-layouts.app title="Administration reports">
    <div class="page-heading report-page-heading">
        <h1>System reports</h1>
        <p>Business activity and payment performance · {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}</p>
    </div>
    <x-report-filters :from="$from" :to="$to" index-route="admin.reports.index"
        download-route="admin.reports.download" />
    @php
        $primaryMetrics = [
            'Confirmed payments' => ['icon' => 'bi-cash-stack', 'scope' => 'Selected period', 'currency' => true],
            'Appointments' => ['icon' => 'bi-calendar-check', 'scope' => 'Selected period'],
            'Active customers' => ['icon' => 'bi-people', 'scope' => 'Current total'],
            'Approved shops' => ['icon' => 'bi-shop', 'scope' => 'Current total'],
        ];
        $activityMetrics = [
            'Completed repairs' => 'bi-tools',
            'Emergency requests' => 'bi-life-preserver',
            'Published reviews' => 'bi-star',
        ];
    @endphp
    <section aria-labelledby="admin-report-summary" class="mb-4">
        <div class="report-section-heading">
            <h2 id="admin-report-summary">Business snapshot</h2><span>At a glance</span>
        </div>
        <div class="report-metrics report-metrics--four">
            @foreach ($primaryMetrics as $label => $display)
                <x-report-metric :label="$label" :value="$metrics[$label] ?? 0" :icon="$display['icon']"
                    :scope="$display['scope']" :currency="$display['currency'] ?? false" />
            @endforeach
        </div>
    </section>
    <section aria-labelledby="admin-report-trends" class="mb-4">
        <div class="report-section-heading">
            <h2 id="admin-report-trends">Performance over time</h2><span>Selected date range</span>
        </div>
        <div class="report-visual-grid">
            <div class="surface report-panel">
                <div class="report-panel__heading">
                    <div>
                        <h3>Confirmed payments</h3>
                        <p>Daily payments in PHP</p>
                    </div><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                </div>
                @if (array_sum($revenueSeries['values']) === 0.0)
                    <p class="report-empty-note">No confirmed payments in this date range.</p>
                @endif
                <div class="report-chart-frame"><canvas role="img"
                        aria-label="Daily confirmed payments chart; exact figures are available in the daily report data table"
                        class="report-chart" data-report-chart data-label="Payments (PHP)" data-currency="true"
                        data-labels='@json($revenueSeries['labels'])'
                        data-values='@json($revenueSeries['values'])'></canvas></div>
            </div>
            <div class="surface report-panel">
                <div class="report-panel__heading">
                    <div>
                        <h3>Appointments</h3>
                        <p>Daily service demand</p>
                    </div><i class="bi bi-calendar-week" aria-hidden="true"></i>
                </div>
                @if (array_sum($appointmentSeries['values']) === 0.0)
                    <p class="report-empty-note">No appointments scheduled in this date range.</p>
                @endif
                <div class="report-chart-frame"><canvas role="img"
                        aria-label="Daily appointments chart; exact figures are available in the daily report data table"
                        class="report-chart" data-report-chart data-label="Appointments"
                        data-labels='@json($appointmentSeries['labels'])'
                        data-values='@json($appointmentSeries['values'])'></canvas></div>
            </div>
        </div>
    </section>
    <section aria-labelledby="admin-report-activity" class="surface report-activity mb-4">
        <div class="report-section-heading">
            <h2 id="admin-report-activity">Service activity</h2><span>Selected date range</span>
        </div>
        <div class="report-activity-grid">
            @foreach ($activityMetrics as $label => $icon)
                <div class="report-activity-item"><i class="bi {{ $icon }}"
                        aria-hidden="true"></i><span>{{ $label }}</span><strong>{{ number_format((int) ($metrics[$label] ?? 0)) }}</strong>
                </div>
            @endforeach
        </div>
    </section>
    <section aria-label="Appointments and platform insights" class="report-detail-grid mb-4">
        <div class="surface report-table-panel">
            <div class="report-panel__heading">
                <div>
                    <h2>Recent appointments</h2>
                    <p>Latest bookings in the selected period</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col">Shop</th>
                            <th scope="col">Service</th>
                            <th scope="col">Date</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentAppointments as $appointment)
                            <tr>
                                <td>{{ $appointment->customer?->name ?? 'Customer unavailable' }}</td>
                                <td>{{ $appointment->shop?->name ?? 'Shop unavailable' }}</td>
                                <td>{{ $appointment->service?->name ?? 'Unspecified service' }}</td>
                                <td>{{ $appointment->appointment_date->format('M j, Y') }}</td>
                                <td><span
                                        class="report-status report-status--{{ $appointment->status }}">{{ str_replace('_', ' ', ucfirst($appointment->status)) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-secondary">No appointments in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="surface report-insights">
            <x-report-appointment-status :counts="$appointmentStatusCounts" />
            <div class="report-insights__divider"></div>
            <div class="report-panel__heading">
                <div>
                    <h2>Top shops</h2>
                    <p>By confirmed payments</p>
                </div>
            </div>
            <div class="report-insight-list">
                @forelse ($topShops as $payment)
                    <div class="report-insight-row">
                        <span>{{ $payment->shop?->name ?? 'Shop unavailable' }}</span><strong>₱{{ number_format((float) $payment->revenue, 2) }}</strong>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No confirmed payments in this period.</p>
                @endforelse
            </div>
            <div class="report-insights__divider"></div>
            <div class="report-panel__heading">
                <div>
                    <h2>Shop verification</h2>
                    <p>Current shop records</p>
                </div>
            </div>
            <div class="report-insight-list">
                @forelse ($shopStatuses as $status)
                    <div class="report-insight-row"><span
                            class="text-capitalize">{{ str_replace('_', ' ', $status->verification_status) }}</span><strong>{{ number_format((int) $status->total) }}</strong>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No shop records yet.</p>
                @endforelse
            </div>
            <div class="report-insights__divider"></div>
            <div class="report-panel__heading">
                <div>
                    <h2>Most requested services</h2>
                    <p>Ranked by appointment count</p>
                </div>
            </div>
            <div class="report-insight-list">
                @forelse ($popularServices as $appointment)
                    <div class="report-insight-row">
                        <span>{{ $appointment->service?->name ?? 'Unspecified service' }}</span><strong>{{ number_format((int) $appointment->total) }}</strong>
                    </div>
                @empty
                    <p class="text-secondary mb-0">No appointments in this period.</p>
                @endforelse
            </div>
        </div>
    </section>
    <details class="surface p-4 mb-4">
        <summary class="fw-semibold">View daily report data</summary>
        <div class="table-responsive mt-3">
            <table class="table mb-0">
                <caption>Daily appointments and confirmed payments in the selected date range</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Appointments</th>
                        <th scope="col">Confirmed payments (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($appointmentSeries['labels'] as $index => $date)
                        <tr>
                            <th scope="row">{{ $date }}</th>
                            <td>{{ $appointmentSeries['values'][$index] ?? 0 }}</td>
                            <td>{{ number_format($revenueSeries['values'][$index] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    <details class="surface p-4 mb-4">
        <summary class="fw-semibold">Generated report history</summary>
        <div class="table-responsive mt-3">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Generated by</th>
                        <th>Type</th>
                        <th>Date range</th>
                        <th>Generated</th>
                    </tr>
                </thead>
                <tbody>@forelse($reportHistory as $report)
                    <tr>
                        <td>{{ $report->generatedBy?->name ?? 'Deleted user' }}</td>
                        <td class="text-capitalize">{{ str_replace('_', ' ', $report->report_type) }}</td>
                        <td>{{ $report->date_from?->format('M j, Y') }} – {{ $report->date_to?->format('M j, Y') }}</td>
                        <td>{{ $report->created_at->diffForHumans() }}</td>
                </tr>@empty<tr>
                        <td colspan="4" class="text-secondary">No generated report history yet.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </details>
    @push('scripts')
        @vite('resources/js/reports.js')
    @endpush
</x-layouts.app>