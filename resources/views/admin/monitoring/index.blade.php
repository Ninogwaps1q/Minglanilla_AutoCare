<x-layouts.app title="Operations monitoring">
    <div class="page-heading"><h1>Operations monitoring</h1><p>Read-only oversight of appointments, repairs, billing, and roadside requests. Records remain managed by their authorized owners.</p></div>
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Monitoring modules">
        @foreach ($modules as $key => $definition)
            <a class="btn {{ $key === $module ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.monitoring.index', ['module' => $key]) }}" @if ($key === $module) aria-current="page" @endif>{{ $definition[0] }}</a>
        @endforeach
    </nav>
    <div class="surface p-4 mb-4">
        <form method="GET" action="{{ route('admin.monitoring.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="module" value="{{ $module }}">
            <div class="col-md-6"><label for="monitor-search" class="form-label">Customer or shop name</label><input id="monitor-search" name="search" type="search" maxlength="100" class="form-control" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-3"><label for="monitor-status" class="form-label">Status</label><select id="monitor-status" name="status" class="form-select"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('admin.monitoring.index', ['module' => $module]) }}">Clear</a></div>
        </form>
        <x-form-errors />
    </div>
    <h2 class="h4">{{ $label }} <span class="text-secondary small">({{ $records->total() }})</span></h2>
    <div class="surface table-responsive">
        <table class="table mb-0"><thead><tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Shop</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Created</th></tr></thead><tbody>
            @forelse ($records as $record)
                <tr>
                    <th scope="row">{{ $record->invoice_number ?? $record->quotation_number ?? $record->job_number ?? '#'.$record->id }}</th>
                    <td>{{ $record->customer?->name ?? 'Unavailable' }}</td>
                    <td>{{ $record->{$shopRelation}?->name ?? 'Unassigned' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
                    <td>
                        @if ($module === 'appointments')
                            {{ $record->appointment_date->format('M j, Y') }} · {{ $record->appointment_time->format('H:i') }}
                        @elseif (in_array($module, ['invoices', 'quotations', 'payments']))
                            PHP {{ number_format($module === 'payments' ? $record->amount : $record->total, 2) }}
                        @elseif ($module === 'emergencies')
                            {{ ucfirst(str_replace('_', ' ', $record->emergency_type)) }}
                        @else
                            {{ Illuminate\Support\Str::limit($record->description, 100) }}
                        @endif
                    </td>
                    <td>{{ $record->created_at?->format('M j, Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-secondary">No records match these filters.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
