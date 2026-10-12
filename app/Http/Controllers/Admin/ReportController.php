<?php

namespace App\Http\Controllers\Admin;

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

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'reportHistory' => Report::query()->with('generatedBy')->latest()->limit(20)->get(),
            ...$reportMetrics->admin($from, $to),
        ]);
    }

    public function download(FilterReportRequest $request, ReportMetricsService $reportMetrics): Response
    {
        [$from, $to] = $reportMetrics->period($request->validated());
        $reportData = $reportMetrics->admin($from, $to);

        Report::query()->create([
            'generated_by' => $request->user()->id,
            'report_type' => 'platform_operations',
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]);

        return Pdf::loadView('components.report-document', [
            'title' => 'Platform operations report',
            'ownerName' => config('app.name'),
            'from' => $from,
            'to' => $to,
            ...$reportData,
        ])->setPaper('a4')->download("platform-report-{$from->toDateString()}-{$to->toDateString()}.pdf")
            ->header('Cache-Control', 'private, no-store');
    }
}
