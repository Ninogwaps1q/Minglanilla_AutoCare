<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReviewModerationRequest;
use App\Models\ActivityLog;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:published,removed,hidden'], 'review_id' => ['nullable', 'integer', 'min:1']]);

        return view('admin.reviews.index', [
            'reports' => ReviewReport::query()->with(['review:id,comment,shop_id,status', 'review.shop:id,name', 'reporter:id,name'])
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest()->paginate(10, pageName: 'reports_page'),
            'reviews' => Review::query()
                ->with(['customer:id,name', 'shop:id,name', 'repairJob:id,job_number', 'appointment:id,appointment_date'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['review_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
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
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            if ($review->status !== 'published') {
                return false;
            }

            $review->update(['status' => 'removed']);
            ReviewReport::query()->where('review_id', $review->id)->where('status', 'pending')->update([
                'status' => 'removed', 'decision_reason' => $reason,
                'decided_by' => $request->user()->id, 'decided_at' => now(),
            ]);
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
