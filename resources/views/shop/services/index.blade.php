<x-layouts.app title="Shop services">
    <div class="page-heading">
        <h1>Shop services</h1>
        <p>Set each service price and say whether labor and parts are included. Customers see these details before
            booking.</p>
    </div>
    <form method="POST" action="{{ route('shop.services.store') }}" class="surface p-4 row g-3 mb-4">@csrf <div
            class="col-md-5"><label class="form-label">Service</label><select class="form-select" name="service_id"
                required>
                <option value="">Choose service</option>@foreach($services as $service)
                    <option value="{{ $service->id }}" @selected(old('service_id', $editingService?->service_id) == $service->id)>{{ $service->name }} @if($service->category) —
                    {{ $service->category->name }} @endif
                </option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label">Price</label><input class="form-control" type="number" min="0"
                step="0.01" name="price" value="{{ old('price', $editingService?->price) }}" required></div>
        <div class="col-md-2"><label class="form-label">Available</label><select class="form-select"
                name="availability">
                <option value="1" @selected(old('availability', $editingService?->availability ?? 1) == 1)>Yes</option>
                <option value="0" @selected(old('availability', $editingService?->availability ?? 1) == 0)>No</option>
            </select></div>
        <div class="col-md-2 d-flex align-items-end"><button
                class="btn btn-primary w-100">{{ $editingService ? 'Save changes' : 'Save service' }}</button></div>
        <div class="col-md-6"><label class="form-label" for="labor_included">Is labor included in this
                price?</label><select id="labor_included" class="form-select" name="labor_included">
                <option value="" @selected(old('labor_included', $editingService?->labor_included) === null)>Not specified
                    yet</option>
                <option value="1" @selected((string) old('labor_included', $editingService?->labor_included) === '1')>Yes,
                    basic labor is included</option>
                <option value="0" @selected((string) old('labor_included', $editingService?->labor_included) === '0' || old('labor_included', $editingService?->labor_included) === false)>No, labor is quoted separately
                </option>
            </select></div>
        <div class="col-md-6"><label class="form-label" for="parts_included">Are parts included in this
                price?</label><select id="parts_included" class="form-select" name="parts_included">
                <option value="" @selected(old('parts_included', $editingService?->parts_included) === null)>Not specified
                    yet</option>
                <option value="1" @selected((string) old('parts_included', $editingService?->parts_included) === '1')>Yes,
                    listed parts are included</option>
                <option value="0" @selected((string) old('parts_included', $editingService?->parts_included) === '0' || old('parts_included', $editingService?->parts_included) === false)>No, parts are quoted separately
                </option>
            </select></div>
        <div class="col-12"><label class="form-label" for="service_scope">What is included? (optional)</label><input
                id="service_scope" class="form-control" name="description" maxlength="5000"
                value="{{ old('description', $editingService?->description) }}"
                placeholder="Example: Inspection and standard labor; replacement parts quoted separately">
        </div>
    </form>
    <div class="surface overflow-hidden">
        <div class="list-group list-group-flush">@forelse($shopServices as $shopService)
            <div class="list-group-item p-3 d-flex justify-content-between align-items-center gap-3">
                <div><strong>{{ $shopService->service->name }}</strong>
                    <div class="text-secondary small">₱{{ number_format($shopService->price, 2) }} ·
                        {{ $shopService->availability ? 'Available' : 'Unavailable' }}
                    </div>
                    <div class="small text-secondary">Labor:
                        {{ $shopService->labor_included === null ? 'ask shop' : ($shopService->labor_included ? 'included' : 'separate quote') }}
                        · Parts:
                        {{ $shopService->parts_included === null ? 'ask shop' : ($shopService->parts_included ? 'included' : 'separate quote') }}
                    </div>
                    @if ($shopService->description)
                    <div class="small">{{ $shopService->description }}</div>@endif
                </div>
                <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary"
                        href="{{ route('shop.services.index', ['edit' => $shopService->id]) }}">Edit</a>
                    <form method="POST" action="{{ route('shop.services.destroy', $shopService) }}">@csrf
                        @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                </div>
        </div>@empty<div class="list-group-item p-4 text-secondary">No services added yet.</div>@endforelse
        </div>
    </div>
</x-layouts.app>