<x-layouts.app title="Shop reports">
    <div class="page-heading report-page-heading">
        <h1>Shop reports</h1>
        <p>Service demand, revenue, and inventory health · {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}</p>
    </div>

    @if ($shop === null)
        <div class="surface p-4">
            <h2 class="h4">Register a shop to view reports</h2>
            <p class="text-secondary mb-3">Reports become available after your shop registration is created.</p>
            <a class="btn btn-primary" href="{{ route('shop.register') }}">Register a shop</a>
        </div>
    @else
        <x-report-filters :from="$from" :to="$to" index-route="shop.reports.index" download-route="shop.reports.download" />

        @php
            $metricDisplay = [
                'Appointments' => ['icon' => 'bi-calendar-check', 'scope' => 'Selected period'],
                'Completed repairs' => ['icon' => 'bi-tools', 'scope' => 'Selected period'],
                'Confirmed revenue' => ['icon' => 'bi-cash-stack', 'scope' => 'Selected period', 'currency' => true],
                'Low-stock parts' => ['icon' => 'bi-box-seam', 'scope' => 'Current stock'],
                'Customers served' => ['icon' => 'bi-people', 'scope' => 'Selected period'],
            ];
        @endphp
        <section aria-labelledby="shop-report-summary" class="mb-4">
            <div class="report-section-heading"><h2 id="shop-report-summary">Shop snapshot</h2></div>
            <div class="report-metrics report-metrics--five">
                @foreach ($metricDisplay as $label => $display)
                    <x-report-metric :label="$label" :value="$metrics[$label] ?? 0" :icon="$display['icon']" :scope="$display['scope']" :currency="$display['currency'] ?? false" />
                @endforeach
            </div>
        </section>
        <section aria-labelledby="shop-report-trends" class="mb-4">
            <div class="report-section-heading"><h2 id="shop-report-trends">Performance over time</h2></div>
            <div class="report-visual-grid">
                <div class="surface report-panel">
                    <div class="report-panel__heading"><div><h3>Confirmed revenue</h3><p>Daily payments in PHP</p></div><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
                    @if (array_sum($revenueSeries['values']) === 0.0)
                        <p class="report-empty-note">No confirmed payments in this date range.</p>
                    @endif
                    <div class="report-chart-frame"><canvas role="img" aria-label="Daily confirmed revenue chart; exact figures are available in the daily report data table" class="report-chart" data-report-chart data-label="Revenue (PHP)" data-currency="true" data-labels='@json($revenueSeries['labels'])' data-values='@json($revenueSeries['values'])'></canvas></div>
                </div>
                <div class="surface report-panel">
                    <div class="report-panel__heading"><div><h3>Appointments</h3><p>Daily service demand</p></div><i class="bi bi-calendar-week" aria-hidden="true"></i></div>
                    @if (array_sum($appointmentSeries['values']) === 0.0)
                        <p class="report-empty-note">No appointments scheduled in this date range.</p>
                    @endif
                    <div class="report-chart-frame"><canvas role="img" aria-label="Daily appointments chart; exact figures are available in the daily report data table" class="report-chart" data-report-chart data-label="Appointments" data-labels='@json($appointmentSeries['labels'])' data-values='@json($appointmentSeries['values'])'></canvas></div>
                </div>
            </div>
        </section>

        <section aria-label="Appointments and shop insights" class="report-detail-grid mb-4">
            <div class="surface report-table-panel">
                <div class="report-panel__heading"><div><h2>Recent appointments</h2><p>Latest bookings in the selected period</p></div></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">Date</th><th scope="col">Customer</th><th scope="col">Service</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @forelse ($recentAppointments as $appointment)
                                <tr>
                                    <td>{{ $appointment->appointment_date->format('M j, Y') }}</td>
                                    <td>{{ $appointment->customer?->name ?? 'Customer unavailable' }}</td>
                                    <td>{{ $appointment->service?->name ?? 'Unspecified service' }}</td>
                                    <td><span class="report-status report-status--{{ $appointment->status }}">{{ str_replace('_', ' ', ucfirst($appointment->status)) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-4 text-secondary">No appointments in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="surface report-insights">
                <x-report-appointment-status :counts="$appointmentStatusCounts" />
                <div class="report-insights__divider"></div>
                <div class="report-panel__heading"><div><h2>Most requested services</h2><p>Ranked by appointment count</p></div></div>
                <div class="report-insight-list">
                    @forelse ($popularServices as $appointment)
                        <div class="report-insight-row"><span>{{ $appointment->service?->name ?? 'Unspecified service' }}</span><strong>{{ number_format((int) $appointment->total) }}</strong></div>
                    @empty
                        <p class="text-secondary mb-0">No services requested in this period.</p>
                    @endforelse
                </div>
                <div class="report-insights__divider"></div>
                <div class="report-panel__heading"><div><h2>Low-stock parts</h2><p>Current inventory needing attention</p></div></div>
                <div class="report-insight-list">
                    @forelse ($lowStockParts as $part)
                        <div class="report-insight-row"><span>{{ $part->name }}</span><strong>{{ $part->quantity }} / {{ $part->minimum_stock }}</strong></div>
                    @empty
                        <p class="text-secondary mb-0">No parts are currently low on stock.</p>
                    @endforelse
                </div>
            </div>
        </section>
        <details class="surface p-4 mb-4">
            <summary class="fw-semibold">View daily report data</summary>
            <div class="table-responsive mt-3">
                <table class="table mb-0"><caption>Daily appointments and confirmed payments in the selected date range</caption><thead><tr><th scope="col">Date</th><th scope="col">Appointments</th><th scope="col">Confirmed payments (PHP)</th></tr></thead><tbody>
                    @foreach ($appointmentSeries['labels'] as $index => $date)
                        <tr><th scope="row">{{ $date }}</th><td>{{ $appointmentSeries['values'][$index] ?? 0 }}</td><td>{{ number_format($revenueSeries['values'][$index] ?? 0, 2) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </details>
<details class="surface p-4 mb-4"><summary class="fw-semibold">Recent generated report ranges</summary><div class="table-responsive mt-3"><table class="table mb-0"><thead><tr><th>Type</th><th>Date range</th><th>Generated</th></tr></thead><tbody>@forelse($reportHistory as $report)<tr><td class="text-capitalize">{{ str_replace('_', ' ', $report->report_type) }}</td><td>{{ $report->date_from?->format('M j, Y') }} – {{ $report->date_to?->format('M j, Y') }}</td><td>{{ $report->created_at->diffForHumans() }}</td></tr>@empty<tr><td colspan="3" class="text-secondary">Downloaded PDF reports will appear here.</td></tr>@endforelse</tbody></table></div></details>
    @endif
@push('scripts')
    @vite('resources/js/reports.js')
@endpush
</x-layouts.app>
