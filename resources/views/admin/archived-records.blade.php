<x-layouts.app :title="__('extensions.archive.title')">
    <div class="page-heading"><h1>{{ __('extensions.archive.title') }}</h1></div>
    <x-form-errors />
    <form method="GET" class="surface p-3 mb-3 d-flex flex-wrap align-items-end gap-3">
        <div>
            <label for="archive-type" class="form-label">{{ __('extensions.archive.type') }}</label>
            <select id="archive-type" name="type" class="form-select">
                @foreach ($types as $option)<option @selected($type === $option) value="{{ $option }}">{{ __('extensions.archive.'.$option) }}</option>@endforeach
            </select>
        </div>
        <label class="form-check my-2"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($active)> {{ __('extensions.archive.active') }}</label>
        <button class="btn btn-outline-primary">{{ __('extensions.archive.apply') }}</button>
    </form>
    <div class="surface p-3">
        @forelse ($records as $record)
            <div class="border-bottom py-3 d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <strong>#{{ $record->id }} {{ $record->name ?? $record->plate_number }}</strong>
                    @if ($record->deleted_at)<div class="small text-secondary">{{ $record->deleted_at->format('M j, Y') }}</div>@endif
                </div>
                @if (! $record->anonymized_at && ! ($type === 'users' && $record->isAdmin()))
                    <form method="POST" action="{{ route('admin.archived-records.update', [$type, $record->id]) }}" class="d-flex flex-wrap align-items-end gap-2">
                        @csrf @method('PATCH')
                        <div><label for="archive-reason-{{ $record->id }}" class="form-label">{{ __('extensions.archive.reason') }}</label>
                            <input id="archive-reason-{{ $record->id }}" name="reason" class="form-control" maxlength="500" required>
                        </div>
                        <button class="btn btn-outline-primary" name="action" value="{{ $active ? 'archive' : 'restore' }}">{{ __('extensions.archive.'.($active ? 'archive' : 'restore')) }}</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="mb-0">{{ __('extensions.archive.empty') }}</p>
        @endforelse
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
</x-layouts.app>
