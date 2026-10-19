<x-layouts.app title="Shop reviews">
    <div class="page-heading"><h1>Customer reviews</h1><p>Thank customers for their feedback and address concerns.</p></div>
    <div class="row g-3">
        @forelse ($reviews as $review)
            <div class="col-12">
                <article class="surface p-4">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $review->customer->name }} · {{ $review->repairJob->job_number }}</h2>
                            <p class="mb-2"><x-star-rating :rating="$review->rating" /></p>
                            <p>{{ $review->comment }}</p>
                        </div>
                        <small class="text-secondary">{{ $review->created_at->format('M j, Y') }}</small>
                    </div>
                    @if ($review->response)
                        <p class="mb-0"><strong>Your response:</strong> {{ $review->response }}</p>
                    @else
                        <form method="POST" action="{{ route('shop.reviews.update', $review) }}" class="row g-2 align-items-end">
                            @csrf
                            @method('PATCH')
                            <div class="col-md-9">
                                <label class="form-label" for="response-{{ $review->id }}">Public response to {{ $review->customer->name }}</label>
                                <input id="response-{{ $review->id }}" class="form-control" name="response" maxlength="2000" placeholder="Thank the customer or address their concern" required>
                            </div>
                            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Publish response</button></div>
                        </form>
                    @endif
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-4 text-secondary">No customer reviews yet.</div></div>
        @endforelse
    </div>
</x-layouts.app>
