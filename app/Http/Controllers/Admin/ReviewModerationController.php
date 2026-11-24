<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReviewModerationRequest;
use App\Models\ActivityLog;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:published,removed,hidden']]);

        return view('admin.reviews.index', [
            'reviews' => Review::query()
                ->with(['customer:id,name', 'shop:id,name', 'repairJob:id,job_number', 'appointment:id,appointment_date'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate()
                ->withQueryString(),
            'filters' => $filters,
            'violations' => UpdateReviewModerationRequest::VIOLATIONS,
        ]);
    }

    public function update(UpdateReviewModerationRequest $request, Review $review): RedirectResponse
    {
        $violation = UpdateReviewModerationRequest::VIOLATIONS[$request->validated('violation')];
        $reason = $request->validated('reason');
        $removed = DB::transaction(function () use ($request, $review, $violation, $reason): bool {
            $shop = Shop::query()->lockForUpdate()->findOrFail($review->shop_id);
            $review->refresh();
            if ($review->status !== 'published') {
                return false;
            }

            $review->update(['status' => 'removed']);
            $shop->refreshPublishedRating();
            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'removed_review',
                'module' => 'review_moderation',
                'description' => "Removed review #{$review->id} for {$violation}. Reason: {$reason}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            $review->customer->notifications()->create([
                'type' => 'review_moderation',
                'title' => 'Review removed for a rule violation',
                'message' => "Your review was removed from the public shop page. {$violation}: {$reason}",
                'data' => ['review_id' => $review->id],
            ]);

            return true;
        });

        return back()->with('status', $removed ? 'Review removed and reason recorded.' : 'This review is already off the public shop page.');
    }
}
