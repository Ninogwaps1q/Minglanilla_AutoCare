<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterReportRequest;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReportMetricsService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportController extends Controller
{
    public function index(FilterReportRequest $request, ReportMetricsService $reports): View
    {
        $request->user()->managedShopOrFail();
        [$from, $to] = $reports->period($request->validated());

        return view('shop.exports', compact('from', 'to'));
    }

    public function __invoke(FilterReportRequest $request, ReportMetricsService $reports, string $dataset): StreamedResponse
    {
        $admin = $request->routeIs('admin.reports.csv');
        $allowed = $admin ? ['shops', 'users', 'payments', 'reports'] : ['bookings', 'repairs', 'invoices', 'payments'];
        abort_unless(in_array($dataset, $allowed, true), 404);
        $shop = $admin ? null : $request->user()->managedShopOrFail();
        [$from, $to] = $reports->period($request->validated());
        if ($from->gt($to) || $to->diffInDays($from, true) > 366) {
            throw ValidationException::withMessages(['to' => __('extensions.export.range')]);
        }

        $dateFrom = $from->toDateString();
        $dateTo = $to->toDateString();
        if ($dataset === 'reports') {
            $columns = ['metric', 'value'];
            $rows = collect($reports->admin($from, $to)['metrics'])
                ->map(fn ($value, string $label): array => [__('ui.'.$label), $value])->values();
        } else {
            [$model, $dateColumn, $columns] = match ($dataset) {
                'bookings' => [Appointment::class, 'appointment_date', ['id', 'appointment_date', 'appointment_time', 'vehicle_id', 'status', 'service_price']],
                'repairs' => [RepairJob::class, 'created_at', ['id', 'job_number', 'vehicle_id', 'status', 'created_at', 'completed_at', 'estimated_cost', 'actual_cost']],
                'invoices' => [Invoice::class, 'created_at', ['id', 'invoice_number', 'status', 'created_at', 'subtotal', 'discount', 'tax', 'total']],
                'payments' => [Payment::class, 'payment_date', ['id', 'invoice_id', 'shop_id', 'payment_date', 'payment_method', 'status', 'amount', 'refund_status', 'refund_amount']],
                'shops' => [Shop::class, 'created_at', ['id', 'name', 'provider_type', 'verification_status', 'shop_status', 'created_at']],
                'users' => [User::class, 'created_at', ['id', 'role', 'status', 'created_at']],
            };

            $query = $model === Appointment::class ? Appointment::withTrashed() : $model::query();
            $query->when($shop, fn ($query) => $query->where('shop_id', $shop->id));
            $rows = $query->where($dateColumn, '>=', $dateFrom)->where($dateColumn, '<', $to->addDay()->toDateString())
                ->select($columns)->lazyById(500)->map(fn ($record): array => array_map(fn (string $key) => $record->getRawOriginal($key), $columns));
        }

        ActivityLog::query()->create([
            'user_id' => $request->user()->id, 'module' => 'reports', 'action' => 'exported_csv',
            'description' => __('extensions.export.audit', ['dataset' => __('extensions.export.'.$dataset), 'from' => $dateFrom, 'to' => $dateTo]),
        ]);

        return response()->streamDownload(function () use ($columns, $rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map(fn (string $column): string => __('extensions.export.columns.'.$column), $columns), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(static function ($value) {
                    return is_string($value) && preg_match('/^(?:[\t\r\n]|[\s\p{Z}\x{FEFF}]*[=+\-@])/u', $value)
                        ? "'".$value : $value;
                }, $row), ',', '"', '');
            }
            fclose($stream);
        }, "{$dataset}-{$dateFrom}-{$dateTo}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
