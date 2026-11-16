<x-layouts.public title="Shop owner pricing">
    <div class="container py-5">
        <div class="row align-items-end g-3 mb-4">
            <div class="col-lg-8">
                <h1>Choose how your shop gets discovered</h1>
                <p class="text-secondary mb-0">Every approved shop can appear on the map with its name and services. Paid plans add priority to the default directory order. Example monthly prices are set by the administrator.</p>
            </div>
            @if ($shop)
                <div class="col-lg-4 text-lg-end">
                    <span class="badge text-bg-primary fs-6">Current plan: {{ ucfirst($shop->activePlanSlug()) }}</span>
                    @if ($shop->activePlanEndsAt())
                        <p class="small mb-0 mt-2">{{ $shop->trialIsActive() ? 'Trial' : 'Plan' }} ends {{ $shop->activePlanEndsAt()->format('M j, Y') }}. {{ $shop->daysUntilAccessEnds() }} {{ $shop->daysUntilAccessEnds() === 1 ? 'day' : 'days' }} left.</p>
                    @elseif ($shop->trial_started_at && $shop->activePlanSlug() === 'free')
                        <p class="small mb-0 mt-2">Your Premium trial {{ $shop->trial_revoked_at ? 'was closed' : 'ended '.$shop->trialEndsAt()->format('M j, Y') }}. Your public listing stays visible.</p>
                    @endif
                </div>
            @endif
        </div>

        <x-form-errors />

        <div class="row g-3 align-items-stretch">
            @foreach ($plans as $plan)
                <div class="col-lg-4">
                    <article class="surface p-4 h-100 d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-2">
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
                            @if ($shop && $shop->activePlanSlug() === $plan->slug)
                                <span class="fw-semibold d-block mb-2">Your current plan</span>
                                @if ($plan->slug !== 'free' && !$pendingPayment)
                                    @if ($shop->requested_plan === $plan->slug)
                                        <span class="small text-secondary">Renewal requested. Submit payment details below.</span>
                                    @else
                                        <form method="POST" action="{{ route('shop.pricing.request', $plan) }}">@csrf<button class="btn btn-outline-primary w-100" type="submit">{{ $shop->trialIsActive() ? 'Request paid '.$plan->name : 'Renew '.$plan->name }}</button></form>
                                    @endif
                                @endif
                            @elseif ($plan->slug === 'free')
                                @if (!$shop && auth()->user()?->role === 'shop_owner')<a class="btn btn-outline-primary w-100" href="{{ route('shop.register') }}">Register your shop</a>@elseif (!$shop && auth()->check())<span class="small text-secondary">Sign in with a shop owner account to register a shop.</span>@elseif (!$shop)<a class="btn btn-outline-primary w-100" href="{{ route('register') }}">Create a shop owner account</a>@else<span class="text-secondary">Included for every approved shop</span>@endif
                            @elseif ($shop && ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active'))
                                <span class="text-secondary">Available after your shop is approved and active.</span>
                            @elseif ($shop)
                                @if ($plan->slug === 'premium' && !$shop->trial_started_at && $shop->activePlanSlug() !== 'premium')
                                    <form method="POST" action="{{ route('shop.pricing.trial') }}" class="mb-2">@csrf<button class="btn btn-primary w-100" type="submit">Start 30-day trial</button></form>
                                @endif
                                @if ($pendingPayment)
                                    <span class="small text-secondary">Payment under review.</span>
                                @elseif ($shop->requested_plan === $plan->slug)
                                    <span class="fw-semibold">Request sent. Waiting for admin review.</span>
                                @else
                                    <form method="POST" action="{{ route('shop.pricing.request', $plan) }}">@csrf<button class="btn btn-outline-primary w-100" type="submit">Request {{ $plan->name }}</button></form>
                                @endif
                            @else
                                @if (auth()->user()?->role === 'shop_owner')<a class="btn btn-outline-primary w-100" href="{{ route('shop.register') }}">Register your shop</a>@elseif (auth()->check())<span class="small text-secondary">Sign in with a shop owner account to request this plan.</span>@else<a class="btn btn-outline-primary w-100" href="{{ route('register') }}">Create a shop owner account</a>@endif
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        @if ($shop && $requestedPlan)
            <section class="surface p-4 mt-4" aria-labelledby="plan-payment-title">
                <h2 id="plan-payment-title" class="h4">Pay for {{ $requestedPlan->name }}</h2>
                <p class="text-secondary">Monthly price: ₱{{ number_format($requestedPlan->monthly_price) }}. The amount is recorded when you submit payment details.</p>
                @if ($pendingPayment)
                    <p class="mb-0">Your payment details are waiting for admin review. You can check their status below.</p>
                @elseif ($requestedPlan->payment_instructions)
                    <div class="payment-instructions rounded p-3 mb-3"><strong>Payment instructions</strong><p class="mb-0 payment-instructions-preline">{{ $requestedPlan->payment_instructions }}</p></div>
                    <form method="POST" action="{{ route('shop.pricing.payments.store') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-md-6"><label class="form-label" for="payment-reference">Payment reference</label><input class="form-control" id="payment-reference" name="reference" maxlength="100" value="{{ old('reference') }}"><div class="form-text">Add the reference from your payment app or receipt.</div></div>
                        <div class="col-md-6"><label class="form-label" for="payment-proof">Receipt or payment proof</label><input class="form-control" id="payment-proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="form-text">JPG, PNG, WebP, or PDF up to 5 MB. Provide a reference or a file.</div></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit">Submit payment for review</button></div>
                    </form>
                @else
                    <p class="mb-0 text-secondary">The administrator has not added payment instructions yet. Check this page later before sending payment details.</p>
                @endif
            </section>
        @endif

        @if ($shop && $payments->isNotEmpty())
            <section class="surface p-4 mt-4" aria-labelledby="plan-payment-history-title">
                <h2 id="plan-payment-history-title" class="h4">Payment history</h2>
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th scope="col">Submitted</th><th scope="col">Plan</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead><tbody>
                    @foreach ($payments as $payment)
                        <tr><td>{{ $payment->created_at->format('M j, Y') }}</td><td>{{ ucfirst($payment->plan_slug) }}</td><td>₱{{ number_format($payment->amount) }}</td><td>{{ ucfirst($payment->status) }}</td><td>@if ($payment->review_notes){{ $payment->review_notes }}@elseif ($payment->proof_path)<a href="{{ route('shop.pricing.payments.proof', $payment) }}">View receipt</a>@else{{ $payment->reference ?: '—' }}@endif</td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        @endif
        <p class="text-secondary small mt-4">Trial access ends automatically after 30 days. An administrator reviews payment details before paid access begins. No automatic payment is taken.</p>
    </div>
</x-layouts.public>
