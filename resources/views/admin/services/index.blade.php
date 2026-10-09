<x-layouts.app title="Service catalog">
    <div class="page-heading">
        <h1>Service catalog</h1>
        <p>Manage the services shops can offer to customers.</p>
        <a class="btn btn-outline-primary mt-2" href="{{ route('admin.service-categories.index') }}">Manage categories</a>
    </div>
    <section class="surface p-4 mb-4" aria-labelledby="add-service">
        <h2 id="add-service" class="h4 mb-3">Add service</h2>
        @if ($categories->isEmpty())
            <p class="mb-0">Create a category using Manage categories before adding your first service.</p>
        @else
            <form method="POST" action="{{ route('admin.services.store') }}">
                @csrf
                @include('admin.services.fields')
                <button class="btn btn-primary mt-3">Add service</button>
            </form>
        @endif
    </section>
    <form method="GET" action="{{ route('admin.services.index') }}" class="row g-3 mb-4">
        <div class="col-md-5">
            <label class="form-label" for="search">Search services</label>
            <input id="search" class="form-control" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Service name">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="filter-category">Category</label>
            <select id="filter-category" class="form-select" name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-primary">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">Reset</a>
        </div>
    </form>
    <div class="surface table-responsive">
        <table class="table align-middle mb-0">
            <caption class="visually-hidden">Available catalog services</caption>
            <thead><tr><th scope="col">Service</th><th scope="col">Category</th><th scope="col">Reference price</th><th scope="col">Duration</th><th scope="col">Action</th></tr></thead>
            <tbody>
                @forelse ($services as $service)
                    <tr>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->category?->name }}</td>
                        <td>{{ $service->default_price !== null ? 'PHP '.number_format($service->default_price, 2) : 'Not set' }}</td>
                        <td>{{ $service->estimated_duration !== null ? $service->estimated_duration.' min' : 'Not set' }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.services.edit', $service) }}" aria-label="Edit {{ $service->name }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4">No services found. Add a service or change your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $services->links() }}</div>
</x-layouts.app>
