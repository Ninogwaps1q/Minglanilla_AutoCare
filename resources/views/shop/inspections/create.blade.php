<x-layouts.app title="Record inspection">
    <div class="page-heading">
        <h1>Record vehicle inspection</h1>
        <p>{{ $repairJob->job_number }} · {{ $repairJob->vehicle->plate_number }}</p>
    </div>
    <form method="POST" action="{{ route('shop.inspections.store', $repairJob) }}" class="surface p-4">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><p>Please correct the inspection details:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label for="inspection_date" class="form-label">Inspection date</label><input id="inspection_date" name="inspection_date" type="date" class="form-control" value="{{ old('inspection_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
            <div class="col-md-6"><label for="overall_condition" class="form-label">Overall condition</label><select id="overall_condition" name="overall_condition" class="form-select" required>@foreach (['Good', 'Needs Attention', 'Critical'] as $condition)<option @selected(old('overall_condition') === $condition)>{{ $condition }}</option>@endforeach</select></div>
            <div class="col-12"><label for="notes" class="form-label">Inspection notes</label><textarea id="notes" name="notes" class="form-control" maxlength="5000" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <h2 class="h4">Inspection items</h2>
        <div id="inspection-items">
            @foreach (old('items', [['category' => '', 'item_name' => '', 'condition' => 'Good', 'recommendation' => '']]) as $index => $item)
                @include('shop.inspections.item')
            @endforeach
        </div>
        <button id="add-inspection-item" class="btn btn-outline-secondary mb-3" type="button">Add another item</button>
        <div class="d-flex gap-2"><button class="btn btn-primary">Save inspection</button><a class="btn btn-outline-secondary" href="{{ route('shop.repair-jobs.index') }}">Cancel</a></div>
    </form>
    <template id="inspection-item-template">@include('shop.inspections.item', ['index' => '__INDEX__', 'item' => []])</template>
    <script>
        document.getElementById('add-inspection-item').addEventListener('click', function () {
            const container = document.getElementById('inspection-items');
            const index = container.children.length;
            if (index >= 50) { return; }
            container.insertAdjacentHTML('beforeend', document.getElementById('inspection-item-template').innerHTML.replaceAll('__INDEX__', String(index)));
            container.lastElementChild.querySelector('input').focus();
            this.disabled = index + 1 >= 50;
        });
    </script>
</x-layouts.app>
