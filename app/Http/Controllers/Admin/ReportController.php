<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Services\ReportMetricsService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(FilterReportRequest $request, ReportMetricsService $reportMetrics): View
    {
        [$from, $to] = $reportMetrics->period($request->validated());

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            ...$reportMetrics->admin($from, $to),
        ]);
    }
}
