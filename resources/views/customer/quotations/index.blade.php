<x-layouts.app title="My quotations">
    <div class="page-heading d-flex flex-wrap justify-content-between gap-3"><div><h1>My quotations</h1><p>Request estimates and approve work before repairs begin.</p></div><div><a class="btn btn-primary" href="{{ route('customer.quotations.create') }}">Request quotation</a></div></div>
    @include('components.form-errors')
    @forelse ($quotations as $quotation)
        <article class="surface p-4 mb-3">
            <h2 class="h5">{{ $quotation->shop->name }} · {{ $quotation->quotation_number }}</h2>
            <p>{{ $quotation->vehicle->plate_number }} · <strong>{{ $quotation->status === 'draft' ? 'Awaiting shop estimate' : ($quotation->status === 'sent' && $quotation->valid_until?->isBefore(today()) ? 'Expired' : ucfirst($quotation->status)) }}</strong></p>
            <p class="text-break">{{ $quotation->notes }}</p>
            @if ($quotation->status !== 'draft')
                <ul>@foreach ($quotation->items as $item)<li>{{ $item->description }} · {{ $item->quantity }} × PHP {{ number_format($item->unit_price, 2) }}</li>@endforeach</ul>
                <p><strong>Total: PHP {{ number_format($quotation->total, 2) }}</strong>@if ($quotation->valid_until) · Valid until {{ $quotation->valid_until->format('M j, Y') }}@endif</p>
            @endif
            @if ($quotation->status === 'sent' && ! $quotation->valid_until?->isBefore(today()))
                <form method="POST" action="{{ route('customer.quotations.update', $quotation) }}" class="d-flex gap-2">@csrf @method('PATCH')<button class="btn btn-success" name="status" value="accepted">Accept quotation</button><button class="btn btn-outline-danger" name="status" value="rejected">Decline</button></form>
            @endif
        </article>
    @empty
        <div class="surface p-4">No quotations yet. Request an estimate from a shop to get started.</div>
    @endforelse
</x-layouts.app>
