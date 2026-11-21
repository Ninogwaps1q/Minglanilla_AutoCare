<x-layouts.app title="Subscription billing">
    <div class="page-heading">
        <h1>Subscription billing</h1>
        <p>Review full refunds, download receipts, and reconcile subscription payments.</p>
        <a href="{{ route('admin.paymongo-settlements.index') }}">Review invoice refunds and shop settlements</a>
    </div>
    <x-form-errors />
    <section class="mb-4" aria-labelledby="subscription-refunds-title">
        <h2 class="h4" id="subscription-refunds-title">Refunds needing attention ({{ $refundRequests->total() }})</h2>
        <p class="text-secondary">Full refunds only. Approval submits the refund to PayMongo. Paid access ends only after a successful full refund; other coverage dates stay unchanged.</p>
        @forelse ($refundRequests as $payment)
            <article class="surface p-4 mb-3">
                <h3 class="h5 text-break">{{ $payment->shop->name }} · {{ ucfirst($payment->plan_slug) }}</h3>
                <p>Requested by {{ $payment->shop->owner->name }} on {{ $payment->refund_requested_at?->format('M j, Y') }}.<br>Full amount: <strong>PHP {{ number_format($payment->refund_amount, 2) }}</strong>.<br>Reason: {{ $payment->refund_reason }}</p>
                <p class="small">Coverage: {{ $payment->period_starts_at?->format('M j, Y') }} to {{ $payment->period_ends_at?->format('M j, Y') }} · <a href="{{ route('shop.pricing.receipt', $payment) }}">Download receipt</a></p>
                <x-refund-review :payment="$payment" :review-url="route('admin.subscription-billing.refund', $payment)" :check-url="route('admin.subscription-billing.check', $payment)" />
            </article>
        @empty
            <p class="surface p-4">No subscription refunds are waiting for review or confirmation.</p>
        @endforelse
        {{ $refundRequests->links() }}
    </section>
    <section class="surface p-4 mb-4" aria-labelledby="billing-report-title">
        <h2 class="h4" id="billing-report-title">Billing report</h2>
        <form method="GET" action="{{ route('admin.subscription-billing.index') }}" class="row g-3 align-items-end mb-3">
            <div class="col-sm-4"><label class="form-label" for="billing-from">From</label><input class="form-control" type="date" id="billing-from" name="from" value="{{ $from }}" required></div>
            <div class="col-sm-4"><label class="form-label" for="billing-to">To</label><input class="form-control" type="date" id="billing-to" name="to" value="{{ $to }}" required></div>
            <div class="col-sm-4"><button class="btn btn-primary" type="submit">Apply dates</button></div>
        </form>
        <p class="small text-secondary">Totals use the payment confirmation date and refund completion date within this range. Net collections exclude PayMongo fees. Dates use {{ config('app.timezone') }}.</p>
        <dl class="row mb-3">
            <dt class="col-sm-8">Subscription collections</dt><dd class="col-sm-4">PHP {{ number_format($gross, 2) }}</dd>
            <dt class="col-sm-8">Completed subscription refunds</dt><dd class="col-sm-4">PHP {{ number_format($refunded, 2) }}</dd>
            <dt class="col-sm-8">Net collections before gateway fees</dt><dd class="col-sm-4"><strong>PHP {{ number_format($gross - $refunded, 2) }}</strong></dd>
            <dt class="col-sm-8">Current unsettled invoice balance (all dates, including holds)</dt><dd class="col-sm-4">PHP {{ number_format($unsettledBalance, 2) }}</dd>
        </dl>
        <p class="small">{{ $heldCount }} invoice payment(s) held for refund or dispute. {{ $unknownFees }} payment(s) awaiting fee details are excluded from the unsettled balance.</p>
        <a class="btn btn-outline-primary mb-3" href="{{ route('admin.subscription-billing.export', ['from' => $from, 'to' => $to]) }}">Download billing report (CSV)</a>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption>Subscriptions collected or refunded during the selected dates.</caption>
                <thead><tr><th scope="col">Payment / shop</th><th scope="col">Plan / coverage</th><th scope="col">Collected</th><th scope="col">Refund</th><th scope="col">Receipt</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="text-break">#{{ $payment->id }} · {{ $payment->shop->name }}<br><small>{{ $payment->reviewed_at?->format('M j, Y') }} · {{ $payment->reference }}</small></td>
                            <td>{{ ucfirst($payment->plan_slug) }}<br><small>{{ $payment->period_starts_at?->format('M j, Y') }} to {{ $payment->period_ends_at?->format('M j, Y') }}</small></td>
                            <td>PHP {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->refund_status ? ucfirst(str_replace('_', ' ', $payment->refund_status)) : 'None' }}@if ($payment->refund_status === 'succeeded')<br>PHP {{ number_format($payment->refund_amount, 2) }}<br><small>{{ $payment->refunded_at?->format('M j, Y') }}</small>@endif</td>
                            <td><a href="{{ route('shop.pricing.receipt', $payment) }}" aria-label="Download receipt for subscription payment {{ $payment->id }}">PDF</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No subscription payments or completed refunds for these dates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </section>
    <section class="surface p-4" aria-labelledby="billing-history-title">
        <h2 class="h4" id="billing-history-title">Recent refund and settlement history</h2>
        <p class="small text-secondary">Latest 30 actions across all dates. <a href="{{ route('admin.activity-logs.index') }}">Open the full audit trail</a>.</p>
        <div class="table-responsive"><table class="table">
            <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th></tr></thead>
            <tbody>@forelse ($history as $entry)<tr><td>{{ $entry->created_at->format('M j, Y g:i A') }}</td><td>{{ $entry->user?->name ?? 'PayMongo / system' }}</td><td class="text-break">{{ $entry->description }}</td></tr>@empty<tr><td colspan="3">No refund or settlement actions recorded yet.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
</x-layouts.app>
