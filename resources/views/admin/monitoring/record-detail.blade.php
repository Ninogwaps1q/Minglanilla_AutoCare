<details class="border rounded p-2">
    <summary class="fw-semibold">View record #{{ $record->id }} details and timeline</summary>
    <div class="pt-3 px-2">
        <p class="small text-secondary mb-3">
            Created {{ $record->created_at?->format('M j, Y g:i A') ?? 'Unknown' }}.
            Last updated {{ $record->updated_at?->format('M j, Y g:i A') ?? 'Unknown' }}.
            This view is read-only.
        </p>
        @if($module === 'appointments')
            <p class="mb-2"><strong>Vehicle:</strong> {{ $record->vehicle?->plate_number ?? 'Unavailable' }}
                <span class="mx-2">|</span><strong>Service:</strong> {{ $record->serviceCase ? 'Follow-up: '.$record->serviceCase->subject : ($record->service?->name ?? 'General service') }}</p>
            @if($record->repairJob)<p class="mb-2"><strong>Linked repair:</strong> {{ $record->repairJob->job_number }}</p>@endif
            <h3 class="h6">Status history</h3>
            <ol class="mb-2">
                @forelse($record->statusHistories as $event)
                    <li>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }} on {{ $event->changed_at->format('M j, Y g:i A') }} by {{ $event->changedBy?->name ?? 'System or former user' }}</li>
                @empty
                    <li>No status updates recorded yet.</li>
                @endforelse
            </ol>
        @elseif($module === 'repairs')
            <p class="mb-2"><strong>Vehicle:</strong> {{ $record->vehicle?->plate_number ?? 'Unavailable' }}
                @if($record->quotation)<span class="mx-2">|</span><strong>Estimate:</strong> {{ $record->quotation->quotation_number }} ({{ $record->quotation->status }})@endif
                @if($record->invoice)<span class="mx-2">|</span><strong>Invoice:</strong> {{ $record->invoice->invoice_number }} ({{ $record->invoice->status }})@endif</p>
            <h3 class="h6">Repair history</h3>
            <ol class="mb-2">
                @forelse($record->statusHistories as $event)
                    <li>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }} on {{ $event->changed_at->format('M j, Y g:i A') }} by {{ $event->changedBy?->name ?? 'System or former user' }}@if($event->note) — {{ $event->note }}@endif</li>
                @empty
                    <li>No status updates recorded yet.</li>
                @endforelse
            </ol>
        @elseif($module === 'quotations')
            <p class="mb-2"><strong>Repair:</strong> {{ $record->repairJob?->job_number ?? 'Not linked yet' }}
                <span class="mx-2">|</span><strong>Valid until:</strong> {{ $record->valid_until?->format('M j, Y') ?? 'Not set' }}</p>
            <h3 class="h6">Customer decisions</h3>
            <ol class="mb-2">
                @forelse($record->approvalRecords as $event)
                    <li>{{ ucfirst($event->decision) }} on {{ $event->decided_at?->format('M j, Y g:i A') ?? 'Unknown date' }} by {{ $event->customer?->name ?? 'Former customer' }}</li>
                @empty
                    <li>No customer decision recorded yet.</li>
                @endforelse
            </ol>
        @elseif($module === 'invoices')
            <p class="mb-2"><strong>Repair:</strong> {{ $record->repairJob?->job_number ?? 'Unavailable' }}
                <span class="mx-2">|</span><strong>Due:</strong> {{ $record->due_date?->format('M j, Y') ?? 'Not set' }}</p>
            <h3 class="h6">Payment history</h3>
            <ol class="mb-2">
                @forelse($record->payments as $payment)
                    <li>PHP {{ number_format((float) $payment->amount, 2) }} — {{ ucfirst($payment->status) }} on {{ $payment->payment_date?->format('M j, Y g:i A') ?? $payment->created_at?->format('M j, Y g:i A') }}@if($payment->reviewedBy) by {{ $payment->reviewedBy->name }}@endif</li>
                @empty
                    <li>No payments submitted yet.</li>
                @endforelse
            </ol>
        @elseif($module === 'payments')
            <p class="mb-2"><strong>Invoice:</strong> {{ $record->invoice?->invoice_number ?? 'Unavailable' }}
                <span class="mx-2">|</span><strong>Method:</strong> {{ ucfirst(str_replace('_', ' ', $record->payment_method)) }}</p>
            <p class="mb-2"><strong>Submitted:</strong> {{ $record->payment_date?->format('M j, Y g:i A') ?? 'Unknown' }}
                @if($record->reviewed_at)<span class="mx-2">|</span><strong>Reviewed:</strong> {{ $record->reviewed_at->format('M j, Y g:i A') }} by {{ $record->reviewedBy?->name ?? 'Former user' }}@endif</p>
        @elseif($module === 'emergencies')
            <p class="mb-2"><strong>Vehicle:</strong> {{ $record->vehicle?->plate_number ?? 'Not specified' }}
                <span class="mx-2">|</span><strong>Requested:</strong> {{ $record->requested_at?->format('M j, Y g:i A') ?? 'Unknown' }}</p>
            <p class="mb-2"><strong>Location:</strong> {{ $record->address ?: 'Not provided' }}
                @if($record->responded_at)<span class="mx-2">|</span><strong>Shop responded:</strong> {{ $record->responded_at->format('M j, Y g:i A') }}@endif
                @if($record->completed_at)<span class="mx-2">|</span><strong>Completed:</strong> {{ $record->completed_at->format('M j, Y g:i A') }}@endif</p>
        @endif
    </div>
</details>
