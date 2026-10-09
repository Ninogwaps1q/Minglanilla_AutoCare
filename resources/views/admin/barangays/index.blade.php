<x-layouts.app title="Barangays">
    <div class="page-heading">
        <h1>Barangays</h1>
        <p>Manage the local areas used for shop registration and discovery.</p>
    </div>
    <x-form-errors />
    <section class="surface p-4 mb-4" aria-labelledby="add-barangay">
        <h2 id="add-barangay" class="h4">Add barangay</h2>
        <form method="POST" action="{{ route('barangays.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="municipality_id">Municipality</label>
                <select id="municipality_id" class="form-select" name="municipality_id" required>
                    <option value="">Select municipality</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}" @selected(old('municipality_id') == $municipality->id)>{{ $municipality->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label" for="barangay_name">Barangay name</label><input id="barangay_name" class="form-control" name="name" value="{{ old('name') }}" maxlength="255" required></div>
            <div class="col-md-2"><label class="form-label" for="barangay_status">Status</label><select id="barangay_status" class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Add barangay</button></div>
        </form>
    </section>
    <div class="surface table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Name</th><th scope="col">Municipality</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead>
            <tbody>
                @forelse ($barangays as $barangay)
                    <tr>
                        <th scope="row">{{ $barangay->name }}</th>
                        <td>{{ $barangay->municipality->name }}</td>
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
                    <tr><td colspan="4" class="p-4 text-secondary">No barangays yet. Add one above to make it available for shop registration.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $barangays->links() }}</div>
</x-layouts.app>
