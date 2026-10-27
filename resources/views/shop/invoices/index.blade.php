<x-layouts.app title="Shop invoices">
    <div class="page-heading">
        <h1>Bills and payments</h1>
        <p>Confirm a payment only after you have received the money. Explain any rejection so the customer knows what to
            correct.</p>
    </div>
    <x-form-errors />

    <nav class="notification-filters mb-3" aria-label="Filter invoices">
        <a class="notification-filter {{ request()->query('status') === 'pending' ? '' : 'is-active' }}"
            href="{{ route('shop.invoices.index') }}">All invoices</a>
        <a class="notification-filter {{ request()->query('status') === 'pending' ? 'is-active' : '' }}"
            href="{{ route('shop.invoices.index', ['status' => 'pending']) }}">Payments awaiting review</a>
    </nav>

    <div class="d-grid gap-3">
        @forelse ($invoices as $invoice)
            <article class="surface p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $invoice->invoice_number }}</h2>
                        <p class="text-secondary mb-1">{{ $invoice->customer->name }} ·
                            {{ $invoice->repairJob?->job_number ?? 'Manual invoice' }}</p><a
                            href="{{ route('shop.invoices.show', $invoice) }}">View invoice</a>
                    </div>
                    <div class="text-sm-end"><span
                            class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ $invoice->status === 'paid' ? 'Paid' : 'Awaiting payment' }}</span>
                        <p class="mb-0 mt-1"><strong>PHP {{ number_format($invoice->total, 2) }}</strong><br><span
                                class="text-secondary small">Remaining: PHP
                                {{ number_format($invoice->remainingBalance(), 2) }}</span></p>
                    </div>
                </div>
                @forelse ($invoice->payments->where('status', 'pending') as $payment)
                    <section class="payment-instructions mb-2" aria-label="Payment awaiting review">
                        <div class="d-flex flex-wrap justify-content-between gap-2"><strong>PHP
                                {{ number_format($payment->amount, 2) }} ·
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</strong><span
                                class="small text-secondary">{{ $payment->created_at->format('M j, Y g:i A') }}</span></div>
                        @if ($payment->reference_number)
                        <p class="small mb-2">Reference: {{ $payment->reference_number }}</p>@endif
                        <p class="small text-secondary mb-2">Check your cash receipt or transfer record before deciding.</p>
                        <div class="d-flex flex-wrap align-items-start gap-2">
                            <form method="POST" action="{{ route('shop.payments.update', $payment) }}">@csrf
                                @method('PATCH')<input type="hidden" name="status" value="confirmed"><button type="submit"
                                    class="btn btn-sm btn-success">Confirm received</button></form>
                            <details class="payment-reject-details">
                                <summary class="btn btn-sm btn-outline-danger">Reject with reason</summary>
                                <form method="POST" action="{{ route('shop.payments.update', $payment) }}" class="mt-2">@csrf
                                    @method('PATCH')<input type="hidden" name="status" value="rejected"><label
                                        class="form-label" for="payment-reason-{{ $payment->id }}">What should the customer
                                        correct?</label><textarea id="payment-reason-{{ $payment->id }}"
                                        class="form-control mb-2" name="review_note" maxlength="500" rows="2"
                                        required></textarea><button type="submit" class="btn btn-sm btn-outline-danger">Send
                                        rejection</button></form>
                            </details>
                        </div>
                    </section>
                @empty
                    <p class="text-secondary mb-0">No payment is waiting for review.</p>
                @endforelse
            </article>
        @empty
            <div class="surface p-4">
                <h2 class="h5">No invoices yet</h2>
                <p class="text-secondary mb-0">Complete a repair job, then issue its invoice here.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
</x-layouts.app>