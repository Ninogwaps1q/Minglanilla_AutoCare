<x-layouts.app title="My payments">
    <div class="page-heading">
        <h1>Bills and payments</h1>
        <p>Submit a payment for a repair invoice, then wait for the shop to confirm it. A submitted payment is not yet marked paid.</p>
    </div>
    @include('components.form-errors')

    <div class="row g-3">
        @forelse ($invoices as $invoice)
            @php($pendingTotal = (float) $invoice->payments->where('status', 'pending')->sum('amount'))
            @php($amountAvailable = max(0, round($invoice->remainingBalance() - $pendingTotal, 2)))
            <div class="col-12">
                <article class="surface p-4">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <div class="d-flex flex-wrap justify-content-between gap-3">
                                <div>
                                    <h2 class="h5 mb-1">{{ $invoice->shop->name }}</h2>
                                    <p class="text-secondary mb-3">{{ $invoice->invoice_number }} · {{ $invoice->repairJob?->job_number ?? 'Repair invoice' }}</p>
                                </div>
                                <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }} align-self-start">{{ $invoice->status === 'paid' ? 'Paid' : ($pendingTotal > 0 ? 'Shop review pending' : 'Payment due') }}</span>
                            </div>
                            <ul class="mb-3">@foreach ($invoice->items as $item)<li>{{ $item->description }} — {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}</li>@endforeach</ul>
                            <p class="mb-1"><strong>Total: ₱{{ number_format($invoice->total, 2) }}</strong></p>
                            <p class="mb-1">Remaining after confirmed payments: ₱{{ number_format($invoice->remainingBalance(), 2) }}</p>
                            @if ($pendingTotal > 0)<p class="small text-secondary mb-0">₱{{ number_format($pendingTotal, 2) }} is awaiting the shop's review.</p>@endif
                            <a class="small" href="{{ route('customer.invoices.show', $invoice) }}">View or download invoice</a>
                        </div>
                        <div class="col-lg-5">
                            @if ($invoice->status === 'paid')
                                <p class="text-success mb-0">The shop has confirmed full payment.</p>
                            @elseif ($amountAvailable <= 0)
                                <p class="mb-0">Your submitted payment covers the remaining balance. Wait for the shop to confirm it. If rejected, you can submit a corrected payment.</p>
                            @else
                                <h3 class="h6">Record a payment for shop review</h3>
                                <p class="small text-secondary">First pay the shop using its details below. Then send this record so the shop can confirm receipt. This form does not transfer money.</p>
                                <form method="POST" action="{{ route('customer.payments.store', $invoice) }}" class="row g-2">
                                    @csrf
                                    <div class="col-sm-6"><label class="form-label" for="amount-{{ $invoice->id }}">Amount paid (PHP)</label><input id="amount-{{ $invoice->id }}" class="form-control" type="number" step="0.01" min="0.01" max="{{ number_format($amountAvailable, 2, '.', '') }}" name="amount" value="{{ old('amount', number_format($amountAvailable, 2, '.', '')) }}" required></div>
                                    <div class="col-sm-6"><label class="form-label" for="method-{{ $invoice->id }}">How you paid</label><select id="method-{{ $invoice->id }}" class="form-select" name="payment_method" data-payment-method required><option value="cash" @selected(old('payment_method') === 'cash')>Cash at the shop</option>@if ($invoice->shop->gcash_number)<option value="gcash" @selected(old('payment_method') === 'gcash')>GCash</option>@endif @if ($invoice->shop->bank_transfer_details)<option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank transfer</option>@endif<option value="other" @selected(old('payment_method') === 'other')>Other arrangement</option></select></div>
                                    <div class="col-12"><div class="payment-instructions rounded p-3" aria-live="polite"><div data-method-instructions="cash"><strong>Pay cash at {{ $invoice->shop->name }}</strong><p class="mb-0">{{ $invoice->shop->address }} · {{ $invoice->shop->phone }}</p></div>@if ($invoice->shop->gcash_number)<div data-method-instructions="gcash" hidden><strong>Send GCash to {{ $invoice->shop->gcash_number }}</strong><p class="mb-0">Check the recipient name in GCash before sending. Save your reference number.</p></div>@endif @if ($invoice->shop->bank_transfer_details)<div data-method-instructions="bank_transfer" hidden><strong>Bank transfer details</strong><p class="mb-0 payment-instructions-preline">{{ $invoice->shop->bank_transfer_details }}</p></div>@endif<div data-method-instructions="other" hidden><strong>Confirm the arrangement with the shop first</strong><p class="mb-0">Contact {{ $invoice->shop->name }} at {{ $invoice->shop->phone }} before recording another payment method.</p></div></div></div>
                                    <div class="col-12"><label class="form-label" for="reference-{{ $invoice->id }}">Transaction reference (if applicable)</label><input id="reference-{{ $invoice->id }}" class="form-control" name="reference_number" value="{{ old('reference_number') }}" maxlength="255"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-primary">I paid the shop — send for review</button></div>
                                </form>
                            @endif
                            @if ($invoice->payments->isNotEmpty())<div class="mt-3"><h3 class="h6 mb-2">Payment history</h3><ol class="payment-history mb-0">@foreach ($invoice->payments->sortByDesc('id') as $payment)<li><strong>PHP {{ number_format($payment->amount, 2) }}</strong> · {{ match ($payment->status) { 'confirmed' => 'Confirmed by shop', 'rejected' => 'Rejected — correct and submit again', default => 'Waiting for shop review' } }}<span>{{ $payment->created_at->format('M j, Y g:i A') }}</span>@if ($payment->status === 'rejected' && $payment->review_note)<p class="mb-0">Reason: {{ $payment->review_note }}</p>@endif</li>@endforeach</ol></div>@endif
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-4 text-secondary">No invoices yet. The shop will issue one after your repair is complete.</div></div>
        @endforelse
    </div>
    {{ $invoices->links() }}
    <script>
        document.querySelectorAll('[data-payment-method]').forEach((select) => {
            const updateInstructions = () => {
                const form = select.closest('form');
                form.querySelectorAll('[data-method-instructions]').forEach((details) => {
                    details.hidden = details.dataset.methodInstructions !== select.value;
                });
            };
            select.addEventListener('change', updateInstructions);
            updateInstructions();
        });
    </script>
</x-layouts.app>
