<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Models\Report;
use App\Services\ReportMetricsService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(FilterReportRequest $request, ReportMetricsService $reportMetrics): View
    {
        [$from, $to] = $reportMetrics->period($request->validated());
        if ($request->hasAny(['from', 'to'])) {
            Report::query()->create(['generated_by' => $request->user()->id, 'report_type' => 'platform_operations', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
        }

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'reportHistory' => Report::query()->with('generatedBy')->latest()->limit(20)->get(),
            ...$reportMetrics->admin($from, $to),
        ]);
    }
}
