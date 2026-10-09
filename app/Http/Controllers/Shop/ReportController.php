<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterReportRequest;
use App\Services\ReportMetricsService;
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
            ...$reportMetrics->shop($shop, $from, $to),
        ]);
    }
}
