<x-layouts.app title="Shared service passport">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h1>Shared service passport</h1>
            <p>Read-only vehicle history shared by its owner. Access expires {{ $expiresAt->format('M j, Y g:i A') }}.
            </p>
        </div><a class="btn btn-outline-primary align-self-start d-print-none"
            href="{{ route('shop.passports.scan') }}"><i class="bi bi-qr-code-scan me-2" aria-hidden="true"></i>Scan
            another passport</a>
    </div>
    <section class="surface p-4 mb-4" aria-labelledby="shared-vehicle-title">
        <h2 id="shared-vehicle-title" class="h3">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h2>
        <p class="mb-3">{{ $vehicle->plate_number }} · {{ number_format($vehicle->current_mileage) }} km</p>
        @if ($confirmedAppointment)
            <p class="text-secondary">This vehicle has a confirmed appointment at your shop. You can start its repair job
                with the appointment already selected.</p>
            <a class="btn btn-primary"
                href="{{ route('shop.repair-jobs.create', ['appointment_id' => $confirmedAppointment->id]) }}">Start repair job</a>
        @else
            <p class="text-secondary mb-2">To start a repair job, the customer must book with your shop and you must confirm
                the appointment first.</p>
            <a class="btn btn-outline-primary" href="{{ route('shop.appointments.index') }}">Review shop appointments</a>
        @endif
    </section>
    <section aria-labelledby="shared-history-title">
        <h2 id="shared-history-title" class="h4">Recorded service history</h2>
        <div class="surface table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Service</th>
                        <th scope="col">Shop</th>
                        <th scope="col">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->service_date->format('M j, Y') }}</td>
                            <td>{{ $record->service_type }}</td>
                            <td>{{ $record->shop?->name ?? 'Unavailable' }}</td>
                            <td>PHP {{ number_format($record->total_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-secondary">No service records have been added for this vehicle
                                yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <nav class="d-flex flex-wrap align-items-center gap-3 mt-4" aria-label="Service history pages"><span>Page
                    {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>@if ($previousUrl)<a
                    class="btn btn-outline-primary btn-sm" href="{{ $previousUrl }}">Previous</a>@endif @if ($nextUrl)<a
                    class="btn btn-outline-primary btn-sm" href="{{ $nextUrl }}">Next</a>@endif</nav>
        @endif
    </section>
</x-layouts.app>
