<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewReportController extends Controller
{
    public function store(Request $request, Review $review): RedirectResponse
    {
        $attributes = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $review, $attributes): void {
            $item = Review::query()->lockForUpdate()->findOrFail($review->id);
            abort_unless($item->status === 'published', 404);
            ReviewReport::query()->firstOrCreate(
                ['review_id' => $item->id, 'reporter_id' => $request->user()->id],
                $attributes,
            );
        });

        return back()->with('status', 'Your report was sent for review.');
    }

    public function dismiss(Request $request, ReviewReport $reviewReport): RedirectResponse
    {
        $attributes = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $reviewReport, $attributes): void {
            $report = ReviewReport::query()->lockForUpdate()->findOrFail($reviewReport->id);
            abort_unless($report->status === 'pending', 409);
            $report->update(['status' => 'dismissed', 'decision_reason' => $attributes['reason'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            ActivityLog::query()->create([
                'user_id' => $request->user()->id, 'action' => 'dismissed_review_report', 'module' => 'review_moderation',
                'description' => "Dismissed report #{$report->id} for review #{$report->review_id}. Reason: ".$attributes['reason'],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
        });

        return back()->with('status', 'Report dismissed and reason recorded.');
    }
}
