<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('components.billing-document-style')
</head>
<body>
    <div class="heading">
        <p class="brand">MINGLANILLA AUTOCARE HUB</p>
        <h1>Invoice</h1>
        <p><strong>{{ $invoice->invoice_number }}</strong> · Issued {{ $invoice->created_at->format('M j, Y') }}</p>
    </div>

    <table class="columns"><tr>
        <td><strong>FROM</strong><p>{{ $invoice->shop->name }}</p><p>{{ $invoice->shop->isIndependentMechanic() ? 'Independent mobile mechanic · '.$invoice->shop->barangay?->name : $invoice->shop->address }}</p><p>{{ $invoice->shop->phone }}</p></td>
        <td><strong>BILL TO</strong><p>{{ $invoice->customer->name }}</p><p>{{ $invoice->customer->email }}</p><p>Repair: {{ $invoice->repairJob?->job_number ?? 'Service invoice' }}</p></td>
    </tr></table>

    <h2>Services and parts</h2>
    @if ($invoice->repairJob?->appointment?->bookedServices?->isNotEmpty())
        @foreach ($invoice->repairJob->appointment->bookedServices as $bookedService)
            <p class="muted">Booking terms for {{ $bookedService->service?->name ?? 'service' }} (PHP {{ number_format($bookedService->price, 2) }}): labor {{ $bookedService->labor_included === null ? 'not specified' : ($bookedService->labor_included ? 'included' : 'quoted separately') }}; parts {{ $bookedService->parts_included === null ? 'not specified' : ($bookedService->parts_included ? 'included' : 'quoted separately') }}. {{ $bookedService->scope }}</p>
        @endforeach
    @elseif ($invoice->repairJob?->appointment?->service_price !== null)
        <p class="muted">Booking terms for {{ $invoice->repairJob->appointment->service?->name ?? 'service' }}: labor {{ $invoice->repairJob->appointment->service_labor_included === null ? 'not specified' : ($invoice->repairJob->appointment->service_labor_included ? 'included' : 'quoted separately') }}; parts {{ $invoice->repairJob->appointment->service_parts_included === null ? 'not specified' : ($invoice->repairJob->appointment->service_parts_included ? 'included' : 'quoted separately') }}.</p>
        @if ($invoice->repairJob->appointment->service_scope)<p class="muted">{{ $invoice->repairJob->appointment->service_scope }}</p>@endif
    @endif
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
        @if ($invoice->creditNoteTotal() > 0)
            <tr><td>Credit note for refunded service</td><td>−PHP {{ number_format($invoice->creditNoteTotal(), 2) }}</td></tr>
            <tr><td>Adjusted amount due</td><td>PHP {{ number_format($invoice->payableTotal(), 2) }}</td></tr>
        @endif
        <tr><td>Confirmed payments</td><td>PHP {{ number_format($invoice->confirmedPaymentsTotal(), 2) }}</td></tr>
        <tr class="total-row"><td>Balance due</td><td>PHP {{ number_format($invoice->remainingBalance(), 2) }}</td></tr>
    </table>

    <h2>Payment record</h2>
    @forelse ($invoice->payments as $payment)
        <p>PHP {{ number_format($payment->amount, 2) }} · {{ ucfirst($payment->status) }} · {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}@if ($payment->paymongo_method) ({{ config('services.paymongo.method_labels.'.$payment->paymongo_method, ucfirst($payment->paymongo_method)) }})@endif @if ($payment->reference_number) · Ref {{ $payment->reference_number }}@endif</p>
    @empty
        <p class="muted">No payments submitted.</p>
    @endforelse
    <p class="footer">Payment submissions count toward the balance only after shop confirmation. Invoice status: {{ ucfirst($invoice->status) }}.</p>
</body>
</html>
