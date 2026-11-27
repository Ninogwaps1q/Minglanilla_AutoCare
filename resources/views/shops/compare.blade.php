<x-layouts.app :title="'Compare ' . $service->name">
    <div class="page-heading">
        <h1>Compare {{ $service->name }}</h1>
        <p>Listed prices from lowest to highest.</p>
        <a class="btn btn-outline-primary" href="{{ route('shops.index', ['service' => $service->id]) }}">{{ __('ui.Find providers') }}</a>
    </div>
    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Service area</th>
                        <th>Price</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>@forelse($offers as $offer)
                    <tr>
                        <td><a href="{{ route('shops.show', $offer->shop) }}">{{ $offer->shop->name }}</a><br><span class="small text-secondary">{{ $offer->shop->providerLabel() }}</span></td>
                        <td>{{ $offer->shop->barangay->areaLabel() }}</td>
                        <td class="price">₱{{ number_format($offer->price, 2) }}</td>
                        <td>@if ($offer->shop->total_reviews > 0)<x-star-rating :rating="$offer->shop->average_rating" /><span class="small text-secondary">{{ trans_choice('directory.reviews', $offer->shop->total_reviews) }}</span>@else<span class="text-secondary">{{ __('directory.not_rated') }}</span>@endif</td>
                </tr>@empty<tr>
                        <td colspan="4" class="text-secondary p-4">No approved providers currently offer this service.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
