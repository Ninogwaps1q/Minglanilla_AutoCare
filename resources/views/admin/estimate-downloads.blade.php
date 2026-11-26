<x-layouts.app :title="__('extensions.estimate.library')">
    <div class="page-heading"><h1>{{ __('extensions.estimate.library') }}</h1></div>
    <x-form-errors />
    <form method="GET" class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label" for="estimate-number">{{ __('extensions.estimate.number') }}</label>
            <input id="estimate-number" class="form-control" name="estimate" value="{{ $search }}" maxlength="100">
        </div>
        <button class="btn btn-outline-primary">{{ __('extensions.estimate.search') }}</button>
    </form>
    <div class="surface p-3">
        @forelse ($estimates as $estimate)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                <div>
                    <strong>{{ $estimate->quotation_number }}</strong><span class="d-block text-secondary">{{ $estimate->shop?->name }} · {{ __('extensions.estimate.status.'.$estimate->documentStatus()) }}</span>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.quotations.pdf', $estimate) }}">{{ __('extensions.estimate.download') }}</a>
            </div>
        @empty
            <p class="mb-0">{{ __('extensions.estimate.empty') }}</p>
        @endforelse
    </div>
    <div class="mt-3">{{ $estimates->links() }}</div>
</x-layouts.app>
