<?php

namespace App\Http\Controllers\Shop;

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
        $shop = $request->user()->shops()->first();
        if ($request->hasAny(['from', 'to'])) {
            Report::query()->create(['generated_by' => $request->user()->id, 'report_type' => 'shop_performance', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
        }

        return view('shop.reports.index', [
            'shop' => $shop,
            'from' => $from,
            'to' => $to,
            'reportHistory' => Report::query()->whereBelongsTo($request->user(), 'generatedBy')->latest()->limit(10)->get(),
            ...$reportMetrics->shop($shop, $from, $to),
        ]);
    }
}
