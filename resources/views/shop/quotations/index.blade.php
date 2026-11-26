<x-layouts.app title="Shop estimates">
    <div class="page-heading">
        <h1>Estimate requests</h1>
        <p>Prepare estimates for customers and start work after they approve.</p>
    </div>
    @include('components.form-errors')
    @forelse ($quotations as $quotation)
        <article class="surface p-4 mb-3">
            <h2 class="h5">{{ $quotation->quotation_number }} · {{ $quotation->customer->name }}</h2>
            <p>{{ $quotation->vehicle->plate_number }} ·
                {{ __('extensions.estimate.status.'.$quotation->documentStatus()) }}</p>
            <p class="text-break">{{ $quotation->notes }}</p>
            @if ($quotation->status === 'draft')
                <a class="btn btn-primary" href="{{ route('shop.quotations.edit', $quotation) }}">Prepare estimate</a>
            @else
                @if ($quotation->discount > 0)<p class="small mb-1">{{ __('extensions.promo.savings', ['amount' => number_format($quotation->discount, 2)]) }}</p>@endif
                <a class="btn btn-outline-primary mb-3" href="{{ route('shop.quotations.pdf', $quotation) }}">{{ __('extensions.estimate.download') }}</a>
                <p>Total: PHP {{ number_format($quotation->total, 2) }}</p>
                @if (in_array($quotation->status, ['accepted', 'approved'], true) && ! $quotation->superseded_by_id)
                    <form method="POST" action="{{ route('shop.quotations.repair', $quotation) }}">@csrf<button
                class="btn btn-primary">Open repair job</button></form>@endif
            @endif
        </article>
    @empty
        <div class="surface p-4">
            <h2 class="h5">No estimate requests yet</h2>
            <p>Requests from customers will appear here.</p>
        </div>
    @endforelse
    {{ $quotations->links() }}
</x-layouts.app>
