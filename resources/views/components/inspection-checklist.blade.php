@props(['inspection'])

<div class="repair-inspection">
    <p class="small text-secondary mb-2"><time datetime="{{ $inspection->inspection_date->toDateString() }}">{{ $inspection->inspection_date->format('M j, Y') }}</time></p>
    @if ($inspection->notes)<p class="text-break">{{ $inspection->notes }}</p>@endif
    <ul class="divided-rows list-unstyled mb-0">
        @foreach ($inspection->items as $item)
            @php
                $tone = match ($item->condition) { 'Good' => 'success', 'Needs Attention' => 'warning', 'Critical' => 'danger', default => 'secondary' };
                $condition = match ($item->condition) {
                    'Good' => __('repair_display.condition_ok'),
                    'Needs Attention' => __('repair_display.condition_attention'),
                    'Critical' => __('repair_display.condition_critical'),
                    default => $item->condition,
                };
            @endphp
            <li class="py-2">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                    <span class="text-break">{{ $item->item_name }} <small class="text-secondary">{{ $item->category }}</small></span>
                    <span class="badge text-bg-{{ $tone }}">{{ $condition }}</span>
                </div>
                @if ($item->recommendation)
                    <p class="repair-inspection-note text-break mb-0 mt-2">{{ $item->recommendation }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>
