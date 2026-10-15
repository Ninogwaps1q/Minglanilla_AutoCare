<x-layouts.app title="Prepare estimate">
    <div class="page-heading"><h1>Prepare estimate</h1><p>{{ isset($quotation) ? $quotation->customer->name.' · '.$quotation->vehicle->plate_number : $repairJob->job_number }}</p></div>
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
        <div id="quote-items">@foreach (old('items', [['description' => '', 'quantity' => 1, 'unit_price' => '']]) as $index => $item) @include('shop.quotations.item') @endforeach</div>
        <button type="button" id="add-quote-item" class="btn btn-outline-secondary mb-3">Add item</button>
        <div><button class="btn btn-primary">Send estimate</button></div>
    </form>
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
</x-layouts.app>
