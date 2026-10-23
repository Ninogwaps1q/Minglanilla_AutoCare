@props(['from', 'to', 'indexRoute', 'downloadRoute'])

@php
    $today = now()->toImmutable()->startOfDay();
    $presets = [
        'Last 7 days' => $today->subDays(6),
        'Last 30 days' => $today->subDays(29),
        'Last 90 days' => $today->subDays(89),
        'This month' => $today->startOfMonth(),
    ];
@endphp

<div {{ $attributes->merge(['class' => 'surface report-toolbar mb-4']) }}>
    <nav class="report-date-presets" aria-label="Report date shortcuts">
        @foreach ($presets as $label => $start)
            @php($selected = $from->toDateString() === $start->toDateString() && $to->toDateString() === $today->toDateString())
            <a @class(['btn btn-sm btn-outline-secondary', 'active' => $selected])
                href="{{ route($indexRoute, ['from' => $start->toDateString(), 'to' => $today->toDateString()]) }}"
                @if ($selected) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="row g-3 align-items-end" method="GET" action="{{ route($indexRoute) }}">
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="from">From</label>
            <input class="form-control" id="from" name="from" type="date" value="{{ $from->toDateString() }}" required>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="to">To</label>
            <input class="form-control" id="to" name="to" type="date" value="{{ $to->toDateString() }}" required>
        </div>
        <div class="col-sm-auto d-grid">
            <button class="btn btn-primary" type="submit">Apply range</button>
        </div>
        <div class="col-sm-auto ms-lg-auto d-grid">
            <a class="btn btn-outline-primary" href="{{ route($downloadRoute, ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Download PDF
            </a>
        </div>
    </form>
    <x-form-errors />
</div>
