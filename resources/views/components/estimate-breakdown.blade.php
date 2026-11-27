@props(['quotation', 'showExpiry' => true])

@php($approved = in_array($quotation->status, ['accepted', 'approved'], true))

@if ($showExpiry && $quotation->valid_until)
    <p class="small text-secondary">{{ __('repair_display.valid_until', ['date' => $quotation->valid_until->format('M j, Y')]) }}</p>
@endif
<ul class="divided-rows list-unstyled mb-2">
    @foreach ($quotation->items as $item)
        <li class="repair-price-row py-2">
            <div class="text-break">
                <span class="d-block">{{ $item->description }}</span>
                <span class="small text-secondary">
                    {{ __('repair_display.line_quantity', ['quantity' => $item->quantity, 'amount' => number_format($item->unit_price, 2)]) }}
                </span>
            </div>
            <strong class="price">₱{{ number_format($item->subtotal, 2) }}</strong>
        </li>
    @endforeach
</ul>
<dl class="repair-approved-prices estimate-breakdown-totals mb-0" aria-label="{{ __($approved ? 'repair_display.approved_total' : 'repair_display.estimate_total') }}">
    @if ($quotation->discount > 0)
        <div><dt>{{ __('repair_display.work_subtotal') }}</dt><dd class="price">₱{{ number_format($quotation->workSubtotal(), 2) }}</dd></div>
        <div><dt>{{ __('repair_display.discount') }}</dt><dd class="price">−₱{{ number_format($quotation->discount, 2) }}</dd></div>
    @endif
    @if ($quotation->tax > 0)
        <div><dt>{{ __('repair_display.tax') }}</dt><dd class="price">₱{{ number_format($quotation->tax, 2) }}</dd></div>
    @endif
    <div class="repair-estimate-total"><dt>{{ __($approved ? 'repair_display.approved_total' : 'repair_display.estimate_total') }}</dt><dd class="price">₱{{ number_format($quotation->total, 2) }}</dd></div>
</dl>
