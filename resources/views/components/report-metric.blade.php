@props([
    'label',
    'value',
    'icon' => 'bi-graph-up',
    'scope' => 'Selected period',
    'currency' => false,
])

<div class="metric-card report-metric">
    <div class="report-metric__top">
        <span class="report-metric__label">{{ $label }}</span>
        <span class="report-metric__icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    </div>
    <strong class="metric-value report-metric__value">{{ $currency ? '₱'.number_format((float) $value, 2) : number_format((int) $value) }}</strong>
    <span class="report-metric__scope">{{ $scope }}</span>
</div>
