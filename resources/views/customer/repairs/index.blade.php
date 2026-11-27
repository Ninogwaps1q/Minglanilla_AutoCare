<x-layouts.app :title="__('ui.' . 'My repairs')">
    <div class="page-heading">
        <h1>{{ __('ui.' . 'My repairs') }}</h1>
        <p>{{ __('repair_display.tracking_intro') }}</p>
    </div>
    @if (request()->integer('appointment_id') > 0 || request()->integer('repair_job_id') > 0)<p><a href="{{ route('customer.repairs.index') }}">{{ __('ui.' . 'View all repairs') }}</a></p>@endif
    <x-form-errors />
    @forelse ($repairJobs as $repairJob)
        @php
            $invoice = $repairJob->invoice;
            $estimate = $repairJob->quotation;
            $revision = $repairJob->pendingSupplementalQuotation;
            $needsApproval = $estimate?->documentStatus() === 'sent' && ! $revision && ! in_array($repairJob->status, ['cancelled', 'completed', 'released'], true);
            $approvedEstimate = in_array($estimate?->status, ['accepted', 'approved'], true) ? $estimate : null;
            $estimateAvailable = $estimate && $estimate->status !== 'draft';
            $latestInspection = $repairJob->inspections->sortBy([['inspection_date', 'desc'], ['id', 'desc']])->first();
            $attentionItems = $latestInspection?->items->whereIn('condition', ['Needs Attention', 'Critical'])->count() ?? 0;
            $pendingCheckout = $invoice?->payments->contains('status', 'checkout_pending');
            $paymentUnderReview = $invoice?->payments->contains('status', 'pending');
            $canPayOnline = filled(config('services.paymongo.secret_key')) && filled(config('services.paymongo.webhook_secret'));
            $balance = $invoice && $invoice->status !== 'paid' ? $invoice->remainingBalance() : 0;
            $reserved = (float) $invoice?->payments->whereIn('status', ['pending', 'checkout_pending'])->sum('amount');
            $availableBalance = max(0, round($balance - $reserved, 2));
            $publicPickup = ! $repairJob->shop->trashed() && $repairJob->shop->verification_status === 'approved'
                && $repairJob->shop->shop_status === 'active' && $repairJob->shop->owner?->status === 'active'
                && ! $repairJob->shop->isIndependentMechanic() && $repairJob->shop->publicMapPosition();
            $canReview = $repairJob->appointment?->status === 'completed' && ! $repairJob->review && ! $repairJob->appointment->review;
            $stage = match (true) {
                $repairJob->status === 'cancelled' => 'cancelled',
                $repairJob->status === 'released' => 'released',
                $revision !== null => 'revision',
                $repairJob->status === 'completed' && $invoice?->status === 'paid' => 'pickup',
                $repairJob->status === 'completed' && $pendingCheckout => 'checkout_pending',
                $repairJob->status === 'completed' && $paymentUnderReview && $availableBalance <= 0 => 'payment_review',
                $repairJob->status === 'completed' && $invoice !== null => 'payment',
                $repairJob->status === 'completed' => 'invoice_wait',
                $needsApproval => 'approve',
                $estimate?->documentStatus() === 'expired' => 'expired',
                $repairJob->status === 'waiting_parts' => 'parts',
                in_array($repairJob->status, ['repairing', 'in_progress'], true) => 'repair',
                $approvedEstimate !== null => 'approved',
                $repairJob->inspections->isNotEmpty() || $repairJob->status === 'waiting_approval' => 'estimate_wait',
                default => 'inspection',
            };
        @endphp
        <article id="repair-{{ $repairJob->id }}" class="customer-repair-card mb-4" aria-labelledby="repair-title-{{ $repairJob->id }}">
            <header class="surface repair-task-header p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div class="text-break">
                        <p class="small text-secondary mb-1">{{ __('repair_display.job_number', ['number' => $repairJob->job_number]) }}</p>
                        <h2 id="repair-title-{{ $repairJob->id }}" class="mb-1">{{ $repairJob->vehicle->make }} {{ $repairJob->vehicle->model }} · {{ $repairJob->vehicle->plate_number }}</h2>
                        <p class="text-secondary mb-0">{{ $repairJob->shop->name }}</p>
                    </div>
                    <span class="badge text-bg-{{ in_array($stage, ['pickup', 'released'], true) ? 'success' : (in_array($stage, ['approve', 'revision', 'payment', 'checkout_pending', 'payment_review', 'parts', 'expired'], true) ? 'warning' : 'info') }}">{{ __('repair_display.stage.'.$stage) }}</span>
                </div>

                <section class="repair-task-next p-3" aria-labelledby="repair-next-{{ $repairJob->id }}">
                    <h3 class="task-section-title" id="repair-next-{{ $repairJob->id }}">{{ __('repair_display.next.'.$stage) }}</h3>
                    @if ($needsApproval && $attentionItems > 0)
                        <p class="mb-1">{{ trans_choice('repair_display.attention_count', $attentionItems) }}</p>
                    @endif
                    @if ($needsApproval && $estimate->valid_until)
                        <p class="mb-2 text-secondary">{{ __('repair_display.valid_until', ['date' => $estimate->valid_until->format('M j, Y')]) }}</p>
                    @elseif ($stage === 'payment' && $invoice->due_date)
                        <p class="mb-2 text-secondary">{{ __('repair_display.due_date', ['date' => $invoice->due_date->format('M j, Y')]) }}</p>
                    @endif
                        @if ($needsApproval)
                            <form method="POST" action="{{ route('customer.quotations.update', $estimate) }}" id="repair-approval-{{ $repairJob->id }}" class="repair-approval-actions">
                                @csrf @method('PATCH')
                                <button class="btn btn-accent" name="status" value="accepted" aria-label="{{ __('repair_display.approve_estimate', ['amount' => number_format($estimate->total, 2)]) }}">{{ __('repair_display.approve_amount', ['amount' => number_format($estimate->total, 2)]) }}</button>
                                <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $repairJob->shop) }}">{{ __('repair_display.ask_question') }}</a>
                            </form>
                        @elseif ($stage === 'revision')
                            <a class="btn btn-accent" href="{{ route('customer.quotations.index', ['focus' => $revision->id]) }}">{{ __('ui.Review revised estimate') }}</a>
                        @elseif (in_array($stage, ['payment', 'checkout_pending', 'payment_review'], true))
                            <a class="btn btn-{{ $stage === 'payment' && $canPayOnline && $availableBalance >= 1 ? 'accent' : 'primary' }}" href="{{ route('customer.invoices.index', ['focus' => $invoice->id]) }}">{{ $stage === 'payment' && $canPayOnline && $availableBalance >= 1 ? __('repair_display.pay_amount', ['amount' => number_format($availableBalance, 2)]) : __('ui.Review bill and payment options') }}</a>
                        @elseif ($stage === 'pickup')
                            @if ($publicPickup)
                                <a class="btn btn-accent" href="{{ route('shops.show', $repairJob->shop) }}#shop-directions">{{ __('repair_display.directions') }}</a>
                            @else
                                <a class="btn btn-primary" href="{{ route('customer.messages.show', $repairJob->shop) }}">{{ __('repair_display.coordinate_pickup') }}</a>
                            @endif
                        @elseif ($stage === 'released' && $canReview)
                            <a class="btn btn-primary" href="{{ route('customer.reviews.index', ['job' => $repairJob->id]) }}">{{ __('repair_display.leave_review') }}</a>
                        @elseif ($stage === 'expired')
                            <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $repairJob->shop) }}">{{ __('repair_display.ask_question') }}</a>
                        @endif
                </section>
            </header>
            @if ($repairJob->appointment)
                <p class="small text-secondary mt-3 mb-3"><strong>Booked services:</strong> {{ $repairJob->appointment->bookedServices->isNotEmpty() ? $repairJob->appointment->bookedServices->pluck('service.name')->filter()->implode(', ') : ($repairJob->appointment->service?->name ?? 'Service') }} · {{ $repairJob->appointment->appointment_date->format('M j, Y') }} at {{ $repairJob->appointment->appointment_time->format('g:i A') }}</p>
                @if ($repairJob->appointment->service_price !== null)
                    <p class="small mb-2"><strong>Booked price: PHP {{ number_format($repairJob->appointment->service_price, 2) }}</strong> · Labor: {{ $repairJob->appointment->service_labor_included === null ? 'ask shop' : ($repairJob->appointment->service_labor_included ? 'included' : 'quoted separately') }} · Parts: {{ $repairJob->appointment->service_parts_included === null ? 'ask shop' : ($repairJob->appointment->service_parts_included ? 'included' : 'quoted separately') }}</p>
                    @if ($repairJob->appointment->service_scope)<p class="small text-secondary mb-3">{{ $repairJob->appointment->service_scope }}</p>@endif
                @endif
            @endif
            @if ($estimateAvailable)
                <section class="repair-task-section" aria-label="{{ __('repair_display.estimate') }}">
                    <h3 class="task-section-title">{{ __('repair_display.estimate') }}</h3>
                    <div class="surface p-3 p-md-4">
                        <x-estimate-breakdown :quotation="$estimate" :show-expiry="! $needsApproval" />
                        @if ($needsApproval)
                            <button type="submit" form="repair-approval-{{ $repairJob->id }}" name="status" value="rejected" class="btn btn-link text-danger p-0">{{ __('ui.Decline estimate') }}</button>
                        @endif
                    </div>
                </section>
            @endif

            @if ($repairJob->inspections->isNotEmpty() || $repairJob->photos->isNotEmpty() || filled($repairJob->diagnosis))
                <section class="repair-task-section" aria-label="{{ __('repair_display.inspection_results') }}">
                    <h3 class="task-section-title">{{ __('repair_display.inspection_results') }}</h3>
                    <div class="surface p-3 p-md-4">
                        @if ($repairJob->diagnosis)<p class="mb-3">{{ $repairJob->diagnosis }}</p>@endif
                        @foreach ($repairJob->inspections as $inspection)
                            <x-inspection-checklist :inspection="$inspection" />
                        @endforeach
                        @if ($repairJob->photos->isNotEmpty())
                            <h4 class="task-section-title mt-3">{{ __('repair_display.evidence') }}</h4>
                            <div class="repair-evidence-grid">
                                @foreach ($repairJob->photos as $photo)
                                    <figure class="mb-0">
                                        <a href="{{ route('repair-photos.show', $photo) }}" target="_blank" rel="noopener">
                                            <img class="repair-evidence-image" src="{{ route('repair-photos.show', $photo) }}" alt="{{ $photo->caption ?: __('repair_display.photo_alt', ['stage' => ucfirst($photo->stage)]) }}" loading="lazy" decoding="async">
                                        </a>
                                        <figcaption class="small mt-1">{{ $photo->caption ?: __('repair_display.photo_alt', ['stage' => ucfirst($photo->stage)]) }}</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <x-service-journey :repair-job="$repairJob" detailed />
            @if ($repairJob->items->isNotEmpty())
                <details class="repair-details my-3" @unless ($estimateAvailable) open @endunless>
                <summary>{{ __('repair_display.breakdown') }}</summary>
                <div class="table-responsive mb-2" tabindex="0" role="region" aria-label="{{ __('repair_display.breakdown') }}">
                    <table class="table table-sm align-middle">
                        <caption class="caption-top">{{ __('repair_display.breakdown') }}</caption>
                        <thead><tr><th>Charge</th><th>Category</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($repairJob->items as $item)
                                <tr><td>{{ $item->description }}</td><td>{{ $item->service_id !== null ? 'Booked service' : ucfirst($item->type) }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">PHP {{ number_format($item->unit_price, 2) }}</td><td class="text-end">PHP {{ number_format($item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        @unless ($approvedEstimate)
                            <tfoot>
                            <tr><th colspan="4">{{ __('repair_display.work_subtotal') }}</th><td class="text-end">PHP {{ number_format($repairJob->items->sum('subtotal'), 2) }}</td></tr>
                            </tfoot>
                        @endunless
                    </table>
                </div>
                <p class="small text-secondary mb-3">The booked service has its own price. Additional parts or labor appear as separate charges and need your approval in the estimate.</p>
                </details>
            @endif
            @if ($repairJob->technicianAssignments->isNotEmpty())
                <p class="mb-3"><strong>Technician{{ $repairJob->technicianAssignments->count() > 1 ? 's' : '' }}:</strong> {{ $repairJob->technicianAssignments->pluck('technician.name')->unique()->implode(', ') }}</p>
            @endif
            @if (in_array($repairJob->status, ['completed', 'released'], true))
                <x-repair-handover :repair-job="$repairJob" context="customer" />
            @endif
            <x-repair-timeline :repair-job="$repairJob" />
            <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('customer.repairs.shares.index', $repairJob) }}">{{ __('extensions.share.manage') }}</a>
            @if(! in_array($repairJob->status, ['completed', 'released', 'cancelled'], true))
                <details class="repair-details mb-3">
                    <summary>Report a concern about this repair</summary>
                    <p class="small text-secondary mt-2">An admin can review a concern even while the repair is still in progress.</p>
                    <form method="POST" action="{{ route('customer.service-cases.store', $repairJob) }}" class="row g-3 mt-1">@csrf
                        <input type="hidden" name="type" value="complaint">
                        <div class="col-md-5"><label class="form-label" for="concern-subject-{{ $repairJob->id }}">Short subject</label><input id="concern-subject-{{ $repairJob->id }}" class="form-control" name="subject" maxlength="150" required placeholder="Example: Work differs from the approved estimate"></div>
                        <div class="col-md-7"><label class="form-label" for="concern-description-{{ $repairJob->id }}">What happened?</label><textarea id="concern-description-{{ $repairJob->id }}" class="form-control" name="description" rows="2" minlength="10" maxlength="3000" required placeholder="Explain what you expected and what happened."></textarea></div>
                        <div class="col-12"><button class="btn btn-outline-primary">Send concern to admin</button></div>
                    </form>
                </details>
            @endif
            @if(in_array($repairJob->status, ['completed', 'released'], true))
                <section class="border rounded p-3 mb-3" aria-label="Warranty and aftercare">
                    <h3 class="h6">Warranty & aftercare</h3>
                    @if($repairJob->warranty)
                        <p class="mb-1"><strong>{{ $repairJob->warranty->valid_until->isBefore(today()) ? 'Warranty ended' : 'Warranty active until '.$repairJob->warranty->valid_until->format('M j, Y') }}</strong></p>
                        <p class="text-secondary mb-2">{{ $repairJob->warranty->coverage }}</p>
                    @else
                        <p class="text-secondary mb-2">No warranty has been issued for this repair.</p>
                    @endif
                    @if($repairJob->serviceCases->isNotEmpty())<p class="small"><a href="{{ route('customer.service-cases.index') }}">Track {{ $repairJob->serviceCases->count() }} aftercare {{ str('request')->plural($repairJob->serviceCases->count()) }}</a></p>@endif
                    <details><summary>Request a follow-up or report a concern</summary>
                        <form method="POST" action="{{ route('customer.service-cases.store', $repairJob) }}" class="row g-3 mt-1">@csrf
                            <div class="col-md-4"><label class="form-label" for="case-type-{{ $repairJob->id }}">Request type</label><select id="case-type-{{ $repairJob->id }}" class="form-select" name="type" required><option value="follow_up">Follow-up repair</option>@if($repairJob->warranty && ! $repairJob->warranty->valid_until->isBefore(today()))<option value="warranty_claim">Warranty claim</option>@endif<option value="complaint">Complaint for admin review</option></select></div>
                            <div class="col-md-8"><label class="form-label" for="case-subject-{{ $repairJob->id }}">Short subject</label><input id="case-subject-{{ $repairJob->id }}" class="form-control" name="subject" maxlength="150" required placeholder="Example: Noise returned after the repair"></div>
                            <div class="col-12"><label class="form-label" for="case-description-{{ $repairJob->id }}">What happened?</label><textarea id="case-description-{{ $repairJob->id }}" class="form-control" name="description" rows="3" minlength="10" maxlength="3000" required placeholder="Describe the issue and when it started."></textarea></div>
                            <div class="col-12"><button class="btn btn-outline-primary">Send request</button></div>
                        </form>
                    </details>
                </section>
            @endif
            <p class="mb-3 text-break">{{ $repairJob->description }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-4">Estimated cost</dt>
                <dd class="col-sm-8">{{ $repairJob->estimated_cost !== null ? '₱'.number_format($repairJob->estimated_cost, 2) : 'Awaiting estimate' }}</dd>
                <dt class="col-sm-4">Actual cost</dt>
                <dd class="col-sm-8">{{ in_array($repairJob->status, ['completed', 'released'], true) && $repairJob->actual_cost !== null ? '₱'.number_format($repairJob->actual_cost, 2) : 'Not finalized' }}</dd>
                <dt class="col-sm-4">Started</dt>
                <dd class="col-sm-8">{{ $repairJob->started_at?->setTimezone(\App\Models\Appointment::TIMEZONE)->format('M j, Y g:i A') ?? 'Not started' }}</dd>
                <dt class="col-sm-4">Completed</dt>
                <dd class="col-sm-8">{{ $repairJob->completed_at?->setTimezone(\App\Models\Appointment::TIMEZONE)->format('M j, Y g:i A') ?? 'Not completed' }}</dd>
            </dl>
        </article>
    @empty
        <div class="surface p-4">
            <h2 class="h5">No repair jobs yet</h2>
            <p>Your shop's repair jobs will appear here once work is arranged.</p>
            <a class="btn btn-outline-primary" href="{{ route('customer.appointments.index') }}">{{ __('ui.' . 'View appointments') }}</a>
        </div>
    @endforelse
    {{ $repairJobs->links() }}
</x-layouts.app>
