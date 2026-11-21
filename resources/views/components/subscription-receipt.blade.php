<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Subscription receipt {{ $payment->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.6; color: #111827; }
        h1 { font-size: 24px; color: #0b1b36; margin-bottom: 4px; }
        h2 { font-size: 15px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 9px; border-bottom: 1px solid #d6dee9; overflow-wrap: anywhere; }
        th { width: 30%; background: #eef3f8; }
        .muted { color: #52627a; }
    </style>
</head>
<body>
    <h1>Subscription receipt</h1>
    <p>{{ config('app.name') }} · Receipt SUB-{{ $payment->id }}<br>Payment confirmed {{ $payment->reviewed_at?->format('M j, Y g:i A') }}</p>
    <h2>{{ $payment->shop->name }}</h2>
    <p>{{ $payment->shop->owner->name }}<br>{{ $payment->shop->owner->email }}</p>
    <table>
        <tr><th>Plan</th><td>{{ ucfirst($payment->plan_slug) }}</td></tr>
        <tr><th>Amount received</th><td>PHP {{ number_format($payment->amount, 2) }}</td></tr>
        <tr><th>Payment method</th><td>{{ $payment->paymongo_method ? config('services.paymongo.method_labels.'.$payment->paymongo_method, $payment->paymongo_method) : 'Manual payment' }}</td></tr>
        <tr><th>Reference</th><td>{{ $payment->reference ?? $payment->paymongo_payment_id ?? 'SUB-'.$payment->id }}</td></tr>
        <tr><th>Coverage</th><td>{{ $payment->period_starts_at?->format('M j, Y g:i A') }} to {{ $payment->period_ends_at?->format('M j, Y g:i A') }} ({{ config('app.timezone') }})</td></tr>
        <tr><th>Refund status</th><td>{{ $payment->refund_status ? ucfirst(str_replace('_', ' ', $payment->refund_status)) : 'No refund' }}@if ($payment->refund_status === 'succeeded')<br>PHP {{ number_format($payment->refund_amount, 2) }} refunded {{ $payment->refunded_at?->format('M j, Y') }}<br>Refund reference: {{ $payment->paymongo_refund_id }}<br>This paid period no longer grants access.@endif</td></tr>
    </table>
    <p class="muted">This document acknowledges the recorded subscription payment. It is not a tax invoice. No automatic recurring charge is taken.</p>
</body>
</html>
