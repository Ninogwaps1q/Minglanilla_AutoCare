<x-layouts.app title="Shop services">
    <div class="page-heading">
        <h1>Shop services</h1>
        <p>Set the services your shop offers and their current prices.</p>
    </div>
    <form method="POST" action="{{ route('shop.services.store') }}" class="surface p-4 row g-3 mb-4">@csrf <div
            class="col-md-5"><label class="form-label">Service</label><select class="form-select" name="service_id"
                required>
                <option value="">Choose service</option>@foreach($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }} @if($service->category) —
                {{ $service->category->name }} @endif</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label">Price</label><input class="form-control" type="number" min="0"
                step="0.01" name="price" required></div>
        <div class="col-md-2"><label class="form-label">Available</label><select class="form-select"
                name="availability">
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save service</button></div>
        <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description">
        </div>
    </form>
    <div class="surface overflow-hidden">
        <div class="list-group list-group-flush">@forelse($shopServices as $shopService)
            <div class="list-group-item p-3 d-flex justify-content-between align-items-center gap-3">
                <div><strong>{{ $shopService->service->name }}</strong>
                    <div class="text-secondary small">₱{{ number_format($shopService->price, 2) }} ·
                        {{ $shopService->availability ? 'Available' : 'Unavailable' }}</div>
                </div>
                <form method="POST" action="{{ route('shop.services.destroy', $shopService) }}">@csrf
                    @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
        </div>@empty<div class="list-group-item p-4 text-secondary">No services added yet.</div>@endforelse
        </div>
    </div>
</x-layouts.app>