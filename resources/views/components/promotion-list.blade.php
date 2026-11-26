@props(['promotions', 'routeName', 'showShop' => false])
<section class="surface p-4">
    @forelse ($promotions as $promotion)
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 border-bottom">
            <div>
                <h2 class="h6 mb-1">{{ $promotion->name }}</h2>
                @if ($showShop)<p class="text-secondary small mb-1">{{ $promotion->shop?->name }}</p>@endif
                <p class="mb-0">{{ $promotion->discountLabel() }} · {{ $promotion->code ?? __('extensions.promo.automatic') }} · {{ $promotion->starts_on->toDateString() }} – {{ $promotion->ends_on->toDateString() }}</p>
                @if ($promotion->disabled_at)<p class="small mb-0">{{ __('extensions.promo.disabled') }}</p>@endif
            </div>
            @unless ($promotion->disabled_at)
                <form method="POST" action="{{ route($routeName, $promotion) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-danger">{{ __('extensions.promo.disable') }}</button>
                </form>
            @endunless
        </div>
    @empty
        <p class="mb-0">{{ __('extensions.promo.empty') }}</p>
    @endforelse
</section>
<div class="mt-3">{{ $promotions->links() }}</div>
