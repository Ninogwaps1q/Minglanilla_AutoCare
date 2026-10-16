<x-layouts.app title="My reviews">
    <div class="page-heading"><h1>Rate your completed repairs</h1><p>Your feedback helps other drivers choose a trusted provider.</p></div>
    <div class="row g-3">
        @forelse ($repairJobs as $repairJob)
            <div class="col-12">
                <article class="surface p-4">
                    <h2 class="h5">{{ $repairJob->shop->name }} · {{ $repairJob->job_number }}</h2>
                    <p class="text-secondary">{{ $repairJob->description }}</p>
                    @if ($repairJob->review)
                        <p class="mb-0">Your rating: {{ $repairJob->review->rating }}/5 — {{ $repairJob->review->comment }}</p>
                    @else
                        <form method="POST" action="{{ route('customer.reviews.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="repair_job_id" value="{{ $repairJob->id }}">
                            <div class="col-md-2">
                                <label class="form-label" for="rating-{{ $repairJob->id }}">Your rating</label>
                                <select id="rating-{{ $repairJob->id }}" class="form-select" name="rating">
                                    @for ($rating = 5; $rating >= 1; $rating--)
                                        <option value="{{ $rating }}">{{ $rating }}/5</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="comment-{{ $repairJob->id }}">Your review (optional)</label>
                                <input id="comment-{{ $repairJob->id }}" class="form-control" name="comment" maxlength="2000" placeholder="What went well or could improve?">
                            </div>
                            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Publish review</button></div>
                        </form>
                    @endif
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-4 text-secondary">Complete a repair job to leave a review.</div></div>
        @endforelse
    </div>
</x-layouts.app>
