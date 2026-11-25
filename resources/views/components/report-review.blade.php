@props(['review'])

@if ($review->status === 'published')
    <details class="mt-2">
        <summary>Report review</summary>
        <form method="POST" action="{{ route('reviews.report', $review) }}" class="vstack gap-2 mt-2">@csrf
            <label class="form-label" for="review-report-{{ $review->id }}">Why are you reporting this review?</label>
            <textarea class="form-control" id="review-report-{{ $review->id }}" name="reason" rows="2" maxlength="500" required></textarea>
            <button class="btn btn-outline-primary align-self-start">Send report</button>
        </form>
    </details>
@endif
