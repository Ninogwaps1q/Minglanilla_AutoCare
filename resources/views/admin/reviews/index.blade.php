<x-layouts.app title="Review moderation">
    <div class="page-heading">
        <h1>Review moderation</h1>
        <p>Customer reviews publish automatically. Remove a review only for a rule violation, and record the reason in the activity log.</p>
    </div>

    <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-4">
        <div>
            <label class="form-label" for="status">Review status</label>
            <select id="status" class="form-select" name="status">
                <option value="">All reviews</option>
                <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                <option value="removed" @selected(($filters['status'] ?? '') === 'removed')>Removed for a violation</option>
                <option value="hidden" @selected(($filters['status'] ?? '') === 'hidden')>Previously hidden</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Apply filter</button>
    </form>
    <x-form-errors />

    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Review</th><th>Customer</th><th>Shop</th><th>Status</th><th>Moderation</th></tr></thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td><x-star-rating :rating="$review->rating" /><br><span class="small text-secondary text-break">{{ $review->comment }}</span></td>
                            <td>{{ $review->customer->name }}</td>
                            <td>{{ $review->shop->name }}<br><span class="small text-secondary">{{ $review->repairJob?->job_number ?? $review->appointment?->appointment_date?->format('M j, Y') ?? 'Completed booking' }}</span></td>
                            <td class="text-capitalize">{{ $review->status === 'hidden' ? 'Previously hidden' : $review->status }}</td>
                            <td>
                                @if ($review->status === 'published')
                                    <details>
                                        <summary class="link-danger">Remove for a violation</summary>
                                        <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="vstack gap-2 mt-2">
                                            @csrf
                                            @method('PATCH')
                                            <div>
                                                <label class="form-label" for="violation-{{ $review->id }}">Rule violated</label>
                                                <select class="form-select form-select-sm" id="violation-{{ $review->id }}" name="violation" required>
                                                    <option value="">Choose a rule</option>
                                                    @foreach ($violations as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label" for="reason-{{ $review->id }}">Removal reason</label>
                                                <textarea class="form-control form-control-sm" id="reason-{{ $review->id }}" name="reason" rows="2" maxlength="500" required></textarea>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger align-self-start" type="submit">Remove review</button>
                                        </form>
                                    </details>
                                @else
                                    <span class="small text-secondary">Off the public page</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-secondary">No reviews found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $reviews->links() }}</div>
</x-layouts.app>
