<x-layouts.public title="Provider pricing">
    @php($canPay = $shop && $shop->verification_status === 'approved' && $shop->shop_status === 'active')
    @php($payMongoEnabled = filled(config('services.paymongo.secret_key')) && filled(config('services.paymongo.webhook_secret')))
    <div class="container py-5">
        <div class="mb-4">
            <h1>Choose a plan for your service profile</h1>
            <p class="text-secondary mb-0">Approved shops and independent mechanics appear on the map with their names and services. Choose a paid plan for online bookings and more tools.</p>
        </div>
        <x-form-errors />
        @if ($shop)
            <section class="surface p-4 mb-4" aria-labelledby="subscription-status-title">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 id="subscription-status-title" class="h4">Current plan: {{ ucfirst($shop->activePlanSlug()) }}</h2>
                        @if ($shop->activePlanEndsAt())
                            <p class="mb-1"><strong>{{ $shop->daysUntilAccessEnds() }} {{ $shop->daysUntilAccessEnds() === 1 ? 'day' : 'days' }} left</strong> · {{ $shop->trialIsActive() ? 'Premium trial' : 'Paid access' }} ends {{ $shop->activePlanEndsAt()->format('M j, Y') }}.</p>
                        @elseif ($shop->trial_started_at)
                            <p class="mb-0 text-secondary">Your Premium trial {{ $shop->trial_revoked_at ? 'was closed' : 'ended '.$shop->trialEndsAt()->format('M j, Y') }}. Your public listing stays visible.</p>
                        @elseif ($shop->plan_expires_at?->isPast())
                            <p class="mb-0 text-secondary">Your paid plan expired {{ $shop->plan_expires_at->format('M j, Y') }}. Choose a plan below to renew.</p>
                        @else
                            <p class="mb-0 text-secondary">Your Free listing has no expiry date.</p>
                        @endif
                    </div>
                    @if ($pendingPayment)
                        <span class="badge text-bg-warning">{{ $pendingPayment->status === 'checkout_pending' ? 'Online checkout in progress' : 'Pending payment review' }}</span>
                    @elseif ($shop->trialIsActive())
                        <span class="badge text-bg-primary">Trial active</span>
                    @else
                        <span class="badge text-bg-secondary">{{ $shop->activePlanSlug() === 'free' ? 'Free listing' : 'Paid plan active' }}</span>
                    @endif
                </div>
                @if ($pendingPayment)
                    <div class="border-top pt-3 mt-3" role="status">
                        <p class="mb-1"><strong>{{ ucfirst($pendingPayment->plan_slug) }} · ₱{{ number_format($pendingPayment->amount) }}</strong> submitted {{ $pendingPayment->created_at->format('M j, Y g:i A') }}.</p>
                        @if ($pendingPayment->status === 'checkout_pending')
                            <p class="mb-2">PayMongo confirmation reserves your next paid month. Current coverage stays unchanged until the new period starts. You can return to the same checkout to finish paying.</p>
                            @if ($payMongoEnabled)
                            <form method="POST" action="{{ route('shop.pricing.checkout') }}">@csrf<input type="hidden" name="plan_slug" value="{{ $pendingPayment->plan_slug }}"><button class="btn btn-primary" type="submit">Continue PayMongo checkout</button></form>
                            @if ($pendingPayment->paymongo_session_id)<button class="btn btn-outline-primary mt-2" type="button" data-payment-check="{{ route('shop.pricing.checkout.check', $pendingPayment) }}">Check payment status</button><span class="small text-secondary ms-2" role="status" aria-live="polite"></span>@endif
                            @if ($pendingPayment->paymongo_session_id)<form method="POST" action="{{ route('shop.pricing.checkout.cancel', $pendingPayment) }}" class="mt-2">@csrf<button class="btn btn-outline-secondary" type="submit">Cancel checkout</button></form>@endif
                            @else
                                <p class="small text-secondary mb-0">Online payment is temporarily unavailable. Your checkout is saved; please contact the administrator.</p>
                            @endif
                        @else
                            <p class="mb-0">An administrator is checking your payment. You do not need to pay or submit again.</p>
                        @endif
                        @if ($pendingPayment->reference)<p class="small text-secondary text-break mb-0 mt-1">Reference: {{ $pendingPayment->reference }}</p>@endif
                    </div>
                @elseif (!$canPay)
                    <p class="mb-0 mt-3">Paid plans and the trial become available once your shop is approved and active.</p>
                @endif
            </section>
        @endif

        <div class="row g-3 align-items-stretch" aria-label="Available plans">
            @foreach ($plans as $plan)
                <div class="col-lg-4">
                    <article class="surface p-4 h-100 d-flex flex-column">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <h2 class="h3 mb-1">{{ $plan->name }}</h2>
                            @if ($plan->slug === 'premium')<span class="badge text-bg-primary">30-day trial</span>@endif
                        </div>
                        <p class="mb-2"><strong class="fs-2">₱{{ number_format($plan->monthly_price) }}</strong><span class="text-secondary"> / month</span></p>
                        <p class="text-secondary">{{ $plan->summary }}</p>
                        <ul class="list-unstyled d-grid gap-2 mb-4">
                            @foreach ($plan->features as $feature)
                                <li><i class="bi bi-check-circle-fill text-primary me-2" aria-hidden="true"></i>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-auto">
                            @if ($shop && $plan->slug === 'free')
                                <span class="text-secondary">Included for every approved provider</span>
                            @elseif ($shop && !$canPay)
                                <span class="text-secondary">Available after approval</span>
                            @elseif ($shop && $pendingPayment)
                                <span class="text-secondary">Payment under review</span>
                            @elseif ($shop)
                                @if ($plan->slug === 'premium' && !$shop->trial_started_at && $shop->activePlanSlug() === 'free' && $scheduledPayments->isEmpty())
                                    <form method="POST" action="{{ route('shop.pricing.trial') }}" class="mb-2">
                                        @csrf
                                        <button class="btn btn-outline-primary w-100" type="submit">Start 30-day trial</button>
                                    </form>
                                @endif
                                <a class="btn btn-outline-primary w-100" href="{{ route('shop.pricing', ['plan' => $plan->slug]) }}#plan-payment" @if ($selectedPlan?->id === $plan->id) aria-current="true" @endif>
                                    {{ $selectedPlan?->id === $plan->id ? 'Selected: '.$plan->name : ($shop->activePlanSlug() === $plan->slug && !$shop->trialIsActive() ? 'Renew '.$plan->name : 'Choose '.$plan->name) }}
                                </a>
                            @elseif (auth()->user()?->isProviderOwner())
                                <a class="btn btn-outline-primary w-100" href="{{ route('shop.register') }}">Register your provider profile</a>
                            @elseif (auth()->check())
                                <span class="text-secondary">Sign in with a shop or mechanic account to choose a plan.</span>
                            @else
                                <a class="btn btn-outline-primary w-100" href="{{ route('register') }}">Create a provider account</a>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        @if ($canPay && !$pendingPayment && $selectedPlan)
            <section id="plan-payment" class="surface p-4 mt-4" aria-labelledby="plan-payment-title" tabindex="-1">
                <h2 id="plan-payment-title" class="h4">Pay for {{ $selectedPlan->name }}</h2>
                <p class="text-secondary">One month: <strong class="text-body">₱{{ number_format($selectedPlan->monthly_price) }}</strong>.</p>
                <p class="small">Upgrades, downgrades, and renewals start after your current trial and all paid coverage end. No paid days are lost. If you do not renew, your shop returns to Free when coverage ends.</p>
                @if ($payMongoEnabled)
                    <p>Pay securely through PayMongo. Choose an available card, e-wallet, or QR Ph method on the checkout page. Confirmation reserves your next paid month; access changes on its start date.</p>
                    <form method="POST" action="{{ route('shop.pricing.checkout') }}">@csrf<input type="hidden" name="plan_slug" value="{{ $selectedPlan->slug }}"><button class="btn btn-primary" type="submit">Pay ₱{{ number_format($selectedPlan->monthly_price) }} with PayMongo</button></form>
                @elseif (config('services.paymongo.legacy_manual_payments') && $selectedPlan->hasPaymentDetails())
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h3 class="h5">Payment details</h3>
                            @if ($selectedPlan->payment_account_name)
                                <dl class="mb-3">
                                    <dt>Account name</dt><dd class="text-break">{{ $selectedPlan->payment_account_name }}</dd>
                                    <dt>Account number</dt><dd class="text-break">{{ $selectedPlan->payment_account_number }}</dd>
                                </dl>
                            @endif
                            @if ($selectedPlan->payment_instructions)
                                <p class="payment-instructions-preline">{{ $selectedPlan->payment_instructions }}</p>
                            @endif
                            @if ($selectedPlan->hasPaymentQr())
                                <figure class="mb-0">
                                    <img class="plan-payment-qr" src="{{ route('shop.pricing.qr', $selectedPlan) }}" alt="Payment QR for {{ $selectedPlan->name }}" width="240" height="240">
                                    <figcaption class="small mt-2"><a href="{{ route('shop.pricing.qr', $selectedPlan) }}" download>Download payment QR</a> to use in your payment app.</figcaption>
                                </figure>
                            @elseif ($selectedPlan->payment_qr_path)
                                <p class="small text-secondary">The QR image is unavailable. Use the account details or payment instructions above.</p>
                            @endif
                        </div>
                        <div class="col-lg-6">
                            <form method="POST" action="{{ route('shop.pricing.payments.store') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="plan_slug" value="{{ $selectedPlan->slug }}">
                                <h3 class="h5">Submit your payment</h3>
                                <p class="small text-secondary">Provide a receipt, a reference number, or both. Your plan activates after approval.</p>
                                <div class="mb-3">
                                    <label class="form-label" for="payment-reference">Payment reference</label>
                                    <input class="form-control @error('reference') is-invalid @enderror" id="payment-reference" name="reference" maxlength="100" value="{{ old('reference') }}" aria-describedby="payment-reference-help">
                                    <div id="payment-reference-help" class="form-text">Use the transaction reference from your payment app or receipt.</div>
                                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="payment-proof">Receipt or payment proof</label>
                                    <input class="form-control @error('proof') is-invalid @enderror" id="payment-proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" aria-describedby="payment-proof-help">
                                    <div id="payment-proof-help" class="form-text">JPG, PNG, WebP, or PDF up to 5 MB. Only you and administrators can view your receipt.</div>
                                    @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn btn-primary" type="submit">Submit {{ $selectedPlan->name }} payment for review</button>
                            </form>
                        </div>
                    </div>
                @else
                    <p class="mb-0">{{ config('services.paymongo.legacy_manual_payments') ? 'Payment details are not available yet. Please check back before sending payment.' : 'PayMongo checkout is temporarily unavailable. Please contact the administrator before making a payment.' }}</p>
                @endif
            </section>
        @endif

        @if ($scheduledPayments->isNotEmpty())
            <section class="surface p-4 mt-4" aria-labelledby="scheduled-plans-title">
                <h2 id="scheduled-plans-title" class="h4">Upcoming paid coverage</h2>
                <p class="text-secondary">Your current plan stays active until its end date.</p>
                <ul class="mb-0">
                    @foreach ($scheduledPayments as $scheduled)
                        <li>{{ ucfirst($scheduled->plan_slug) }}: {{ $scheduled->period_starts_at->format('M j, Y g:i A') }} to {{ $scheduled->period_ends_at->format('M j, Y g:i A') }} @if ($scheduled->refund_status) · Refund {{ str_replace('_', ' ', $scheduled->refund_status) }} @endif</li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($shop && $payments->isNotEmpty())
            <section class="surface p-4 mt-4" aria-labelledby="plan-payment-history-title">
                <h2 id="plan-payment-history-title" class="h4">Payment history</h2>
                <p class="small text-secondary">Subscription refunds are for the full payment only and require admin approval. Eligibility and processing time depend on the payment method. Coverage is removed only when PayMongo confirms the full refund; other paid periods keep their dates.</p>
                <div class="table-responsive">
                    <table class="table plan-payment-history mb-0">
                        <thead><tr><th scope="col">Submitted</th><th scope="col">Plan</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td>{{ $payment->created_at->format('M j, Y') }}</td><td>{{ ucfirst($payment->plan_slug) }}</td><td>₱{{ number_format($payment->amount) }}</td>
                                    <td>{{ $payment->refund_status === 'succeeded' ? 'Refunded' : match ($payment->status) { 'pending' => 'Pending review', 'checkout_pending' => 'Online checkout in progress', 'approved' => 'Paid', default => ucfirst($payment->status) } }}
                                        @if ($payment->refund_status && $payment->refund_status !== 'succeeded')<p class="small mb-0">Refund {{ str_replace('_', ' ', $payment->refund_status) }}</p>@endif
                                    </td>
                                    <td class="text-break">
                                        @if ($payment->review_notes)<p class="mb-1">{{ $payment->review_notes }}</p>@endif
                                        @if ($payment->period_ends_at)<p class="mb-1">Coverage: {{ $payment->period_starts_at?->format('M j, Y g:i A') }} to {{ $payment->period_ends_at->format('M j, Y g:i A') }}</p>@endif
                                        @if ($payment->paymongo_method)<p class="mb-1">Paid with {{ config('services.paymongo.method_labels.'.$payment->paymongo_method, ucfirst($payment->paymongo_method)) }} through PayMongo</p>@endif
                                        @if ($payment->reference)<p class="mb-1">Reference: {{ $payment->reference }}</p>@endif
                                        @if ($payment->hasProof())<a href="{{ route('shop.pricing.payments.proof', $payment) }}">Download receipt</a>@endif
                                        @if ($payment->status === 'approved')<a class="d-block" href="{{ route('shop.pricing.receipt', $payment) }}">Download subscription receipt (PDF)</a>@endif
                                        @if ($payment->refund_review_notes)<p class="small mt-2 mb-1">Refund review: {{ $payment->refund_review_notes }}</p>@endif
                                        @if ($payment->status === 'approved' && $payment->paymongo_payment_id && in_array($payment->refund_status, [null, 'rejected', 'failed'], true))
                                            <details class="mt-2">
                                                <summary>Request a full refund</summary>
                                                <form class="mt-2" method="POST" action="{{ route('shop.pricing.refund', $payment) }}">
                                                    @csrf
                                                    <label class="form-label" for="refund-reason-{{ $payment->id }}">Reason for refund</label>
                                                    <textarea class="form-control mb-2" id="refund-reason-{{ $payment->id }}" name="reason" rows="2" maxlength="500" required>{{ old('reason') }}</textarea>
                                                    <button class="btn btn-outline-primary btn-sm" type="submit">Request full refund of PHP {{ number_format($payment->amount, 2) }}</button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $payments->links() }}</div>
            </section>
        @endif
        <p class="text-secondary small mt-4">Trial access ends automatically after 30 days. PayMongo confirms new plan payments. No automatic recurring charge is taken.</p>
    </div>
<script>
    document.querySelectorAll('[data-payment-check]').forEach((button) => {
        const check = async () => {
            button.disabled = true;
            const result = button.nextElementSibling;
            result.textContent = 'Checking PayMongo…';
            try {
                const response = await fetch(button.dataset.paymentCheck, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
                if (!response.ok) { throw new Error('Payment check failed'); }
                if ((await response.json()).status !== 'checkout_pending') { window.location.reload(); }
                result.textContent = 'Still awaiting payment confirmation.';
            } catch (error) {
                result.textContent = 'Could not check payment. Try again.';
            } finally { button.disabled = false; }
        };
        button.addEventListener('click', check);
        setInterval(() => { if (document.visibilityState === 'visible') { check(); } }, 30000);
    });
</script>
</x-layouts.public>
