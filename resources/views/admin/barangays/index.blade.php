<x-layouts.app title="Barangays">
    <div class="page-heading">
        <h1>Barangays</h1>
        <p>Search and manage the Philippine address catalog used for registration and discovery.</p>
    </div>
    <x-form-errors />
    <form method="GET" action="{{ route('barangays.index') }}" class="surface p-4 mb-4 row g-3 align-items-end">
        <div class="col-12"><x-address-picker prefix="barangay-filter" area-only :area="$filters" /></div>
        <div class="col-md-6">
            <label class="form-label" for="barangay-search">Barangay name</label>
            <input id="barangay-search" type="search" class="form-control" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-auto d-flex flex-wrap gap-2">
            <button class="btn btn-primary">Find barangays</button>
            <a href="{{ route('barangays.index') }}" class="btn btn-link">Clear filters</a>
        </div>
    </form>
    <section class="surface p-4 mb-4" aria-labelledby="add-barangay">
        <h2 id="add-barangay" class="h4">Add barangay</h2>
        <form method="POST" action="{{ route('barangays.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-12"><x-address-picker prefix="new-barangay" area-only required :area="old()" /></div>
            <div class="col-md-4"><label class="form-label" for="barangay_name">Barangay name</label><input id="barangay_name" class="form-control" name="name" value="{{ old('name') }}" maxlength="255" required></div>
            <div class="col-md-2"><label class="form-label" for="barangay_status">Status</label><select id="barangay_status" class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Add barangay</button></div>
        </form>
    </section>
    <div class="surface table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Name</th><th scope="col">City, province and region</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead>
            <tbody>
                @forelse ($barangays as $barangay)
                    <tr>
                        <th scope="row">{{ $barangay->name }}</th>
                        <td>{{ $barangay->areaLabel() }}</td>
                        <td class="text-capitalize">{{ $barangay->status }}</td>
                        <td>
                            <form method="POST" action="{{ route('barangays.update', $barangay) }}" class="d-flex flex-wrap gap-2">
                                @csrf @method('PATCH')
                                <label class="visually-hidden" for="name-{{ $barangay->id }}">Name of {{ $barangay->name }}</label>
                                <input id="name-{{ $barangay->id }}" name="name" class="form-control form-control-sm" value="{{ $barangay->name }}" maxlength="255" required>
                                <label class="visually-hidden" for="status-{{ $barangay->id }}">Status of {{ $barangay->name }}</label>
                                <select id="status-{{ $barangay->id }}" name="status" class="form-select form-select-sm"><option value="active" @selected($barangay->status === 'active')>Active</option><option value="inactive" @selected($barangay->status === 'inactive')>Inactive</option></select>
                                <button class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-secondary">No barangays match the current search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $barangays->links() }}</div>
</x-layouts.app>
