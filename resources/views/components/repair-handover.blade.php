@props(['repairJob', 'context'])

@php($isCustomer = $context === 'customer')

<section class="repair-handover mb-3" aria-label="Completed repair handover">
    <div class="service-journey-heading"><h3>Repair handover</h3><span>{{ $repairJob->job_number }}</span></div>
    <dl>
        <div><dt>Before photos</dt><dd>{{ $repairJob->photos->where('stage', 'before')->count() }} recorded</dd></div>
        <div><dt>After photos</dt><dd>{{ $repairJob->photos->where('stage', 'after')->count() }} recorded</dd></div>
        <div><dt>Warranty</dt><dd>{{ $repairJob->warranty ? ($repairJob->warranty->valid_until->isBefore(today()) ? 'Ended' : 'Until '.$repairJob->warranty->valid_until->format('M j, Y')) : 'Not issued' }}</dd></div>
        <div><dt>Invoice</dt><dd>{{ $repairJob->invoice ? ucfirst($repairJob->invoice->status) : 'Not issued' }}</dd></div>
    </dl>
    <div class="d-flex flex-wrap gap-3 small">
        @if ($repairJob->invoice)<a href="{{ route($isCustomer ? 'customer.invoices.show' : 'shop.invoices.show', $repairJob->invoice) }}">View itemized invoice</a>@endif
        @if ($isCustomer)
            <a href="{{ route('customer.service-history.index') }}">Service history</a>
            <a href="{{ route('customer.reminders.index') }}">Plan next maintenance</a>
        @endif
    </div>
</section>
