<x-layouts.app title="Vehicle catalog">
    <div class="page-heading">
        <h1>Vehicle catalog</h1>
        <p>Add or correct the makes, models, and variants customers can choose. Existing vehicle records keep their details.</p>
    </div>
    @if ($errors->any())<div class="alert alert-danger" role="alert"><strong>Check the catalog details:</strong><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <section class="col-lg-5" aria-labelledby="makes-heading">
            <div class="surface p-4 h-100">
                <h2 id="makes-heading" class="h4">Makes</h2>
                <form method="POST" action="{{ route('admin.vehicle-makes.store') }}" class="row g-2 mb-4">
                    @csrf
                    <div class="col-sm-6"><label class="form-label" for="new-make">Make</label><input id="new-make" class="form-control" name="name" maxlength="100" value="{{ old('name') }}" required></div>
                    <div class="col-sm-6"><label class="form-label" for="new-make-type">Type</label><select id="new-make-type" class="form-select" name="vehicle_type" required><option value="car">Car</option><option value="motorcycle">Motorcycle</option></select></div>
                    <div class="col-12"><button class="btn btn-primary">Add make</button></div>
                </form>
                <form method="GET" action="{{ route('admin.vehicle-catalog.index') }}" class="mb-3"><label class="form-label" for="filter-make-type">Show makes</label><select id="filter-make-type" class="form-select" name="type" onchange="this.form.submit()"><option value="">All types</option><option value="car" @selected($selectedType === 'car')>Cars</option><option value="motorcycle" @selected($selectedType === 'motorcycle')>Motorcycles</option></select></form>
                <div class="d-grid gap-2">
                    @foreach ($makes as $make)
                        <form method="POST" action="{{ route('admin.vehicle-makes.update', $make) }}" class="d-flex flex-wrap align-items-end gap-2 border-bottom pb-2">
                            @csrf @method('PUT')
                            <div class="flex-grow-1"><label class="form-label" for="make-{{ $make->id }}">{{ ucfirst($make->vehicle_type) }} make · {{ $make->models_count }} models</label><input id="make-{{ $make->id }}" class="form-control" name="name" value="{{ $make->name }}" maxlength="100" required></div>
                            <button class="btn btn-outline-primary">Save</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="col-lg-7" aria-labelledby="models-heading">
            <div class="surface p-4">
                <h2 id="models-heading" class="h4">Models and variants</h2>
                <p class="text-secondary">Separate variants with commas. Customers can still choose “Other / not listed.”</p>
                <form method="POST" action="{{ route('admin.vehicle-models.store') }}" class="row g-2 mb-4">
                    @csrf
                    <div class="col-sm-6"><label class="form-label" for="new-model-make">Make</label><select id="new-model-make" class="form-select" name="vehicle_make_id" required><option value="">Select make</option>@foreach ($allMakes as $make)<option value="{{ $make->id }}">{{ $make->name }} ({{ $make->vehicle_type }})</option>@endforeach</select></div>
                    <div class="col-sm-6"><label class="form-label" for="new-model">Model</label><input id="new-model" class="form-control" name="name" maxlength="100" required></div>
                    <div class="col-12"><label class="form-label" for="new-variants">Variants</label><input id="new-variants" class="form-control" name="variants" placeholder="Base, E, G"></div>
                    <div class="col-12"><button class="btn btn-primary">Add model</button></div>
                </form>
                @foreach ($models as $model)
                    <form method="POST" action="{{ route('admin.vehicle-models.update', $model) }}" class="row g-2 border-top py-3">
                        @csrf @method('PUT')
                        <div class="col-sm-5"><label class="form-label" for="model-make-{{ $model->id }}">Make</label><select id="model-make-{{ $model->id }}" class="form-select" name="vehicle_make_id" required>@foreach ($allMakes as $make)<option value="{{ $make->id }}" @selected($make->id === $model->vehicle_make_id)>{{ $make->name }} ({{ $make->vehicle_type }})</option>@endforeach</select></div>
                        <div class="col-sm-7"><label class="form-label" for="model-name-{{ $model->id }}">Model</label><input id="model-name-{{ $model->id }}" class="form-control" name="name" value="{{ $model->name }}" maxlength="100" required></div>
                        <div class="col-sm-9"><label class="form-label" for="model-variants-{{ $model->id }}">Variants</label><input id="model-variants-{{ $model->id }}" class="form-control" name="variants" value="{{ implode(', ', $model->variants ?? []) }}" maxlength="2000"></div>
                        <div class="col-sm-3 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Save</button></div>
                    </form>
                @endforeach
                {{ $models->links() }}
            </div>
        </section>
    </div>
</x-layouts.app>
