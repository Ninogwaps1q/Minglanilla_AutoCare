<x-layouts.app title="Shop estimates">
    <div class="page-heading"><h1>Estimate requests</h1><p>Prepare estimates for customers and start work after they approve.</p></div>
    @include('components.form-errors')
    @forelse ($quotations as $quotation)
        <article class="surface p-4 mb-3">
            <h2 class="h5">{{ $quotation->quotation_number }} · {{ $quotation->customer->name }}</h2>
            <p>{{ $quotation->vehicle->plate_number }} · {{ $quotation->status === 'draft' ? 'Requested' : ucfirst($quotation->status) }}</p>
            <p class="text-break">{{ $quotation->notes }}</p>
            @if ($quotation->status === 'draft')
                <a class="btn btn-primary" href="{{ route('shop.quotations.edit', $quotation) }}">Prepare estimate</a>
            @else
                <p>Total: PHP {{ number_format($quotation->total, 2) }}</p>
                @if (in_array($quotation->status, ['accepted', 'approved'], true))<form method="POST" action="{{ route('shop.quotations.repair', $quotation) }}">@csrf<button class="btn btn-primary">Open repair job</button></form>@endif
            @endif
        </article>
    @empty
        <div class="surface p-4"><h2 class="h5">No estimate requests yet</h2><p>Requests from customers will appear here.</p></div>
    @endforelse
    {{ $quotations->links() }}
</x-layouts.app>
