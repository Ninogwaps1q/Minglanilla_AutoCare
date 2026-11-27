<x-layouts.app title="Shop plans">
    <div class="page-heading"><h1>Shop plans</h1><p>{{ config('services.paymongo.legacy_manual_payments') ? 'Review manual payments and manage subscriptions and pricing.' : 'Manage subscriptions and pricing. New plan payments go through PayMongo.' }}</p><a href="{{ route('admin.paymongo-settlements.index') }}">Review PayMongo settlements for shops</a></div>
    <x-form-errors />
    <section aria-labelledby="payment-review-title" class="mb-4">
        <h2 id="payment-review-title" class="h4">Payments needing review ({{ $payments->total() }})</h2>
        @forelse ($payments as $payment)
            <article class="surface p-4 mb-3" aria-labelledby="payment-{{ $payment->id }}-title">
                <div class="row g-4">
                    <div class="col-lg-4">
                        @if ($payment->hasImageProof())
                            <a href="{{ route('admin.shop-plan-payments.preview', $payment) }}" target="_blank" rel="noopener" aria-label="Open receipt for {{ $payment->shop->name }} in a new tab">
                                <img class="plan-payment-receipt" src="{{ route('admin.shop-plan-payments.preview', $payment) }}" alt="Payment receipt from {{ $payment->shop->name }}" loading="lazy">
                            </a>
                        @else
                            <div class="payment-instructions h-100 d-flex flex-column justify-content-center">
                                <p class="fw-semibold mb-1">{{ $payment->hasProof() ? 'PDF receipt attached' : 'No receipt image available' }}</p>
                                <p class="text-secondary small mb-0">{{ $payment->hasProof() ? 'Download the receipt to check the transfer.' : 'Check the reference number against your payment records.' }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-8">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h3 id="payment-{{ $payment->id }}-title" class="h5 text-break mb-1">{{ $payment->shop->name }}</h3>
                                <p class="text-secondary mb-0">{{ $payment->shop->owner->name }} · Submitted {{ $payment->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <span class="badge text-bg-warning">Pending review</span>
                        </div>
                        <dl class="row mb-2">
                            <dt class="col-sm-4">Plan / amount</dt><dd class="col-sm-8">{{ ucfirst($payment->plan_slug) }} · ₱{{ number_format($payment->amount) }}</dd>
                            <dt class="col-sm-4">Reference number</dt><dd class="col-sm-8 text-break">{{ $payment->reference ?: 'Not provided — check the receipt' }}</dd>
                        </dl>
                        @if ($payment->hasProof())
                            <p><a href="{{ route('admin.shop-plan-payments.proof', $payment) }}">Download private receipt</a></p>
                        @endif
                        <p class="small text-secondary">Confirm the amount and recipient in your payment records. Approval adds one month of access.</p>
                        <form method="POST" action="{{ route('admin.shop-plan-payments.update', $payment) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="review_payment_id" value="{{ $payment->id }}">
                            <div class="mb-3">
                                <label class="form-label" for="review-note-{{ $payment->id }}">Review note (required if rejecting)</label>
                                <textarea class="form-control" id="review-note-{{ $payment->id }}" name="review_notes" maxlength="1000" rows="2" placeholder="Explain what the owner should correct">{{ (string) old('review_payment_id') === (string) $payment->id ? old('review_notes') : '' }}</textarea>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary" name="decision" value="approved" type="submit">Approve payment and activate</button>
                                <button class="btn btn-outline-danger" name="decision" value="rejected" type="submit">Reject payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="surface p-4"><p class="mb-0 text-secondary">No earlier manual payment submissions need review. New PayMongo payments activate automatically after confirmation.</p></div>
        @endforelse
        {{ $payments->links() }}
    </section>

    <section class="mb-4" aria-labelledby="plan-settings-title">
        <h2 id="plan-settings-title" class="h4">Plan pricing</h2>
        <p class="text-secondary">Open a plan to edit its price and features. Owners choose their payment method on PayMongo checkout.</p>
        <div class="row g-3 align-items-start">
            @foreach ($plans as $plan)
                @php($editingPlan = (string) old('editing_plan_id') === (string) $plan->id)
                <div class="col-lg-4">
                    <details class="surface p-4" @if ($editingPlan) open @endif>
                        <summary class="fw-semibold">{{ $plan->name }} · ₱{{ number_format($plan->monthly_price) }} / month</summary>
                        <form class="mt-3" method="POST" action="{{ route('admin.shop-plans.update', $plan) }}" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <input type="hidden" name="editing_plan_id" value="{{ $plan->id }}">
                            <div class="mb-3">
                                <label class="form-label" for="price-{{ $plan->id }}">Monthly price (₱)</label>
                                <input class="form-control" id="price-{{ $plan->id }}" name="monthly_price" type="number" min="0" max="1000000" required value="{{ $editingPlan ? old('monthly_price', $plan->monthly_price) : $plan->monthly_price }}" @disabled($plan->slug === 'free')>
                                @if ($plan->slug === 'free')<input type="hidden" name="monthly_price" value="0">@endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="summary-{{ $plan->id }}">Short description</label>
                                <input class="form-control" id="summary-{{ $plan->id }}" name="summary" maxlength="255" required value="{{ $editingPlan ? old('summary', $plan->summary) : $plan->summary }}">
                            </div>
                            @foreach ($plan->features as $index => $feature)
                                <div class="mb-3">
                                    <label class="form-label" for="feature-{{ $plan->id }}-{{ $index }}">Feature {{ $index + 1 }}</label>
                                    <input class="form-control" id="feature-{{ $plan->id }}-{{ $index }}" name="features[]" maxlength="120" required value="{{ $editingPlan ? old('features.'.$index, $feature) : $feature }}">
                                </div>
                            @endforeach
                            <p class="form-text">Feature text appears on the pricing page. Access follows the Free, Basic, and Premium plan rules.</p>
                            @php($mechanicFeatures = $plan->mechanicFeatures())
                            <fieldset class="border-top pt-3 mb-3">
                                <legend class="h6">Independent mechanic entitlements</legend>
                                <label class="form-label" for="mechanic-limit-{{ $plan->id }}">Monthly requests per mechanic</label>
                                <input class="form-control" id="mechanic-limit-{{ $plan->id }}" name="mechanic_entitlements[monthly_limit]" type="number" min="0" max="1000" value="{{ $mechanicFeatures['monthly_limit'] }}" aria-describedby="mechanic-limit-help-{{ $plan->id }}">
                                <p id="mechanic-limit-help-{{ $plan->id }}" class="form-text">Leave blank for unlimited. Set 0 to pause new requests on this plan.</p>
                                <input name="mechanic_entitlements[travel_pricing]" value="0" type="hidden">
                                <label class="form-check"><input class="form-check-input" type="checkbox" name="mechanic_entitlements[travel_pricing]" value="1" @checked($mechanicFeatures['travel_pricing'])> Enable inspection, home service and travel fees</label>
                                <input name="mechanic_entitlements[live_tracking]" value="0" type="hidden">
                                <label class="form-check"><input class="form-check-input" type="checkbox" name="mechanic_entitlements[live_tracking]" value="1" @checked($mechanicFeatures['live_tracking'] ?? $plan->slug === 'premium')> Allow optional live roadside location sharing</label>
                                <label class="form-check"><input type="hidden" name="mechanic_entitlements[reminders]" value="0"><input class="form-check-input" type="checkbox" name="mechanic_entitlements[reminders]" value="1" @checked($mechanicFeatures['reminders'])> Send the mechanic appointment reminders</label>
                                <label class="form-label mt-2" for="portfolio-limit-{{ $plan->id }}">Portfolio project limit</label>
                                <input class="form-control" id="portfolio-limit-{{ $plan->id }}" name="mechanic_entitlements[portfolio_limit]" type="number" min="0" max="100" value="{{ $mechanicFeatures['portfolio_limit'] }}" required>
                            </fieldset>
                            @if ($plan->slug !== 'free' && config('services.paymongo.legacy_manual_payments'))
                                <fieldset class="border-top pt-3">
                                    <legend class="h6">Payment destination</legend>
                                    <div class="mb-3">
                                        <label class="form-label" for="account-name-{{ $plan->id }}">Account name</label>
                                        <input class="form-control" id="account-name-{{ $plan->id }}" name="payment_account_name" maxlength="255" value="{{ $editingPlan ? old('payment_account_name', $plan->payment_account_name) : $plan->payment_account_name }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="account-number-{{ $plan->id }}">Account number</label>
                                        <input class="form-control" id="account-number-{{ $plan->id }}" name="payment_account_number" maxlength="100" value="{{ $editingPlan ? old('payment_account_number', $plan->payment_account_number) : $plan->payment_account_number }}">
                                        <div class="form-text">Enter both the account name and number when using account details.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="payment-instructions-{{ $plan->id }}">Payment instructions</label>
                                        <textarea class="form-control" id="payment-instructions-{{ $plan->id }}" name="payment_instructions" rows="3" maxlength="1000">{{ $editingPlan ? old('payment_instructions', $plan->payment_instructions) : $plan->payment_instructions }}</textarea>
                                        <div class="form-text">Include the payment service or bank name and any steps the owner needs to follow.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="payment-qr-{{ $plan->id }}">Payment QR image</label>
                                        <input class="form-control" id="payment-qr-{{ $plan->id }}" name="payment_qr" type="file" accept=".jpg,.jpeg,.png,.webp" data-image-preview-target="qr-preview-{{ $plan->id }}" data-image-max-size="2097152" aria-describedby="qr-help-{{ $plan->id }}">
                                        <div id="qr-help-{{ $plan->id }}" class="form-text">JPG, PNG, or WebP, up to 2 MB and 4096 × 4096 pixels. Upload a replacement to change the QR.</div>
                                        @if ($plan->hasPaymentQr())
                                            <img src="{{ route('shop.pricing.qr', $plan) }}" id="qr-preview-{{ $plan->id }}" class="plan-payment-qr mt-3" alt="{{ $plan->name }} payment QR" width="240" height="240">
                                        @else
                                            <div id="qr-preview-{{ $plan->id }}" class="plan-payment-qr uploaded-image-fallback mt-3" role="img" aria-label="{{ $plan->name }} payment QR preview"><span class="small">No QR image available</span></div>
                                        @endif
                                    </div>
                                    @if ($plan->payment_qr_path)
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" id="remove-qr-{{ $plan->id }}" name="remove_payment_qr" value="1" type="checkbox" @checked($editingPlan && old('remove_payment_qr'))>
                                            <label class="form-check-label" for="remove-qr-{{ $plan->id }}">Remove current QR image</label>
                                        </div>
                                    @endif
                                </fieldset>
                            @endif
                            <button class="btn btn-primary" type="submit">Save {{ $plan->name }} plan</button>
                        </form>
                    </details>
                </div>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="subscribers-title">
        <h2 id="subscribers-title" class="h4">Subscriptions and trial access</h2>
        <p class="text-secondary">Manual corrections are available when there is no paid coverage. Paid plan changes use the next billing period. <a href="{{ route('admin.subscription-billing.index') }}">Review subscription refunds and billing</a>.</p>
        @forelse ($subscribers as $shop)
            <form class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-3" method="POST" action="{{ route('admin.shops.plan', $shop) }}">
                @csrf @method('PATCH')
                <div class="me-auto"><strong>{{ $shop->name }}</strong><div class="small text-secondary">{{ $shop->owner->name }} · Current: {{ ucfirst($shop->activePlanSlug()) }} @if ($shop->trial_started_at)· Trial ends {{ $shop->trialEndsAt()->format('M j, Y') }}@endif @if ($shop->plan_expires_at)· Paid through {{ $shop->plan_expires_at->format('M j, Y') }}@endif</div></div>
                <div><label class="form-label" for="subscriber-plan-{{ $shop->id }}">Set plan</label><select class="form-select" id="subscriber-plan-{{ $shop->id }}" name="plan_slug"><option value="free" @selected($shop->plan_slug === 'free')>Free</option><option value="basic" @selected($shop->plan_slug === 'basic')>Basic</option><option value="premium" @selected($shop->plan_slug === 'premium')>Premium</option></select></div>
                <div><label class="form-label" for="subscriber-expiry-{{ $shop->id }}">Access through (paid plans)</label><input class="form-control" id="subscriber-expiry-{{ $shop->id }}" name="plan_expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" value="{{ $shop->plan_expires_at?->isFuture() ? $shop->plan_expires_at->toDateString() : '' }}"></div>
                @if ($shop->next_plan_starts_at)<p class="small mb-0">Next paid period starts {{ $shop->next_plan_starts_at->format('M j, Y g:i A') }}.</p>@endif
                <button class="btn btn-outline-primary" type="submit" @disabled($shop->paid_coverage_count > 0)>Save access</button>
                @if ($shop->paid_coverage_count > 0)<p class="small mb-0">Paid coverage is protected. Complete a full refund to remove its access.</p>@endif
            </form>
        @empty
            <div class="surface p-4"><p class="mb-0 text-secondary">No subscriptions or trials yet.</p></div>
        @endforelse
        {{ $subscribers->links() }}
    </section>
    <section aria-labelledby="payment-history-title" class="mt-4">
        <h2 id="payment-history-title" class="h4">Payment review history</h2>
        <div class="surface p-3 table-responsive">
            <table class="table plan-payment-history mb-0">
                <thead><tr><th scope="col">Reviewed</th><th scope="col">Shop</th><th scope="col">Plan</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Period / note</th></tr></thead>
                <tbody>
                    @forelse ($reviewedPayments as $payment)
                        <tr><td>{{ $payment->reviewed_at?->format('M j, Y') }}</td><td>{{ $payment->shop->name }}</td><td>{{ ucfirst($payment->plan_slug) }}</td><td>₱{{ number_format($payment->amount) }}</td><td>{{ ucfirst($payment->status) }}</td><td class="text-break">@if ($payment->reference)Reference {{ $payment->reference }} · @endif{{ $payment->period_ends_at ? 'Access through '.$payment->period_ends_at->format('M j, Y') : ($payment->review_notes ?: '—') }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No reviewed plan payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reviewedPayments->links() }}
    </section>
</x-layouts.app>
