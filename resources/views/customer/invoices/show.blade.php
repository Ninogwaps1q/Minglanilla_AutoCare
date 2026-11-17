<x-layouts.app title="Invoice {{ $invoice->invoice_number }}">
    @if (in_array($invoice->repairJob?->quotation?->status, ['accepted', 'approved'], true))
        <div class="price-agreement mb-3"><strong>Customer-approved estimate: PHP {{ number_format($invoice->repairJob->quotation->total, 2) }}</strong><span>Final invoice: PHP {{ number_format($invoice->total, 2) }}</span></div>
    @endif
    @if ($invoice->repairJob?->appointment?->service_price !== null)
        <p class="small text-secondary">Booking terms for {{ $invoice->repairJob->appointment->service?->name ?? 'service' }}: labor {{ $invoice->repairJob->appointment->service_labor_included === null ? 'not specified' : ($invoice->repairJob->appointment->service_labor_included ? 'included' : 'quoted separately') }}; parts {{ $invoice->repairJob->appointment->service_parts_included === null ? 'not specified' : ($invoice->repairJob->appointment->service_parts_included ? 'included' : 'quoted separately') }}.@if ($invoice->repairJob->appointment->service_scope) {{ $invoice->repairJob->appointment->service_scope }}@endif</p>
    @endif
    <div class="d-print-none text-end mb-3"><a class="btn btn-primary" href="{{ route('customer.invoices.pdf', $invoice) }}">Download PDF</a></div>
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h1>Invoice {{ $invoice->invoice_number }}</h1><p class="text-secondary mb-0">{{ $invoice->shop->name }} · {{ $invoice->created_at->format('M j, Y') }}</p></div><button class="btn btn-outline-primary d-print-none" type="button" onclick="window.print()">Print invoice</button></div>
    <article class="surface p-4"><div class="row mb-4"><div class="col-sm-6"><strong>Bill to</strong><p class="mb-0">{{ $invoice->customer->name }}<br>{{ $invoice->customer->email }}</p></div><div class="col-sm-6 text-sm-end"><strong>Repair job</strong><p class="mb-0">{{ $invoice->repairJob?->job_number ?? 'Service invoice' }}<br>Status: {{ ucfirst($invoice->status) }}</p></div></div><div class="table-responsive"><table class="table"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Subtotal</th></tr></thead><tbody>@foreach ($invoice->items as $item)<tr><td>{{ $item->description }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">PHP {{ number_format($item->unit_price, 2) }}</td><td class="text-end">PHP {{ number_format($item->subtotal, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">PHP {{ number_format($invoice->total, 2) }}</th></tr></tfoot></table></div><div class="row mt-4"><div class="col-sm-6"><strong>Payments</strong>@forelse ($invoice->payments as $payment)<p class="mb-1">PHP {{ number_format($payment->amount, 2) }} · {{ match ($payment->status) { 'checkout_pending' => 'PayMongo checkout in progress', 'confirmed' => $payment->paymongo_payment_id ? 'Confirmed by PayMongo' : 'Confirmed by shop', default => ucfirst($payment->status) } }}@if ($payment->paymongo_method) · {{ config('services.paymongo.method_labels.'.$payment->paymongo_method, ucfirst($payment->paymongo_method)) }}@endif</p>@empty<p class="text-secondary">No payments submitted.</p>@endforelse</div><div class="col-sm-6 text-sm-end"><strong>Remaining balance</strong><p class="h4">PHP {{ number_format($invoice->remainingBalance(), 2) }}</p></div></div></article>
    @foreach ($invoice->payments->whereNotNull('paymongo_payment_id') as $payment)
        <section class="surface p-4 mt-3 d-print-none" aria-label="Refund for PayMongo payment">
            <h2 class="h5">PayMongo payment PHP {{ number_format($payment->amount, 2) }}</h2>
            @if ($payment->refund_status && $payment->refund_status !== 'rejected')
                <p class="mb-0">Refund: <strong>{{ ucfirst($payment->refund_status) }}</strong>@if($payment->refund_amount) · PHP {{ number_format($payment->refund_amount, 2) }}@endif</p>
            @else
                @if ($payment->refund_status === 'rejected')<p class="text-secondary">Your previous refund request was declined. You may submit another request.</p>@endif
                @if ($payment->settlement_status === 'settled')<p class="text-secondary">This payment has already been transferred to the shop. Contact support for a refund review.</p>@else
                <form method="POST" action="{{ route('customer.payments.refund.request', $payment) }}" class="row g-2 align-items-end">@csrf
                    <div class="col-md-3"><label class="form-label" for="refund-amount-{{ $payment->id }}">Refund amount (PHP)</label><input class="form-control" id="refund-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="{{ $payment->amount }}" step="0.01" required></div>
                    <div class="col-md-6"><label class="form-label" for="refund-reason-{{ $payment->id }}">Reason</label><input class="form-control" id="refund-reason-{{ $payment->id }}" name="reason" maxlength="500" required></div>
                    <div class="col-md-3"><button class="btn btn-outline-primary" type="submit">Request refund</button></div>
                </form>
                @endif
            @endif
        </section>
    @endforeach
</x-layouts.app>
