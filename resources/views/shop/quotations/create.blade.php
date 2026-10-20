<x-layouts.app title="Prepare estimate">
    <div class="page-heading"><h1>Prepare estimate</h1><p>{{ isset($quotation) ? $quotation->customer->name.' · '.$quotation->vehicle->plate_number : $repairJob->job_number }}</p></div>
    @if (isset($repairJob))<p class="text-secondary">Send this price to the customer for approval. Repair work can start only after they approve it.</p>@endif
    @if (isset($quotation))<div class="surface p-3 mb-3"><strong>Customer request</strong><p class="mb-0 text-break">{{ $quotation->notes }}</p></div>@endif
    <form method="POST" action="{{ isset($quotation) ? route('shop.quotations.update', $quotation) : route('shop.quotations.store', $repairJob) }}" class="surface p-4">
        @csrf
        @if (isset($quotation)) @method('PUT') @endif
        @include('components.form-errors')
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label" for="valid_until">Valid until</label><input id="valid_until" class="form-control" type="date" min="{{ today()->toDateString() }}" name="valid_until" value="{{ old('valid_until') }}"></div>
            <div class="col-12"><label class="form-label" for="notes">Shop notes</label><textarea id="notes" class="form-control" name="notes" rows="2" maxlength="5000">{{ old('notes') }}</textarea></div>
        </div>
        <h2 class="h4">Estimate items</h2>
        @if (isset($repairJob) && $repairJob->items->isNotEmpty())
            <p class="text-secondary">These items come from the repair job. To change the price, return to the job and edit its work items before sending.</p>
            <div class="table-responsive mb-3"><table class="table align-middle"><thead><tr><th scope="col">Work item</th><th scope="col" class="text-end">Quantity</th><th scope="col" class="text-end">Unit price</th><th scope="col" class="text-end">Subtotal</th></tr></thead><tbody>@foreach ($repairJob->items as $item)<tr><td>{{ $item->description }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">PHP {{ number_format($item->unit_price, 2) }}</td><td class="text-end">PHP {{ number_format($item->subtotal, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="3">Price for customer approval</th><th class="text-end">PHP {{ number_format($repairJob->items->sum('subtotal'), 2) }}</th></tr></tfoot></table></div>
        @else
            <div id="quote-items">@foreach (old('items', [['description' => '', 'quantity' => 1, 'unit_price' => '']]) as $index => $item) @include('shop.quotations.item') @endforeach</div>
            <button type="button" id="add-quote-item" class="btn btn-outline-secondary mb-3">Add item</button>
        @endif
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">Send estimate to customer</button>@if (isset($repairJob))<a class="btn btn-outline-secondary" href="{{ route('shop.repair-jobs.index') }}#repair-{{ $repairJob->id }}">Back to repair job</a>@endif</div>
    </form>
    @if (! isset($repairJob) || $repairJob->items->isEmpty())
        <template id="quote-item-template">@include('shop.quotations.item', ['index' => '__INDEX__', 'item' => []])</template>
        <script>
            document.getElementById('add-quote-item').addEventListener('click', function () {
                const container = document.getElementById('quote-items');
                const index = container.children.length;
                if (index >= 50) { return; }
                container.insertAdjacentHTML('beforeend', document.getElementById('quote-item-template').innerHTML.replaceAll('__INDEX__', String(index)));
                container.lastElementChild.querySelector('input').focus();
                this.disabled = index + 1 >= 50;
            });
        </script>
    @endif
</x-layouts.app>
