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
            <div class="col-12"><label for="notes" class="form-label">General notes (optional)</label><textarea id="notes" name="notes" class="form-control" maxlength="5000" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <h2 class="h4">Service checklist</h2>
        <p class="text-secondary">Tick every item with a problem and describe what you found. Leave healthy items unticked.</p>
        <div id="inspection-items">
            @foreach (old('items', $checklist) as $index => $item)
                @include('shop.inspections.item')
            @endforeach
        </div>
        <div class="d-flex gap-2"><button class="btn btn-primary">Save inspection</button><a class="btn btn-outline-secondary" href="{{ route('shop.repair-jobs.index') }}">Cancel</a></div>
    </form>
</x-layouts.app>
