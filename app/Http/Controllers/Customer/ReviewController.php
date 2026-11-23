<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Appointment;
use App\Models\RepairJob;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.reviews.index', [
            'appointments' => Appointment::query()->whereBelongsTo($request->user(), 'customer')->where('status', 'completed')
                ->when($request->integer('job') > 0, fn ($query) => $query->whereHas('repairJob', fn ($jobs) => $jobs->whereKey($request->integer('job'))))
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereHas('review', fn ($review) => $review->whereKey($request->integer('focus'))))
                ->with(['shop', 'service', 'bookedServices.service', 'repairJob', 'review'])->latest('appointment_date')->get(),
        ]);
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $repairJob = $request->filled('repair_job_id')
            ? RepairJob::query()->whereBelongsTo($request->user(), 'customer')->whereKey($request->integer('repair_job_id'))->whereIn('status', ['completed', 'released'])->firstOrFail()
            : null;
        $appointment = Appointment::query()->whereBelongsTo($request->user(), 'customer')->where('status', 'completed')
            ->whereKey($repairJob?->appointment_id ?? $request->integer('appointment_id'))->firstOrFail();
        abort_if($repairJob && ($repairJob->shop_id !== $appointment->shop_id || $repairJob->appointment_id !== $appointment->id), 404);
        $repairJob ??= $appointment->repairJob()->whereIn('status', ['completed', 'released'])->first();

        DB::transaction(function () use ($appointment, $repairJob, $request): void {
            $shop = $appointment->shop()->lockForUpdate()->firstOrFail();
            if ($appointment->review()->exists() || ($repairJob && $repairJob->review()->exists())) {
                throw ValidationException::withMessages(['appointment_id' => 'You have already reviewed this booking.']);
            }
            $review = Review::query()->create([
                ...$request->safe()->only(['rating', 'comment']),
                'appointment_id' => $appointment->id,
                'repair_job_id' => $repairJob?->id,
                'customer_id' => $request->user()->id,
                'shop_id' => $appointment->shop_id,
                'status' => 'published',
            ]);
            $shop->refreshPublishedRating();
            $shop->owner->notifications()->create([
                'type' => 'review_received',
                'title' => 'New customer review',
                'message' => "{$request->user()->name} left a {$review->rating}-star review.",
                'data' => ['review_id' => $review->id],
            ]);
        });

        return back()->with('status', 'Thank you for your review.');
    }
}
