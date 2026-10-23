<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\EmergencyRequest;
use App\Models\Payment;
use App\Models\RepairJob;
use App\Models\Review;
use App\Models\Shop;
use App\Models\SparePart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportMetricsService
{
    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function period(array $filters): array
    {
        $from = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['from'])->startOfDay()
            : now()->toImmutable()->subDays(29)->startOfDay();
        $to = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['to'])->endOfDay()
            : now()->toImmutable()->endOfDay();

        return [$from, $to];
    }

    /** @return array<string, mixed> */
    public function admin(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'metrics' => [
                'Active customers' => User::query()->where('role', 'customer')->where('status', 'active')->count(),
                'Approved shops' => Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')->count(),
                'Appointments' => $this->appointments($from, $to)->count(),
                'Confirmed payments' => Payment::query()->where('status', 'confirmed')->whereBetween('payment_date', [$from, $to])->sum('amount'),
                'Completed repairs' => RepairJob::query()->whereIn('status', ['completed', 'released'])->whereBetween('completed_at', [$from, $to])->count(),
                'Emergency requests' => EmergencyRequest::query()->whereBetween('requested_at', [$from, $to])->count(),
                'Published reviews' => Review::query()->where('status', 'published')->whereBetween('created_at', [$from, $to])->count(),
            ],
            'shopStatuses' => Shop::query()->selectRaw('verification_status, count(*) as total')->groupBy('verification_status')->orderBy('verification_status')->get(),
            'appointmentStatusCounts' => $this->appointmentStatusCounts($from, $to),
            'topShops' => Payment::query()->selectRaw('shop_id, sum(amount) as revenue')->where('status', 'confirmed')->whereBetween('payment_date', [$from, $to])->with('shop:id,name')->groupBy('shop_id')->orderByDesc('revenue')->limit(5)->get(),
            'recentAppointments' => $this->appointments($from, $to)->with(['customer:id,name', 'shop:id,name', 'service:id,name'])->orderByDesc('appointment_date')->limit(10)->get(),
            'appointmentSeries' => $this->dailySeries($this->appointments($from, $to), 'appointment_date', $from, $to),
            'revenueSeries' => $this->dailySeries(Payment::query()->where('status', 'confirmed')->whereBetween('payment_date', [$from, $to]), 'payment_date', $from, $to, 'amount'),
            'popularServices' => $this->appointments($from, $to)->selectRaw('service_id, count(*) as total')->with('service:id,name')->groupBy('service_id')->orderByDesc('total')->limit(5)->get(),
        ];
    }

    /** @return array<string, mixed> */
    public function shop(?Shop $shop, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($shop === null) {
            return [
                'metrics' => [],
                'appointmentSeries' => $this->emptySeries($from, $to),
                'revenueSeries' => $this->emptySeries($from, $to),
                'popularServices' => collect(),
                'lowStockParts' => collect(),
                'recentAppointments' => collect(),
                'appointmentStatusCounts' => [],
            ];
        }

        return [
            'metrics' => [
                'Appointments' => $this->appointments($from, $to, $shop)->count(),
                'Completed repairs' => $shop->repairJobs()->whereIn('status', ['completed', 'released'])->whereBetween('completed_at', [$from, $to])->count(),
                'Confirmed revenue' => Payment::query()->whereBelongsTo($shop)->where('status', 'confirmed')->whereBetween('payment_date', [$from, $to])->sum('amount'),
                'Low-stock parts' => SparePart::query()->whereBelongsTo($shop)->whereColumn('quantity', '<=', 'minimum_stock')->count(),
                'Customers served' => $this->appointments($from, $to, $shop)->distinct()->count('customer_id'),
            ],
            'appointmentSeries' => $this->dailySeries($this->appointments($from, $to, $shop), 'appointment_date', $from, $to),
            'revenueSeries' => $this->dailySeries(Payment::query()->whereBelongsTo($shop)->where('status', 'confirmed')->whereBetween('payment_date', [$from, $to]), 'payment_date', $from, $to, 'amount'),
            'popularServices' => $this->appointments($from, $to, $shop)->selectRaw('service_id, count(*) as total')->with('service:id,name')->groupBy('service_id')->orderByDesc('total')->limit(5)->get(),
            'lowStockParts' => SparePart::query()->whereBelongsTo($shop)->whereColumn('quantity', '<=', 'minimum_stock')->orderBy('quantity')->limit(5)->get(['id', 'name', 'quantity', 'minimum_stock']),
            'recentAppointments' => $this->appointments($from, $to, $shop)->with(['customer:id,name', 'service:id,name'])->orderByDesc('appointment_date')->limit(6)->get(),
            'appointmentStatusCounts' => $this->appointmentStatusCounts($from, $to, $shop),
        ];
    }

    /** @return Builder<Appointment> */
    private function appointments(CarbonImmutable $from, CarbonImmutable $to, ?Shop $shop = null): Builder
    {
        $query = Appointment::query()
            ->where('appointment_date', '>=', $from->toDateString())
            ->where('appointment_date', '<', $to->addDay()->toDateString());

        if ($shop !== null) {
            $query->whereBelongsTo($shop);
        }

        return $query;
    }

    /** @return array<string, int> */
    private function appointmentStatusCounts(CarbonImmutable $from, CarbonImmutable $to, ?Shop $shop = null): array
    {
        $counts = $this->appointments($from, $to, $shop)->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return array_replace(
            array_fill_keys(['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], 0),
            $counts,
        );
    }

    /** @return array{labels: array<int, string>, values: array<int, float>} */
    private function dailySeries(mixed $query, string $column, CarbonImmutable $from, CarbonImmutable $to, ?string $sumColumn = null): array
    {
        $aggregation = $sumColumn === null ? 'count(*)' : "sum({$sumColumn})";
        $values = $query->selectRaw("date({$column}) as date_key, {$aggregation} as total")->groupBy('date_key')->pluck('total', 'date_key');

        return $this->seriesFromValues($values, $from, $to);
    }

    /** @return array{labels: array<int, string>, values: array<int, float>} */
    private function emptySeries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->seriesFromValues(collect(), $from, $to);
    }

    /**
     * @param  Collection<string, float|int|string>  $values
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function seriesFromValues(Collection $values, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $labels = [];
        $seriesValues = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $labels[] = $day->format('M j');
            $seriesValues[] = (float) ($values->get($day->toDateString()) ?? 0);
        }

        return ['labels' => $labels, 'values' => $seriesValues];
    }
}
