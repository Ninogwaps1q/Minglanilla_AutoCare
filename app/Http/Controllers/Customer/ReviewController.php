<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
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
            'repairJobs' => RepairJob::query()->whereBelongsTo($request->user(), 'customer')->whereIn('status', ['completed', 'released'])->with(['shop', 'review'])->latest('completed_at')->get(),
        ]);
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $repairJob = RepairJob::query()->whereBelongsTo($request->user(), 'customer')->whereKey($request->integer('repair_job_id'))->whereIn('status', ['completed', 'released'])->firstOrFail();

        if ($repairJob->review()->exists()) {
            throw ValidationException::withMessages(['repair_job_id' => 'You have already reviewed this repair job.']);
        }

        DB::transaction(function () use ($repairJob, $request): void {
            $review = Review::query()->create([
                ...$request->validated(),
                'customer_id' => $request->user()->id,
                'shop_id' => $repairJob->shop_id,
                'status' => 'published',
            ]);
            $shop = $repairJob->shop()->firstOrFail();
            $shop->update(['average_rating' => $shop->reviews()->avg('rating'), 'total_reviews' => $shop->reviews()->count()]);
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
