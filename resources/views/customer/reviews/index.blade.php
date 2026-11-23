<x-layouts.app title="My reviews">
    <div class="page-heading"><h1>Rate your completed bookings</h1><p>Your feedback helps other drivers choose a trusted provider.</p></div>
    <div class="row g-3">
        @forelse ($appointments as $appointment)
            <div class="col-12">
                <article class="surface p-4">
                    <h2 class="h5">{{ $appointment->shop->name }} · {{ $appointment->repairJob?->job_number ?? $appointment->appointment_date->format('M j, Y') }}</h2>
                    <p class="text-secondary">{{ $appointment->bookedServices->isNotEmpty() ? $appointment->bookedServices->pluck('service.name')->filter()->implode(', ') : ($appointment->service?->name ?? 'Vehicle service') }}</p>
                    @if ($appointment->review)
                        <p class="mb-0 d-flex flex-wrap align-items-center gap-2"><span>Your rating:</span><x-star-rating :rating="$appointment->review->rating" />@if ($appointment->review->comment)<span>— {{ $appointment->review->comment }}</span>@endif</p>
                    @else
                        <form method="POST" action="{{ route('customer.reviews.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                            <div class="col-md-2">
                                <label class="form-label" for="rating-{{ $appointment->id }}">Your rating</label>
                                <select id="rating-{{ $appointment->id }}" class="form-select" name="rating">
                                    @for ($rating = 5; $rating >= 1; $rating--)
                                        <option value="{{ $rating }}">{{ $rating }}/5</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="comment-{{ $appointment->id }}">Your review (optional)</label>
                                <input id="comment-{{ $appointment->id }}" class="form-control" name="comment" maxlength="2000" placeholder="What went well or could improve?">
                            </div>
                            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Publish review</button></div>
                        </form>
                    @endif
                </article>
            </div>
        @empty
            <div class="col-12"><div class="surface p-4 text-secondary">Complete a booking to leave a review.</div></div>
        @endforelse
    </div>
</x-layouts.app>
