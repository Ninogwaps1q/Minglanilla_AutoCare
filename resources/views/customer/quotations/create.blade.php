<x-layouts.app title="Request estimate">
    <div class="page-heading"><h1>Request an estimate</h1><p>Describe the work you need so a shop can prepare a price estimate.</p></div>
    <form method="POST" action="{{ route('customer.quotations.store') }}" class="surface p-4">
        @csrf
        @include('components.form-errors')
        @if ($vehicles->isEmpty())
            <p>Add your vehicle before requesting an estimate.</p><a class="btn btn-primary" href="{{ route('customer.vehicles.create') }}">Add vehicle</a>
        @elseif ($shops->isEmpty())
            <p>No approved shops are available for quotation requests yet.</p>
        @else
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="shop_id">Shop</label><select id="shop_id" name="shop_id" class="form-select" required><option value="">Choose a shop</option>@foreach ($shops as $shop)<option value="{{ $shop->id }}" @selected(old('shop_id', request('shop_id')) == $shop->id)>{{ $shop->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="vehicle_id">Vehicle</label><select id="vehicle_id" name="vehicle_id" class="form-select" required><option value="">Choose your vehicle</option>@foreach ($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->plate_number }} · {{ $vehicle->make }} {{ $vehicle->model }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="notes">Requested work or symptoms</label><textarea id="notes" name="notes" class="form-control" maxlength="5000" rows="4" required>{{ old('notes') }}</textarea></div>
            </div>
            <button class="btn btn-primary mt-3">Send request</button>
        @endif
    </form>
</x-layouts.app>
