<x-layouts.app title="Shop invoices">
    @if ($invoices->isNotEmpty())<p class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('shop.invoices.show', $invoices->first()) }}">Open latest printable invoice</a></p>@endif
    <div class="page-heading"><h1>Invoices and payments</h1><p>Review repair invoices and verify customer payment submissions.</p></div>
    <div class="surface overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Invoice</th><th>Customer</th><th>Repair job</th><th class="text-end">Total</th><th>Payment status</th><th class="text-end">Action</th></tr></thead><tbody>
        @forelse ($invoices as $invoice)
            @php($pendingPayment = $invoice->payments->firstWhere('status', 'pending'))
            <tr><td><strong>{{ $invoice->invoice_number }}</strong><br><span class="text-secondary small">{{ $invoice->created_at->format('M j, Y') }}</span></td><td>{{ $invoice->customer->name }}</td><td>{{ $invoice->repairJob?->job_number ?? 'Manual invoice' }}</td><td class="text-end">₱{{ number_format($invoice->total, 2) }}</td><td><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }} text-capitalize">{{ $invoice->status }}</span>@if ($pendingPayment)<div class="small text-secondary mt-1">₱{{ number_format($pendingPayment->amount, 2) }} awaiting review</div>@endif</td><td class="text-end">@if ($pendingPayment)<form method="POST" action="{{ route('shop.payments.update', $pendingPayment) }}" class="d-inline-flex gap-1">@csrf @method('PATCH')<button class="btn btn-sm btn-success" name="status" value="confirmed">Confirm</button><button class="btn btn-sm btn-outline-danger" name="status" value="rejected">Reject</button></form>@else<span class="text-secondary small">No pending payment</span>@endif</td></tr>
        @empty
            <tr><td colspan="6" class="p-4 text-secondary">No invoices yet. Complete a repair job, then issue its invoice.</td></tr>
        @endforelse
    </tbody></table></div></div>
</x-layouts.app>
