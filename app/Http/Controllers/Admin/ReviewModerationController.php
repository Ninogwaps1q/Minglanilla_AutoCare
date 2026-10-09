<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReviewModerationRequest;
use App\Models\ActivityLog;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:published,hidden']]);

        return view('admin.reviews.index', [
            'reviews' => Review::query()
                ->with(['customer:id,name', 'shop:id,name', 'repairJob:id,job_number'])
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate()
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function update(UpdateReviewModerationRequest $request, Review $review): RedirectResponse
    {
        $review->update($request->validated());
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'updated_review_status',
            'module' => 'review_moderation',
            'description' => "Set review #{$review->id} status to {$review->status}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $review->customer->notifications()->create([
            'type' => 'review_moderation',
            'title' => 'Review visibility updated',
            'message' => "Your review has been {$review->status}.",
            'data' => ['review_id' => $review->id],
        ]);

        return back()->with('status', 'Review visibility updated.');
    }
}
