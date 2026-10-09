<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 38px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #14233d; line-height: 1.5; }
        h1 { font-size: 24px; margin: 0 0 5px; color: #0b1b36; }
        h2 { font-size: 13px; margin: 24px 0 8px; color: #0b1b36; }
        p { margin: 3px 0; }
        .brand { color: #e66b13; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #53647d; }
        .heading { border-bottom: 2px solid #e66b13; padding-bottom: 16px; }
        .columns { width: 100%; margin-top: 22px; }
        .columns td { width: 50%; vertical-align: top; }
        .columns td:last-child { text-align: right; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .details th { background: #edf2f8; color: #0b1b36; text-align: left; padding: 9px; border-bottom: 1px solid #cbd5e1; }
        .details td { padding: 9px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .numeric { text-align: right !important; white-space: nowrap; }
        .totals { width: 42%; margin: 18px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 0; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .total-row { border-top: 1px solid #cbd5e1; font-weight: bold; font-size: 12px; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #cbd5e1; color: #53647d; }
    </style>
</head>
<body>
    <div class="heading">
        <p class="brand">MINGLANILLA AUTOCARE HUB</p>
        <h1>Invoice</h1>
        <p><strong>{{ $invoice->invoice_number }}</strong> · Issued {{ $invoice->created_at->format('M j, Y') }}</p>
    </div>

    <table class="columns"><tr>
        <td><strong>FROM</strong><p>{{ $invoice->shop->name }}</p><p>{{ $invoice->shop->address }}</p><p>{{ $invoice->shop->phone }}</p></td>
        <td><strong>BILL TO</strong><p>{{ $invoice->customer->name }}</p><p>{{ $invoice->customer->email }}</p><p>Repair: {{ $invoice->repairJob?->job_number ?? 'Service invoice' }}</p></td>
    </tr></table>

    <h2>Services and parts</h2>
    <table class="details">
        <thead><tr><th>Description</th><th class="numeric">Qty</th><th class="numeric">Unit price</th><th class="numeric">Amount</th></tr></thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr><td>{{ $item->description }}</td><td class="numeric">{{ $item->quantity }}</td><td class="numeric">PHP {{ number_format($item->unit_price, 2) }}</td><td class="numeric">PHP {{ number_format($item->subtotal, 2) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No itemized lines were recorded for this invoice.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td>PHP {{ number_format($invoice->subtotal, 2) }}</td></tr>
        @if ((float) $invoice->discount > 0)<tr><td>Discount</td><td>− PHP {{ number_format($invoice->discount, 2) }}</td></tr>@endif
        @if ((float) $invoice->tax > 0)<tr><td>Tax</td><td>PHP {{ number_format($invoice->tax, 2) }}</td></tr>@endif
        <tr class="total-row"><td>Total</td><td>PHP {{ number_format($invoice->total, 2) }}</td></tr>
        <tr><td>Confirmed payments</td><td>PHP {{ number_format($invoice->confirmedPaymentsTotal(), 2) }}</td></tr>
        <tr class="total-row"><td>Balance due</td><td>PHP {{ number_format($invoice->remainingBalance(), 2) }}</td></tr>
    </table>

    <h2>Payment record</h2>
    @forelse ($invoice->payments as $payment)
        <p>PHP {{ number_format($payment->amount, 2) }} · {{ ucfirst($payment->status) }} · {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}@if ($payment->reference_number) · Ref {{ $payment->reference_number }}@endif</p>
    @empty
        <p class="muted">No payments submitted.</p>
    @endforelse
    <p class="footer">Payment submissions count toward the balance only after shop confirmation. Invoice status: {{ ucfirst($invoice->status) }}.</p>
</body>
</html>
