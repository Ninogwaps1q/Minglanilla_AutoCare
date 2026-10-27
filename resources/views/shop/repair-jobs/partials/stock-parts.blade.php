@php
    $estimateLocked = in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true);
    $activeRepair = in_array($repairJob->status, ['approved', 'repairing', 'in_progress', 'waiting_parts'], true);
@endphp

@if (! $estimateLocked && in_array($repairJob->status, ['pending', 'diagnosis', 'waiting_approval'], true) && ! $repairJob->invoice && $stockParts->isNotEmpty())
    <div class='rounded border p-3 mb-3'>
        <h3 class='h6 mb-1'>Add a recorded inventory part</h3>
        <p class='small text-secondary mb-3'>Use its current selling price in the estimate. After customer approval, available stock is reserved for this job. Stock is deducted when installation is confirmed.</p>
        <form method='POST' action='{{ route('shop.repair-job-items.store', $repairJob) }}' class='row g-2 align-items-end'>
            @csrf
            <input type='hidden' name='type' value='part'>
            <div class='col-md-7'>
                <label class='form-label' for='estimate-part-{{ $repairJob->id }}'>Inventory part</label>
                <select id='estimate-part-{{ $repairJob->id }}' name='spare_part_id' class='form-select' required>
                    <option value=''>Choose a part</option>
                    @foreach ($stockParts as $part)
                        <option value='{{ $part->id }}'>{{ $part->name }} — {{ max(0, $part->quantity - (int) $part->reserved_units) }} available · ₱{{ number_format($part->selling_price, 2) }} each</option>
                    @endforeach
                </select>
            </div>
            <div class='col-md-2'><label class='form-label' for='estimate-part-quantity-{{ $repairJob->id }}'>Quantity</label><input id='estimate-part-quantity-{{ $repairJob->id }}' class='form-control' type='number' min='1' max='10000' name='quantity' value='1' required></div>
            <div class='col-md-3 d-grid'><button class='btn btn-outline-primary' type='submit'>Add part to estimate</button></div>
        </form>
    </div>
@endif

@if ($activeRepair && $repairJob->items->where('type', 'part')->isNotEmpty())
    <section class='rounded border p-3 mb-3' aria-label='Repair stock usage'>
        <h3 class='h6 mb-1'>Use parts from stock</h3>
        <p class='small text-secondary mb-3'>Reserved parts are held for this repair. Confirm use only after installation; the approved price stays unchanged.</p>
        @foreach ($repairJob->items->where('type', 'part') as $item)
            @php
                $used = $item->inventoryTransactions->where('type', 'repair_consumption')->isNotEmpty();
                $reservation = $item->reservation;
                $availableParts = $stockParts->filter(fn ($part) => ($item->spare_part_id === null || $part->id === $item->spare_part_id)
                    && ($part->quantity - (int) $part->reserved_units > 0 || ($reservation?->status === 'reserved' && $reservation->spare_part_id === $part->id)));
            @endphp
            <div class='d-flex flex-wrap align-items-end justify-content-between gap-2 py-2 border-top'>
                <div><strong>{{ $item->description }}</strong><div class='small text-secondary'>{{ $item->quantity }} {{ $item->quantity == 1 ? 'unit' : 'units' }} · ₱{{ number_format($item->subtotal, 2) }} already in the estimate</div>@if ($reservation?->status === 'reserved')<span class='badge text-bg-info mt-1'>Reserved for this repair</span>@endif</div>
                @if ($used)
                    <span class='badge text-bg-success'>Stock used</span>
                @elseif ($availableParts->isEmpty())
                    <a href='{{ route('shop.inventory.index') }}' class='btn btn-sm btn-outline-warning'>Restock this part</a>
                @elseif ($item->spare_part_id && $reservation?->status !== 'reserved' && in_array($repairJob->quotation?->status, ['accepted', 'approved'], true))
                    <form method='POST' action='{{ route('shop.repair-job-items.reserve', $item) }}'>@csrf<button class='btn btn-sm btn-outline-primary' type='submit'>Reserve {{ $item->quantity }} for this repair</button></form>
                @else
                    <form method='POST' action='{{ route('shop.repair-job-items.consume', $item) }}' class='d-flex flex-wrap align-items-end gap-2'>
                        @csrf
                        <input type='hidden' name='repair_job_id' value='{{ $repairJob->id }}'>
                        <input type='hidden' name='repair_job_item_id' value='{{ $item->id }}'>
                        <input type='hidden' name='quantity' value='{{ $item->quantity }}'>
                        <div>
                            <label class='form-label small' for='use-stock-{{ $item->id }}'>Inventory part</label>
                            <select id='use-stock-{{ $item->id }}' class='form-select form-select-sm' name='spare_part_id' required>
                                <option value=''>Choose part</option>
                                @foreach ($availableParts as $part)
                                    <option value='{{ $part->id }}' @selected($item->spare_part_id == $part->id)>{{ $part->name }} · {{ $reservation?->status === 'reserved' ? $part->quantity : max(0, $part->quantity - (int) $part->reserved_units) }} available</option>
                                @endforeach
                            </select>
                        </div>
                        <button class='btn btn-sm btn-outline-primary' type='submit'>Use {{ $item->quantity }} from stock</button>
                    </form>
                @endif
            </div>
        @endforeach
    </section>
@endif

@if ($repairJob->status === 'cancelled' && $repairJob->items->where('type', 'part')->isNotEmpty())
    <section class='rounded border p-3 mb-3' aria-label='Return unused repair parts'>
        <h3 class='h6 mb-1'>Return unused parts</h3>
        <p class='small text-secondary mb-3'>Reservations were released when this repair was cancelled. Record a return only for units taken from stock but never installed.</p>
        @foreach ($repairJob->items->where('type', 'part') as $item)
            @php($returnable = -(int) $item->inventoryTransactions->where('type', 'repair_consumption')->sum('quantity') - (int) $item->inventoryTransactions->where('type', 'repair_return')->sum('quantity'))
            @if ($returnable > 0)
                <form method='POST' action='{{ route('shop.repair-job-items.return', $item) }}' class='row g-2 align-items-end border-top py-2'>
                    @csrf
                    <div class='col-md-4'><strong>{{ $item->description }}</strong><div class='small text-secondary'>{{ $returnable }} units may be returned</div></div>
                    <div class='col-md-2'><label class='form-label' for='return-quantity-{{ $item->id }}'>Unused units</label><input id='return-quantity-{{ $item->id }}' class='form-control' type='number' name='quantity' min='1' max='{{ $returnable }}' required></div>
                    <div class='col-md-4'><label class='form-label' for='return-notes-{{ $item->id }}'>Reason for return</label><input id='return-notes-{{ $item->id }}' class='form-control' name='notes' minlength='5' maxlength='1000' required></div>
                    <div class='col-md-2 d-grid'><button class='btn btn-outline-primary'>Return to stock</button></div>
                </form>
            @endif
        @endforeach
    </section>
@endif
