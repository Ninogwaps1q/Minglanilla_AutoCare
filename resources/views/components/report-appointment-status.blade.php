@props(['counts'])

@php
    $total = array_sum($counts);
    $colors = [
        'pending' => '#e99025',
        'confirmed' => '#3b82f6',
        'in_progress' => '#8b5cf6',
        'completed' => '#16a375',
        'cancelled' => '#e15b64',
    ];
    $labels = array_map(fn ($status) => ucfirst(str_replace('_', ' ', $status)), array_keys($counts));
    $chartColors = array_map(fn ($status) => $colors[$status] ?? '#94a3b8', array_keys($counts));
@endphp

<section {{ $attributes->merge(['aria-label' => 'Appointment status breakdown']) }}>
    <div class="report-panel__heading">
        <div><h2>Appointment status</h2><p>Current status of bookings in the selected period</p></div>
    </div>
    @if ($total > 0)
        <div class="report-status-overview">
            <div class="report-doughnut">
                <canvas aria-hidden="true" data-report-status-chart
                    data-labels='@json($labels)'
                    data-values='@json(array_values($counts))'
                    data-colors='@json($chartColors)'></canvas>
                <div class="report-doughnut__total"><strong>{{ number_format($total) }}</strong><span>appointments</span></div>
            </div>
            <dl class="report-status-legend">
                @foreach ($counts as $status => $count)
                    <div>
                        <dt><span class="report-status-dot" style="--status-color: {{ $colors[$status] ?? '#94a3b8' }}" aria-hidden="true"></span>{{ ucfirst(str_replace('_', ' ', $status)) }}</dt>
                        <dd>{{ number_format($count) }} <span>({{ number_format($count / $total * 100, 1) }}%)</span></dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
        <p class="report-empty-note mb-0">No appointments in this date range. Choose a wider range to see the status breakdown.</p>
    @endif
</section>
