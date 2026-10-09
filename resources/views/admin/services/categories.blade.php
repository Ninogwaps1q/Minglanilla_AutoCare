<x-layouts.app title="Service categories">
    <div class="page-heading">
        <h1>Service categories</h1>
        <p>Organize the service catalog. Categories in use cannot be deleted.</p>
        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary mt-2">Back to services</a>
    </div>
    <x-form-errors />
    <section class="surface p-4 mb-4" aria-labelledby="add-category">
        <h2 id="add-category" class="h4">Add category</h2>
        <form method="POST" action="{{ route('admin.service-categories.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8"><label for="category-name" class="form-label">Category name</label><input id="category-name" name="name" class="form-control" maxlength="255" required></div>
            <div class="col-md-auto"><button class="btn btn-primary">Add category</button></div>
        </form>
    </section>
    <div class="surface p-4">
        <h2 class="h4">Existing categories</h2>
        @forelse ($categories as $category)
            <section class="py-3 border-bottom" aria-label="{{ $category->name }}">
                <form method="POST" action="{{ route('admin.service-categories.update', $category) }}" class="row g-3 align-items-end">
                    @csrf @method('PUT')
                    <div class="col-md-8"><label for="category-{{ $category->id }}" class="form-label">Category name ({{ $category->services_count }} services)</label><input id="category-{{ $category->id }}" name="name" value="{{ $category->name }}" class="form-control" maxlength="255" required></div>
                    <div class="col-md-auto"><button class="btn btn-outline-primary">Save category</button></div>
                </form>
                @if ($category->services_count === 0)
                    <form method="POST" action="{{ route('admin.service-categories.destroy', $category) }}" class="mt-3" onsubmit="return confirm('Delete this unused category? This cannot be undone.');">
                        @csrf @method('DELETE')<button class="btn btn-outline-danger">Delete unused category</button>
                    </form>
                @else
                    <p class="small text-secondary mt-2 mb-0">Reassign its services before deleting this category.</p>
                @endif
            </section>
        @empty
            <p class="text-secondary mb-0">No categories yet. Add one to start organizing services.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $categories->links() }}</div>
</x-layouts.app>
