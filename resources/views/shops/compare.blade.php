<x-layouts.app :title="'Compare ' . $service->name">
    <div class="page-heading">
        <h1>Compare {{ $service->name }}</h1>
        <p>Available providers are ordered by listed price. This view presents factual offer data only.</p>
    </div>
    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Barangay</th>
                        <th>Price</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>@forelse($offers as $offer)
                    <tr>
                        <td><a href="{{ route('shops.show', $offer->shop) }}">{{ $offer->shop->name }}</a></td>
                        <td>{{ $offer->shop->barangay->name }}</td>
                        <td>₱{{ number_format($offer->price, 2) }}</td>
                        <td>{{ number_format($offer->shop->average_rating, 1) }}</td>
                </tr>@empty<tr>
                        <td colspan="4" class="text-secondary p-4">No approved shops currently offer this service.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>