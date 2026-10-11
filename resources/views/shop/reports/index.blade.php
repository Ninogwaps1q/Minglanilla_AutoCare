<x-layouts.app title="Shop reports">
    <div class="page-heading">
        <h1>Shop reports</h1>
        <p>Appointments, confirmed revenue, and inventory signals from {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}.</p>
    </div>

    @if ($shop === null)
        <div class="surface p-4">
            <h2 class="h4">Register a shop to view reports</h2>
            <p class="text-secondary mb-3">Reports become available after your shop registration is created.</p>
            <a class="btn btn-primary" href="{{ route('shop.register') }}">Register a shop</a>
        </div>
    @else
        <div class="surface p-4 mb-4">
            <form class="row g-3 align-items-end" method="GET" action="{{ route('shop.reports.index') }}">
                <div class="col-sm-5 col-lg-3"><label class="form-label" for="from">From</label><input class="form-control" id="from" name="from" type="date" value="{{ $from->toDateString() }}"></div>
                <div class="col-sm-5 col-lg-3"><label class="form-label" for="to">To</label><input class="form-control" id="to" name="to" type="date" value="{{ $to->toDateString() }}"></div>
                <div class="col-sm-2 col-lg-auto d-grid"><button class="btn btn-primary" type="submit">Apply range</button></div>
                <div class="col-lg-auto"><a class="btn btn-outline-secondary" href="{{ route('shop.reports.index') }}">Last 30 days</a></div>
            </form>
            <x-form-errors />
        </div>

        <div class="row g-3 mb-4">@foreach ($metrics as $label => $value)
            <div class="col-sm-6 col-xl-3"><div class="metric-card"><div class="text-secondary mb-2">{{ $label }}</div><div class="metric-value">@if (str_contains(strtolower($label), 'revenue'))₱{{ number_format($value, 2) }}@else{{ $value }}@endif</div></div></div>
        @endforeach</div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6"><div class="surface p-4 h-100"><h2 class="h5">Appointments per day</h2><div class="report-chart-frame"><canvas role="img" aria-label="Daily report chart; summary figures appear above" class="report-chart" data-report-chart data-label="Appointments" data-labels='@json($appointmentSeries['labels'])' data-values='@json($appointmentSeries['values'])'></canvas></div></div></div>
            <div class="col-lg-6"><div class="surface p-4 h-100"><h2 class="h5">Confirmed revenue per day</h2><div class="report-chart-frame"><canvas role="img" aria-label="Daily report chart; summary figures appear above" class="report-chart" data-report-chart data-label="Revenue (PHP)" data-currency="true" data-labels='@json($revenueSeries['labels'])' data-values='@json($revenueSeries['values'])'></canvas></div></div></div>
        </div>

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
        <details class="surface p-4 mb-4"><summary class="fw-semibold">Recent generated report ranges</summary><div class="table-responsive mt-3"><table class="table mb-0"><thead><tr><th>Type</th><th>Date range</th><th>Generated</th></tr></thead><tbody>@forelse($reportHistory as $report)<tr><td class="text-capitalize">{{ str_replace('_', ' ', $report->report_type) }}</td><td>{{ $report->date_from?->format('M j, Y') }} – {{ $report->date_to?->format('M j, Y') }}</td><td>{{ $report->created_at->diffForHumans() }}</td></tr>@empty<tr><td colspan="3" class="text-secondary">Apply a date range to create report history.</td></tr>@endforelse</tbody></table></div></details>
        <div class="row g-4">
            <div class="col-lg-6"><div class="surface p-4 h-100"><h2 class="h5">Most requested services</h2><div class="list-group list-group-flush">@forelse ($popularServices as $appointment)<div class="list-group-item px-0 d-flex justify-content-between"><span>{{ $appointment->service?->name ?? 'Unspecified service' }}</span><strong>{{ $appointment->total }}</strong></div>@empty<div class="text-secondary">No appointments in this period.</div>@endforelse</div></div></div>
            <div class="col-lg-6"><div class="surface p-4 h-100"><h2 class="h5">Low-stock parts</h2><div class="list-group list-group-flush">@forelse ($lowStockParts as $part)<div class="list-group-item px-0 d-flex justify-content-between"><span>{{ $part->name }}</span><strong>{{ $part->quantity }} / {{ $part->minimum_stock }}</strong></div>@empty<div class="text-secondary">No parts currently meet the low-stock threshold.</div>@endforelse</div></div></div>
        </div>
    @endif
@push('scripts')
    @vite('resources/js/reports.js')
@endpush
</x-layouts.app>
