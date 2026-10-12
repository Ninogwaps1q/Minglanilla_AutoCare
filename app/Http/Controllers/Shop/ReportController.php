<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Models\Report;
use App\Services\ReportMetricsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(FilterReportRequest $request, ReportMetricsService $reportMetrics): View
    {
        [$from, $to] = $reportMetrics->period($request->validated());
        $shop = $request->user()->shops()->first();

        return view('shop.reports.index', [
            'shop' => $shop,
            'from' => $from,
            'to' => $to,
            'reportHistory' => Report::query()->whereBelongsTo($request->user(), 'generatedBy')->latest()->limit(10)->get(),
            ...$reportMetrics->shop($shop, $from, $to),
        ]);
    }

    public function download(FilterReportRequest $request, ReportMetricsService $reportMetrics): Response
    {
        [$from, $to] = $reportMetrics->period($request->validated());
        $shop = $request->user()->shops()->firstOrFail();
        $reportData = $reportMetrics->shop($shop, $from, $to);

        Report::query()->create([
            'generated_by' => $request->user()->id,
            'report_type' => 'shop_performance',
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]);

        return Pdf::loadView('components.report-document', [
            'title' => 'Shop performance report',
            'ownerName' => $shop->name,
            'from' => $from,
            'to' => $to,
            ...$reportData,
        ])->setPaper('a4')->download("shop-report-{$shop->id}-{$from->toDateString()}-{$to->toDateString()}.pdf")
            ->header('Cache-Control', 'private, no-store');
    }
}
