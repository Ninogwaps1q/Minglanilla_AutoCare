<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 34px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        h1, h2 { color: #102a43; margin: 0; }
        h1 { font-size: 22px; }
        h2 { border-bottom: 1px solid #d8e1ea; font-size: 14px; margin: 24px 0 8px; padding-bottom: 5px; }
        .brand { border-bottom: 3px solid #f97316; margin-bottom: 20px; padding-bottom: 14px; }
        .muted { color: #5d6b7d; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        th, td { border: 1px solid #d8e1ea; padding: 7px 8px; text-align: left; }
        th { background: #eef3f7; color: #102a43; }
        .value { font-weight: bold; text-align: right; }
        .footer { border-top: 1px solid #d8e1ea; color: #6b7280; margin-top: 26px; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="brand">
        <h1>{{ $title }}</h1>
        <div class="muted">{{ $ownerName }}</div>
        <div class="muted">Reporting period: {{ $from->format('F j, Y') }} to {{ $to->format('F j, Y') }}</div>
    </div>

    <h2>Performance summary</h2>
    <table>
        <tbody>
            @foreach ($metrics as $label => $value)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="value">{{ str_contains(strtolower($label), 'payment') || str_contains(strtolower($label), 'revenue') ? 'PHP '.number_format((float) $value, 2) : number_format((float) $value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Most requested services</h2>
    <table>
        <thead><tr><th>Service</th><th class="value">Requests</th></tr></thead>
        <tbody>
            @forelse ($popularServices as $service)
                <tr><td>{{ $service->service?->name ?? 'Unspecified service' }}</td><td class="value">{{ $service->total }}</td></tr>
            @empty
                <tr><td colspan="2">No service requests in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    @isset($lowStockParts)
        <h2>Low-stock parts</h2>
        <table>
            <thead><tr><th>Part</th><th class="value">Current / Minimum</th></tr></thead>
            <tbody>
                @forelse ($lowStockParts as $part)
                    <tr><td>{{ $part->name }}</td><td class="value">{{ $part->quantity }} / {{ $part->minimum_stock }}</td></tr>
                @empty
                    <tr><td colspan="2">No low-stock parts.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endisset

    @isset($shopStatuses)
        <h2>Shop verification status</h2>
        <table>
            <thead><tr><th>Status</th><th class="value">Shops</th></tr></thead>
            <tbody>
                @forelse ($shopStatuses as $status)
                    <tr><td>{{ ucfirst($status->verification_status) }}</td><td class="value">{{ $status->total }}</td></tr>
                @empty
                    <tr><td colspan="2">No shop records.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endisset

    <div class="footer">Generated {{ now()->format('F j, Y g:i A') }} · Minglanilla AutoCare Hub</div>
</body>
</html>
