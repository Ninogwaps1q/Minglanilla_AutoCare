<x-layouts.app title="Edit service">
    <div class="page-heading">
        <h1>Edit service</h1>
        <p>Update {{ $service->name }} in the shared catalog.</p>
    </div>
    <form method="POST" action="{{ route('admin.services.update', $service) }}" class="surface p-4">
        @csrf
        @method('PUT')
        @include('admin.services.fields')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">Save changes</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">Cancel</a>
        </div>
    </form>
</x-layouts.app>
