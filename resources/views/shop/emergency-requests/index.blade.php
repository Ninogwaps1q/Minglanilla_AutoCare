<x-layouts.app title="Roadside assistance">
    <div class="page-heading">
        <h1>Roadside assistance</h1>
        <p>Respond only to requests assigned to your approved, active shop.</p>
    </div>
    <x-form-errors />
    <section class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Customer</th><th scope="col">Request</th><th scope="col">Location</th><th scope="col">Status</th><th scope="col">Next action</th></tr></thead>
                <tbody>
                    @forelse ($requests as $emergencyRequest)
                        <tr>
                            <td>{{ $emergencyRequest->customer->name }}<br><small>{{ $emergencyRequest->customer->phone }}</small></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $emergencyRequest->emergency_type)) }}<br><small>{{ $emergencyRequest->vehicle?->plate_number }}</small></td>
                            <td>{{ $emergencyRequest->address ?: 'Coordinates shared' }}<br><small>{{ $emergencyRequest->latitude }}, {{ $emergencyRequest->longitude }}</small></td>
                            <td class="text-capitalize">{{ str_replace('_', ' ', $emergencyRequest->status) }}</td>
                            <td>
                                @if ($emergencyRequest->allowedStatuses() !== [])
                                    <form method="POST" action="{{ route('shop.emergency-requests.update', $emergencyRequest) }}" class="d-flex flex-wrap align-items-end gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <div><label class="form-label" for="emergency-status-{{ $emergencyRequest->id }}">Next request status</label>
                                        <select id="emergency-status-{{ $emergencyRequest->id }}" class="form-select" name="status" required>
                                            @foreach ($emergencyRequest->allowedStatuses() as $status)
                                                <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                            @endforeach
                                        </select></div>
                                        <button class="btn btn-primary" type="submit">Update status</button>
                                    </form>
                                @else
                                    <span class="text-secondary small">Request closed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-secondary">No roadside assistance requests are assigned to this shop.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
