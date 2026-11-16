<x-layouts.app title="Shop plans">
    <div class="page-heading"><h1>Shop plans</h1><p>Edit example monthly pricing and review shop plan requests.</p></div>
    <x-form-errors />
    <div class="row g-3 mb-4">
        @foreach ($plans as $plan)
            <div class="col-lg-4">
                <form class="surface p-4 h-100" method="POST" action="{{ route('admin.shop-plans.update', $plan) }}">
                    @csrf @method('PUT')
                    <h2 class="h4">{{ $plan->name }}</h2>
                    <div class="mb-3"><label class="form-label" for="price-{{ $plan->id }}">Monthly price (₱)</label><input class="form-control" id="price-{{ $plan->id }}" name="monthly_price" type="number" min="0" max="1000000" required value="{{ $plan->monthly_price }}" @disabled($plan->slug === 'free')>@if ($plan->slug === 'free')<input type="hidden" name="monthly_price" value="0">@endif</div>
                    <div class="mb-3"><label class="form-label" for="summary-{{ $plan->id }}">Short description</label><input class="form-control" id="summary-{{ $plan->id }}" name="summary" maxlength="255" required value="{{ $plan->summary }}"></div>
                    @foreach ($plan->features as $index => $feature)
                        <div class="mb-3"><label class="form-label" for="feature-{{ $plan->id }}-{{ $index }}">Feature {{ $index + 1 }}</label><input class="form-control" id="feature-{{ $plan->id }}-{{ $index }}" name="features[]" maxlength="120" required value="{{ $feature }}"></div>
                    @endforeach
                    <p class="form-text">Feature text is for the pricing page. Access follows the Free, Basic, and Premium plan rules.</p>
                    @if ($plan->slug !== 'free')<div class="mb-3"><label class="form-label" for="payment-instructions-{{ $plan->id }}">Payment instructions for owners</label><textarea class="form-control" id="payment-instructions-{{ $plan->id }}" name="payment_instructions" rows="3" maxlength="1000">{{ $plan->payment_instructions }}</textarea><div class="form-text">Add the platform's real payment account or in-person instructions before owners submit payment details.</div></div>@endif
                    <button class="btn btn-primary" type="submit">Save {{ $plan->name }} plan</button>
                </form>
            </div>
        @endforeach
    </div>
    <section aria-labelledby="payment-review-title" class="mb-4">
        <h2 id="payment-review-title" class="h4">Payments needing review ({{ $payments->total() }})</h2>
        @forelse ($payments as $payment)
            <form class="surface p-4 mb-3" method="POST" action="{{ route('admin.shop-plan-payments.update', $payment) }}">
                @csrf @method('PATCH')
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><div><strong>{{ $payment->shop->name }}</strong><p class="text-secondary mb-0">{{ $payment->shop->owner->name }} · {{ ucfirst($payment->plan_slug) }} · ₱{{ number_format($payment->amount) }}</p></div><span class="badge text-bg-warning align-self-start">Pending review</span></div>
                <p class="small mb-2">Submitted {{ $payment->created_at->format('M j, Y g:i A') }} @if ($payment->reference) · Reference: {{ $payment->reference }}@endif @if ($payment->proof_path) · <a href="{{ route('admin.shop-plan-payments.proof', $payment) }}">Download private receipt</a>@endif</p>
                <p class="small text-secondary">Check the actual payment amount and recipient before approving. Approval adds one month of access.</p>
                <div class="mb-3"><label class="form-label" for="review-note-{{ $payment->id }}">Review note (required if rejecting)</label><textarea class="form-control" id="review-note-{{ $payment->id }}" name="review_notes" maxlength="1000" rows="2" placeholder="Explain what the owner should correct">{{ old('review_notes') }}</textarea></div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="decision" value="approved" type="submit">Approve payment and activate</button><button class="btn btn-outline-danger" name="decision" value="rejected" type="submit">Reject payment</button></div>
            </form>
        @empty
            <div class="surface p-4"><p class="mb-0 text-secondary">No payment submissions need review.</p></div>
        @endforelse
        {{ $payments->links() }}
    </section>
    <section aria-labelledby="plan-requests-title" class="mb-4">
        <h2 id="plan-requests-title" class="h4">Plan requests ({{ $requests->total() }})</h2>
        <div class="surface p-4">
            @forelse ($requests as $shop)
                <p class="mb-2"><strong>{{ $shop->name }}</strong> requested {{ ucfirst($shop->requested_plan) }}. @if ($shop->planPayments->contains('status', 'pending'))Payment is under review.@elseWaiting for payment details.@endif</p>
            @empty
                <p class="mb-0 text-secondary">No shops are waiting to submit payment details.</p>
            @endforelse
        </div>
        {{ $requests->links() }}
    </section>
    <section aria-labelledby="subscribers-title">
        <h2 id="subscribers-title" class="h4">Subscriptions and trial access</h2>
        <p class="text-secondary">Use manual access changes for corrections or offline arrangements. Pending payments must be reviewed above first.</p>
        @forelse ($subscribers as $shop)
            <form class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-3" method="POST" action="{{ route('admin.shops.plan', $shop) }}">
                @csrf @method('PATCH')
                <div class="me-auto"><strong>{{ $shop->name }}</strong><div class="small text-secondary">{{ $shop->owner->name }} · Current: {{ ucfirst($shop->activePlanSlug()) }} @if ($shop->trial_started_at)· Trial ends {{ $shop->trialEndsAt()->format('M j, Y') }}@endif @if ($shop->plan_expires_at)· Paid through {{ $shop->plan_expires_at->format('M j, Y') }}@endif</div></div>
                <div><label class="form-label" for="subscriber-plan-{{ $shop->id }}">Set plan</label><select class="form-select" id="subscriber-plan-{{ $shop->id }}" name="plan_slug"><option value="free" @selected($shop->plan_slug === 'free')>Free</option><option value="basic" @selected($shop->plan_slug === 'basic')>Basic</option><option value="premium" @selected($shop->plan_slug === 'premium')>Premium</option></select></div>
                <div><label class="form-label" for="subscriber-expiry-{{ $shop->id }}">Access through (paid plans)</label><input class="form-control" id="subscriber-expiry-{{ $shop->id }}" name="plan_expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" value="{{ $shop->plan_expires_at?->isFuture() ? $shop->plan_expires_at->toDateString() : '' }}"></div>
                <button class="btn btn-outline-primary" type="submit">Save access</button>
            </form>
        @empty
            <div class="surface p-4"><p class="mb-0 text-secondary">No subscriptions or trials yet.</p></div>
        @endforelse
        {{ $subscribers->links() }}
    </section>
    <section aria-labelledby="payment-history-title" class="mt-4">
        <h2 id="payment-history-title" class="h4">Payment review history</h2>
        <div class="surface p-3 table-responsive"><table class="table mb-0"><thead><tr><th scope="col">Reviewed</th><th scope="col">Shop</th><th scope="col">Plan</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Period / note</th></tr></thead><tbody>
            @forelse ($reviewedPayments as $payment)
                <tr><td>{{ $payment->reviewed_at?->format('M j, Y') }}</td><td>{{ $payment->shop->name }}</td><td>{{ ucfirst($payment->plan_slug) }}</td><td>₱{{ number_format($payment->amount) }}</td><td>{{ ucfirst($payment->status) }}</td><td>@if ($payment->reference)Reference {{ $payment->reference }} · @endif{{ $payment->period_ends_at ? 'Access through '.$payment->period_ends_at->format('M j, Y') : ($payment->review_notes ?: '—') }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-secondary">No reviewed plan payments yet.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $reviewedPayments->links() }}
    </section>
</x-layouts.app>
