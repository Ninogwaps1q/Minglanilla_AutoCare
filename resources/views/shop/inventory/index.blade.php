<x-layouts.app title="Inventory">
    <div class="page-heading">
        <h1>Parts inventory</h1>
        <p>Add spare parts, manage stock, and record the parts used for each active repair.</p>
    </div>
    <x-form-errors />
    @if ($lowStockCount > 0)
        <div class="alert alert-warning" role="status">{{ $lowStockCount }} part(s) are at or below minimum stock. Restock them before the next repair.</div>
    @endif

    <form method="POST" action="{{ route('shop.inventory.store') }}" class="surface p-4 row g-3 mb-4">
        @csrf
        <div class="col-md-4"><label class="form-label" for="name">Part name</label><input id="name" class="form-control" name="name" maxlength="255" required></div>
        <div class="col-md-2"><label class="form-label" for="part_number">Part number</label><input id="part_number" class="form-control" name="part_number" maxlength="255"></div>
        <div class="col-md-2"><label class="form-label" for="brand">Brand</label><input id="brand" class="form-control" name="brand" maxlength="255"></div>
        <div class="col-md-2"><label class="form-label" for="quantity">Opening stock</label><input id="quantity" class="form-control" type="number" min="0" name="quantity" value="0" required></div>
        <div class="col-md-2"><label class="form-label" for="minimum_stock">Minimum stock</label><input id="minimum_stock" class="form-control" type="number" min="0" name="minimum_stock" value="0" required></div>
        <div class="col-md-3"><label class="form-label" for="purchase_price">Purchase price</label><input id="purchase_price" class="form-control" type="number" min="0" step="0.01" name="purchase_price" value="0" required></div>
        <div class="col-md-3"><label class="form-label" for="selling_price">Selling price</label><input id="selling_price" class="form-control" type="number" min="0" step="0.01" name="selling_price" value="0" required></div>
        <div class="col-md-4"><label class="form-label" for="supplier">Supplier</label><input id="supplier" class="form-control" name="supplier" maxlength="255"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Add part</button></div>
    </form>

    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Part</th><th scope="col">Stock</th><th scope="col">Prices</th><th scope="col">Adjust stock</th><th scope="col">Use for repair</th></tr></thead>
                <tbody>
                    @forelse ($parts as $part)
                        <tr>
                            <th scope="row"><strong>{{ $part->name }}</strong><br><span class="text-secondary small">{{ $part->brand }} {{ $part->part_number }}</span></th>
                            <td><span class="{{ $part->quantity <= $part->minimum_stock ? 'text-danger fw-semibold' : '' }}">{{ $part->quantity }}</span> / min {{ $part->minimum_stock }}</td>
                            <td>PHP {{ number_format($part->purchase_price, 2) }} → PHP {{ number_format($part->selling_price, 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('shop.inventory.adjust', $part) }}" class="d-flex gap-2">
                                    @csrf @method('PATCH')
                                    <label class="visually-hidden" for="adjust-{{ $part->id }}">Stock change for {{ $part->name }}</label>
                                    <input id="adjust-{{ $part->id }}" class="form-control form-control-sm" type="number" name="quantity" placeholder="± stock" required>
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                            <td>
                                @if ($part->quantity > 0 && $repairJobs->isNotEmpty())
                                    <form method="POST" action="{{ route('shop.inventory.consume', $part) }}" class="d-flex flex-wrap gap-2">
                                        @csrf
                                        <label class="visually-hidden" for="repair-{{ $part->id }}">Repair job for {{ $part->name }}</label>
                                        <select id="repair-{{ $part->id }}" class="form-select form-select-sm" name="repair_job_id" required>
                                            <option value="">Select repair</option>
                                            @foreach ($repairJobs as $job)
                                                <option value="{{ $job->id }}">{{ $job->job_number }}</option>
                                            @endforeach
                                        </select>
                                        <label class="visually-hidden" for="use-{{ $part->id }}">Units of {{ $part->name }} used</label>
                                        <input id="use-{{ $part->id }}" class="form-control form-control-sm" type="number" name="quantity" min="1" max="{{ $part->quantity }}" placeholder="Units" required>
                                        <button class="btn btn-sm btn-outline-primary">Record usage</button>
                                    </form>
                                @else
                                    <span class="text-secondary small">{{ $part->quantity === 0 ? 'Out of stock' : 'No active repair' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-secondary">No parts in inventory yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $parts->links() }}</div>
</x-layouts.app>
