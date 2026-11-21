<x-layouts.app title="PayMongo settlements">
    <div class="page-heading">
        <h1>PayMongo shop settlements</h1>
        <p>Reserve the exact amount before sending a shop transfer. Upload the reference and proof after the transfer.</p>
        <a href="{{ route('admin.subscription-billing.index') }}">Subscription refunds, billing report, and approval history</a>
    </div>
    <x-form-errors />
    <section id="dispute-lookup" class="surface p-4 mb-4" aria-labelledby="dispute-lookup-title">
        <h2 id="dispute-lookup-title" class="h4">Track a dispute</h2>
        <form method="GET" action="{{ route('admin.paymongo-settlements.index') }}#dispute-lookup" class="row g-2 align-items-end">
            <div class="col-md-8"><label class="form-label" for="payment-lookup-id">PayMongo payment ID</label><input class="form-control" id="payment-lookup-id" name="payment_id" value="{{ $paymentLookupId }}" maxlength="100" required></div>
            <div class="col-md-4"><button class="btn btn-outline-primary" type="submit">Find payment</button></div>
        </form>
        @if ($paymentLookup)
            <div class="border-top mt-3 pt-3">
                <p><strong>{{ $paymentLookup->invoice->invoice_number }} · {{ $paymentLookup->shop->name }}</strong><br>Customer paid PHP {{ number_format($paymentLookup->amount, 2) }} · Settlement {{ $paymentLookup->settlement_status ?? 'pending' }}</p>
                @if ($paymentLookup->dispute_status)<p>Dispute: {{ ucfirst($paymentLookup->dispute_status) }} · {{ $paymentLookup->paymongo_dispute_id }} · PHP {{ number_format($paymentLookup->dispute_amount, 2) }} · {{ $paymentLookup->dispute_reason }}</p>@endif
                <form method="POST" action="{{ route('admin.paymongo-settlements.dispute', $paymentLookup) }}" class="row g-2 align-items-end">
                    @csrf @method('PATCH')
                    <div class="col-md-4"><label class="form-label" for="dispute-id">Dispute ID</label><input class="form-control" id="dispute-id" name="dispute_id" value="{{ $paymentLookup->paymongo_dispute_id }}" maxlength="100" required></div>
                    <div class="col-md-4"><label class="form-label" for="dispute-status">Status</label><select class="form-select" id="dispute-status" name="dispute_status"><option value="open" @selected($paymentLookup->dispute_status === 'open')>Open</option><option value="won" @selected($paymentLookup->dispute_status === 'won')>Won</option><option value="lost" @selected($paymentLookup->dispute_status === 'lost')>Lost</option><option value="resolved" @selected($paymentLookup->dispute_status === 'resolved')>Resolved</option></select></div>
                    <div class="col-md-4"><label class="form-label" for="dispute-amount">Amount (PHP)</label><input class="form-control" id="dispute-amount" name="dispute_amount" type="number" min="0.01" step="0.01" value="{{ $paymentLookup->dispute_amount ?? $paymentLookup->amount }}" required></div>
                    <div class="col-md-8"><label class="form-label" for="dispute-reason">Reason</label><input class="form-control" id="dispute-reason" name="dispute_reason" maxlength="255" value="{{ $paymentLookup->dispute_reason }}" required></div>
                    <div class="col-md-4"><button class="btn btn-primary" type="submit">Save dispute</button></div>
                </form>
            </div>
        @elseif ($paymentLookupId !== '')
            <p class="text-danger mt-3 mb-0">No PayMongo payment with that ID was found.</p>
        @endif
    </section>
    <section class="mb-4" aria-labelledby="refund-requests-title">
        <h2 id="refund-requests-title" class="h4">Refunds needing attention ({{ $refundRequests->total() }})</h2>
        @forelse ($refundRequests as $refund)
            <div class="surface p-4 mb-3">
                <h3 class="h5">{{ $refund->invoice->invoice_number }} · {{ $refund->shop->name }}</h3>
                <p>Requested by {{ $refund->customer->name }}: PHP {{ number_format($refund->refund_amount, 2) }}. Reason: {{ $refund->refund_reason }}</p>
                <x-refund-review :payment="$refund" :review-url="route('admin.paymongo-refunds.update', $refund)" :check-url="route('admin.paymongo-refunds.check', $refund)" />
            </div>
        @empty
            <p class="text-secondary">No refunds need review.</p>
        @endforelse
        {{ $refundRequests->links() }}
    </section>
    <section aria-labelledby="pending-settlements-title">
        <h2 id="pending-settlements-title" class="h4">Awaiting shop settlement ({{ $pendingPayments->total() }})</h2>
        @forelse ($pendingPayments as $payment)
            @php($availableForShop = (float) ($payment->paymongo_net_amount ?? $payment->amount) - ($payment->refund_status === 'succeeded' ? (float) $payment->refund_amount : 0))
            <article class="surface p-4 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div><h3 class="h5 mb-1">{{ $payment->shop->name }}</h3><p class="text-secondary mb-0">{{ $payment->invoice->invoice_number }} · {{ $payment->customer->name }}</p></div>
                    <span class="badge text-bg-warning align-self-start">Settlement pending</span>
                </div>
                <dl class="row mb-3">
                    <dt class="col-sm-4">Customer paid</dt><dd class="col-sm-8">PHP {{ number_format($payment->amount, 2) }}</dd>
                    @if ($payment->paymongo_method)<dt class="col-sm-4">Payment method</dt><dd class="col-sm-8">{{ config('services.paymongo.method_labels.'.$payment->paymongo_method, ucfirst($payment->paymongo_method)) }}</dd>@endif
                    @if ($payment->paymongo_net_amount !== null)<dt class="col-sm-4">Net after PayMongo fees</dt><dd class="col-sm-8">PHP {{ number_format($payment->paymongo_net_amount, 2) }}@if ($payment->paymongo_fee !== null) · Fee PHP {{ number_format($payment->paymongo_fee, 2) }}@endif</dd>@endif
                    <dt class="col-sm-4">Refund</dt><dd class="col-sm-8">{{ $payment->refund_status ? ucfirst(str_replace('_', ' ', $payment->refund_status)).' · PHP '.number_format($payment->refund_amount, 2) : 'None' }}</dd>
                    <dt class="col-sm-4">Available for shop before platform fee</dt><dd class="col-sm-8">PHP {{ number_format(max(0, $availableForShop), 2) }}</dd>
                    <dt class="col-sm-4">Dispute</dt><dd class="col-sm-8">{{ $payment->dispute_status ? ucfirst($payment->dispute_status) : 'None' }}</dd>
                    <dt class="col-sm-4">PayMongo payment ID</dt><dd class="col-sm-8 text-break">{{ $payment->paymongo_payment_id }}</dd>
                    <dt class="col-sm-4">Received</dt><dd class="col-sm-8">{{ $payment->reviewed_at?->format('M j, Y g:i A') ?? $payment->updated_at->format('M j, Y g:i A') }}</dd>
                </dl>
                <form method="POST" action="{{ route('admin.paymongo-settlements.reserve', $payment) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4"><label class="form-label" for="platform-fee-{{ $payment->id }}">Platform fee (PHP)</label><input class="form-control" id="platform-fee-{{ $payment->id }}" name="platform_fee" type="number" min="0" step="0.01" value="0.00" required></div>
                    <div class="col-md-4"><label class="form-label" for="settlement-amount-{{ $payment->id }}">Amount to reserve (PHP)</label><input class="form-control" id="settlement-amount-{{ $payment->id }}" name="settlement_amount" type="number" min="0.01" max="{{ $payment->amount }}" step="0.01" value="{{ number_format(max(0, $availableForShop), 2, '.', '') }}" required><div class="form-text">Net after PayMongo fee, refund, and platform fee. Must match exactly.</div></div>
                    <div class="col-md-4"><button class="btn btn-primary" type="submit" @disabled($payment->paymongo_fee === null || $payment->paymongo_net_amount === null || $availableForShop <= 0 || in_array($payment->refund_status, \App\Services\PayMongoRefunds::HOLDS_SETTLEMENT, true) || in_array($payment->dispute_status, ['open', 'lost'], true))>Reserve before transfer</button></div>
                </form>
                @if (in_array($payment->dispute_status, ['open', 'lost'], true) || in_array($payment->refund_status, \App\Services\PayMongoRefunds::HOLDS_SETTLEMENT, true))<p class="text-danger small mt-2 mb-0">Settlement is on hold while the refund or dispute needs resolution.</p>@endif
                @if ($availableForShop <= 0)<p class="text-secondary small mt-2 mb-0">No shop balance remains after the confirmed refund.</p>@endif
                @if ($payment->paymongo_fee === null || $payment->paymongo_net_amount === null)
                    <p class="text-danger small mt-2 mb-0">PayMongo fee details are missing. Refresh them before recording a shop transfer.</p>
                    <form method="POST" action="{{ route('admin.paymongo-settlements.refresh', $payment) }}" class="mt-2">@csrf<button class="btn btn-outline-primary" type="submit">Refresh PayMongo details</button></form>
                @endif
                <a class="btn btn-outline-primary mt-2" href="{{ route('admin.paymongo-settlements.index', ['payment_id' => $payment->paymongo_payment_id]) }}#dispute-lookup">Review dispute for this payment</a>
            </article>
        @empty
            <div class="surface p-4"><p class="text-secondary mb-0">No PayMongo payment is awaiting settlement.</p></div>
        @endforelse
        <div class="mt-3">{{ $pendingPayments->links() }}</div>
    </section>
    <section class="mt-4" aria-labelledby="reserved-title">
        <h2 id="reserved-title" class="h4">Reserved, awaiting transfer proof ({{ $reservedPayments->count() }})</h2>
        @forelse ($reservedPayments as $payment)
            <article class="surface p-4 mb-3">
                <h3 class="h5">{{ $payment->shop->name }} · {{ $payment->invoice->invoice_number }}</h3>
                <p>Transfer exactly <strong>PHP {{ number_format($payment->settlement_amount, 2) }}</strong>. Platform fee: PHP {{ number_format($payment->platform_fee, 2) }}. Reserved by {{ $payment->settlementReservedBy?->name ?? 'admin' }} on {{ $payment->settlement_reserved_at?->format('M j, Y g:i A') }}.</p>
                <form method="POST" action="{{ route('admin.paymongo-settlements.update', $payment) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf @method('PATCH')
                    <div class="col-md-5"><label class="form-label" for="reserved-reference-{{ $payment->id }}">Bank transfer reference</label><input class="form-control" id="reserved-reference-{{ $payment->id }}" name="settlement_reference" maxlength="255" required></div>
                    <div class="col-md-5"><label class="form-label" for="reserved-proof-{{ $payment->id }}">Transfer proof</label><input class="form-control" id="reserved-proof-{{ $payment->id }}" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                    <div class="col-md-2"><button class="btn btn-primary" type="submit">Record transfer</button></div>
                </form>
                <form method="POST" action="{{ route('admin.paymongo-settlements.release', $payment) }}" class="row g-2 align-items-end mt-3">
                    @csrf
                    <div class="col-md-8"><label class="form-label" for="release-reason-{{ $payment->id }}">If no transfer occurred, explain why</label><input class="form-control" id="release-reason-{{ $payment->id }}" name="reason" minlength="10" maxlength="1000" required></div>
                    <div class="col-md-4"><button class="btn btn-outline-danger" type="submit">Release reservation</button></div>
                </form>
            </article>
        @empty
            <p class="text-secondary">No reserved transfers.</p>
        @endforelse
    </section>
    <section class="mt-4" aria-labelledby="settled-title">
        <h2 id="settled-title" class="h4">Recent settlements</h2>
        <div class="surface p-4 table-responsive">
            <table class="table mb-0"><thead><tr><th scope="col">Shop</th><th scope="col">Invoice</th><th scope="col">Transferred</th><th scope="col">Reference</th><th scope="col">Date</th></tr></thead><tbody>
                @forelse ($settledPayments as $payment)
                    <tr><td>{{ $payment->shop->name }}</td><td>{{ $payment->invoice->invoice_number }}</td><td>PHP {{ number_format($payment->settlement_amount, 2) }}<small class="d-block">Platform fee PHP {{ number_format($payment->platform_fee, 2) }}</small></td><td class="text-break">{{ $payment->settlement_reference }} @if($payment->settlement_proof_path)<a href="{{ route('admin.paymongo-settlements.proof', $payment) }}">Proof</a>@endif</td><td>{{ $payment->settled_at?->format('M j, Y') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-secondary">No settlements recorded yet.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>
</x-layouts.app>
