<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('extensions.estimate.title') }} {{ $quotation->quotation_number }}</title>
    @include('components.billing-document-style')
</head>
<body>
    <div class="heading">
        <p class="brand">MINGLANILLA AUTOCARE HUB</p>
        <h1>{{ __('extensions.estimate.title') }}</h1>
        <p>{{ $quotation->quotation_number }} · <strong>{{ __('extensions.estimate.status.'.$status) }}</strong></p>
        @if ($quotation->valid_until)
            <p>{{ __('extensions.estimate.valid_until', ['date' => $quotation->valid_until->format('M j, Y')]) }}</p>
        @endif
    </div>

    <table class="columns"><tr>
        <td><strong>{{ __('extensions.estimate.shop') }}</strong>
            <p>{{ $quotation->shop?->name }}</p>
            <p>{{ $quotation->shop?->isIndependentMechanic() ? $quotation->shop?->barangay?->name : $quotation->shop?->address }}</p>
            <p>{{ $quotation->shop?->phone }}</p>
        </td>
        <td><strong>{{ __('extensions.estimate.customer') }}</strong>
            <p>{{ $quotation->customer?->name }}</p>
            <p>{{ $quotation->vehicle?->make }} {{ $quotation->vehicle?->model }} · {{ $quotation->vehicle?->plate_number }}</p>
        </td>
    </tr></table>

    <h2>{{ __('extensions.estimate.items') }}</h2>
    @php
        $services = $quotation->appointment?->bookedServices ?? collect();
        if ($services->isEmpty() && $quotation->appointment?->service) {
            $services = collect([(object) [
                'service' => $quotation->appointment->service,
                'labor_included' => $quotation->appointment->service_labor_included,
                'parts_included' => $quotation->appointment->service_parts_included,
                'scope' => $quotation->appointment->service_scope,
            ]]);
        }
    @endphp
    @foreach ($services as $service)
        <p class="muted">{{ $service->service?->name }}
            · {{ __('extensions.estimate.labor') }} {{ __('extensions.estimate.inclusion.'.match ($service->labor_included) { true => 'yes', false => 'no', default => 'unknown' }) }}
            · {{ __('extensions.estimate.parts') }} {{ __('extensions.estimate.inclusion.'.match ($service->parts_included) { true => 'yes', false => 'no', default => 'unknown' }) }}
        </p>
        @if ($service->scope)<p class="muted">{{ $service->scope }}</p>@endif
    @endforeach
    @if ($quotation->notes)<p class="muted">{{ $quotation->notes }}</p>@endif

    <table class="details">
        <thead><tr>
            <th>{{ __('extensions.estimate.description') }}</th>
            <th class="numeric">{{ __('extensions.estimate.quantity') }}</th>
            <th class="numeric">{{ __('extensions.estimate.unit_price') }}</th>
            <th class="numeric">{{ __('extensions.estimate.amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($quotation->items as $item)
            <tr>
                <td>{{ $item->description }}
                    @if ($item->type || $item->service_id)<br><span class="muted">{{ __('extensions.estimate.type.'.($item->type ?? 'service')) }}</span>@endif
                </td>
                <td class="numeric">{{ $item->quantity }}</td>
                <td class="numeric">PHP {{ number_format($item->unit_price, 2) }}</td>
                <td class="numeric">PHP {{ number_format($item->subtotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>{{ __('extensions.estimate.subtotal') }}</td><td>PHP {{ number_format($quotation->subtotal, 2) }}</td></tr>
        @if ((float) $quotation->discount > 0)<tr><td>{{ __('extensions.estimate.discount') }}</td><td>− PHP {{ number_format($quotation->discount, 2) }}</td></tr>@endif
        @if ((float) $quotation->tax > 0)<tr><td>{{ __('extensions.estimate.tax') }}</td><td>PHP {{ number_format($quotation->tax, 2) }}</td></tr>@endif
        <tr class="total-row"><td>{{ __('extensions.estimate.total') }}</td><td>PHP {{ number_format($quotation->total, 2) }}</td></tr>
    </table>
</body>
</html>
