<x-layouts.app title="Operations monitoring">
    <div class="page-heading"><h1>Operations monitoring</h1><p>Read-only oversight of appointments, repairs, billing, and roadside requests. Records remain managed by their authorized owners.</p></div>
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Monitoring modules">
        @foreach ($modules as $key => $definition)
            <a class="btn {{ $key === $module ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.monitoring.index', ['module' => $key]) }}" @if ($key === $module) aria-current="page" @endif>{{ $definition[0] }}</a>
        @endforeach
    </nav>
    @php
        $aiState = $aiService['configured'] ? $aiService['state'] : 'unconfigured';
        $aiLabels = ['healthy' => 'Available', 'not_checked' => 'Not checked yet', 'unconfigured' => 'Not configured', 'rate_limited' => 'Rate limited', 'provider_busy' => 'Provider busy', 'connection_error' => 'Connection problem', 'configuration_error' => 'Configuration problem', 'model_unavailable' => 'Model unavailable', 'invalid_request' => 'Request rejected', 'no_reply' => 'No reply received', 'provider_error' => 'Provider error'];
    @endphp
    <section class='surface p-4 mb-4' aria-labelledby='ai-service-heading'>
        <div class='d-flex flex-wrap justify-content-between align-items-start gap-3'>
            <div><h2 id='ai-service-heading' class='h5 mb-1'>AI assistant service</h2><p class='mb-0 text-secondary'>Last observed provider response, not a live availability check.</p></div>
            <span class='badge {{ $aiState === 'healthy' ? 'text-bg-success' : ($aiState === 'not_checked' ? 'text-bg-secondary' : 'text-bg-warning') }}'>{{ $aiLabels[$aiState] ?? 'Needs attention' }}</span>
        </div>
        <p class='mb-0 mt-3'>Provider: {{ ucfirst((string) $aiService['provider']) }} | Current model: {{ $aiService['current_model'] }}
            @if ($aiService['checked_at']) | Last checked: {{ \Illuminate\Support\Carbon::parse($aiService['checked_at'])->format('M j, Y g:i A') }} @endif
            @if ($aiService['http_status']) | HTTP {{ $aiService['http_status'] }} @endif
        </p>
        @if ($aiState === 'rate_limited')
            <p class='mb-0 mt-2'>Requests may be limited by quota. Check your Gemini usage and try again later.</p>
        @elseif ($aiState === 'unconfigured')
            <p class='mb-0 mt-2'>Set the Gemini API key on the server to enable free-form answers. Booking and status shortcuts still work.</p>
        @elseif (in_array($aiState, ['provider_busy', 'connection_error'], true))
            <p class='mb-0 mt-2'>The assistant retries temporary provider failures automatically. Check connectivity if this continues.</p>
        @endif
    </section>
    <div class="surface p-4 mb-4">
        <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-sm {{ !empty($filters['attention']) ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.monitoring.index', ['module' => $module, 'attention' => 1]) }}">Needs attention</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.monitoring.index', ['module' => $module]) }}">All {{ strtolower($label) }}</a><span class="small text-secondary align-self-center">Open requests older than {{ $module === 'emergencies' ? '10 minutes' : '24 hours' }}.</span></div>
        <form method="GET" action="{{ route('admin.monitoring.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="module" value="{{ $module }}">
            @if(!empty($filters['attention']))<input type="hidden" name="attention" value="1">@endif
            <div class="col-md-6"><label for="monitor-search" class="form-label">Customer, shop, or reference</label><input id="monitor-search" name="search" type="search" maxlength="100" class="form-control" value="{{ $filters['search'] ?? '' }}"></div>
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
                <tr>
                    <td colspan="6" class="pt-0">
                        @include('admin.monitoring.record-detail', ['record' => $record, 'module' => $module])
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-4 text-secondary">No records match these filters.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</x-layouts.app>
