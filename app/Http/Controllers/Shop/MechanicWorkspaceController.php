<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Payment;
use App\Models\Province;
use App\Models\Shop;
use App\Services\AppointmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MechanicWorkspaceController extends Controller
{
    public function show(Request $request, string $section): View
    {
        $shop = $this->shop($request);
        $data = ['shop' => $shop, 'section' => $section];
        if ($section === 'coverage') {
            $settings = $shop->mechanic_settings ?? [];
            $data['coverageAreas'] = [
                'barangay' => Barangay::query()->whereKey($settings['coverage_barangays'] ?? [])->pluck('name', 'id'),
                'city' => Municipality::query()->whereKey($settings['coverage_cities'] ?? [])->pluck('name', 'id'),
                'province' => Province::query()->whereKey($settings['coverage_provinces'] ?? [])->pluck('name', 'id'),
            ];
        }
        if ($section === 'earnings') {
            $period = $request->validate(['period' => ['nullable', 'in:day,week,month']])['period'] ?? 'month';
            $from = $this->periodStart($period);
            $payments = Payment::query()->where('shop_id', $shop->id);
            $expenses = DB::table('mechanic_expenses')->where('shop_id', $shop->id)->where('spent_on', '>=', $from->toDateString());
            $confirmed = (clone $payments)->where('status', 'confirmed')->where('payment_date', '>=', $from->copy()->utc());
            $gross = (float) (clone $confirmed)->sum('amount');
            $fees = (float) (clone $confirmed)->sum('paymongo_fee') + (float) (clone $confirmed)->sum('platform_fee');
            $refunds = (float) (clone $payments)->where('refund_status', 'succeeded')->where('refunded_at', '>=', $from->copy()->utc())->sum('refund_amount');
            $expenseTotal = (float) (clone $expenses)->sum('amount');
            $received = (float) (clone $payments)->where('settled_at', '>=', $from->copy()->utc())->sum('settlement_amount');
            $outstanding = $shop->invoices()->whereIn('status', ['unpaid', 'partial'])
                ->withSum(['payments as confirmed_total' => fn ($query) => $query->where('status', 'confirmed')], 'amount')
                ->withSum(['payments as reopened_total' => fn ($query) => $query->where('refund_status', 'succeeded')->where(fn ($query) => $query->whereNull('refund_resolution')->orWhere('refund_resolution', '!=', 'credit'))], 'refund_amount')
                ->get(['id', 'total'])->sum(fn ($invoice): float => max(0, (float) $invoice->total - (float) $invoice->confirmed_total + (float) $invoice->reopened_total));
            $billedByType = DB::table('repair_job_items')->join('invoices', 'repair_job_items.repair_job_id', '=', 'invoices.repair_job_id')
                ->where('invoices.shop_id', $shop->id)->where('invoices.created_at', '>=', $from->copy()->utc())
                ->groupBy('repair_job_items.type')->selectRaw('repair_job_items.type, SUM(repair_job_items.subtotal) as amount')->pluck('amount', 'type');
            $data += [
                'period' => $period,
                'metrics' => [
                    'gross' => $gross, 'refunds' => $refunds, 'payment_fees' => $fees, 'expenses' => $expenseTotal,
                    'net' => $gross - $refunds - $expenseTotal - $fees, 'received' => $received, 'outstanding' => $outstanding,
                    'labor_billed' => $billedByType->only(['labor', 'service', 'inspection_fee'])->sum(),
                    'travel_billed' => $billedByType->only(['home_fee', 'travel_fee'])->sum(),
                    'parts_billed' => $billedByType->get('part', 0),
                ],
                'payments' => $payments->with('invoice:id,invoice_number')->latest('payment_date')->paginate(10)->withQueryString(),
                'expenses' => $expenses->latest('spent_on')->limit(20)->get(),
            ];
        }

        return view('shop.mechanic.workspace', $data);
    }

    public function updateAvailability(Request $request): RedirectResponse
    {
        $settings = $request->validate([
            'online' => ['required', 'boolean'],
            'buffer_minutes' => ['required', 'integer', 'between:0,180'],
            'rest_start' => ['nullable', 'required_with:rest_end', 'date_format:H:i'],
            'rest_end' => ['nullable', 'required_with:rest_start', 'date_format:H:i', 'after:rest_start'],
        ]);
        $this->updateSettings($request, [
            'online' => $request->boolean('online'),
            'buffer_minutes' => (int) $settings['buffer_minutes'],
            'rest' => isset($settings['rest_start']) ? ['start' => $settings['rest_start'], 'end' => $settings['rest_end']] : null,
        ]);

        return back()->with('status', __('mechanic.saved'));
    }

    public function updateCoverage(Request $request): RedirectResponse
    {
        $coverage = $request->validate([
            'radius_km' => ['required', 'integer', 'between:1,100'],
            'home_service' => ['sometimes', 'boolean'],
            'coverage_barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'coverage_level' => ['required', 'in:radius,barangay,city,province'],
            'keep_existing_areas' => ['sometimes', 'boolean'],
            'inspection_fee' => ['required', 'numeric', 'between:0,100000', 'decimal:0,2'],
            'home_fee' => ['required', 'numeric', 'between:0,100000', 'decimal:0,2'],
            'travel_rate' => ['required', 'numeric', 'between:0,10000', 'decimal:0,2'],
        ]);
        $level = $coverage['coverage_level'];
        $barangay = isset($coverage['coverage_barangay']) ? Barangay::query()->with('municipality')->find($coverage['coverage_barangay']) : null;
        $selectedArea = match ($level) {
            'barangay' => $barangay?->id,
            'city' => $barangay?->municipality_id,
            'province' => $barangay?->municipality?->province_id,
            default => null,
        };
        if ($level !== 'radius' && $selectedArea === null) {
            throw ValidationException::withMessages(['coverage_barangay' => __('mechanic.choose_area')]);
        }
        $append = $request->boolean('keep_existing_areas');
        if ($append && $level === 'radius') {
            throw ValidationException::withMessages(['coverage_level' => __('mechanic.choose_area')]);
        }
        unset($coverage['keep_existing_areas']);
        $this->updateSettings($request, [
            ...$coverage,
            'home_service' => $request->boolean('home_service', true),
            'coverage_barangays' => $level === 'barangay' ? [(int) $selectedArea] : [],
            'coverage_cities' => $level === 'city' ? [(int) $selectedArea] : [],
            'coverage_provinces' => $level === 'province' ? [(int) $selectedArea] : [],
        ], requiresLocation: true, appendAreas: $append);

        return back()->with('status', __('mechanic.saved'));
    }

    public function removeCoverageArea(Request $request, string $level, int $area): RedirectResponse
    {
        $shop = $this->shop($request);
        $key = match ($level) {
            'barangay' => 'coverage_barangays',
            'city' => 'coverage_cities',
            'province' => 'coverage_provinces',
            default => abort(404),
        };
        DB::transaction(function () use ($shop, $key, $area): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $settings = $shop->mechanic_settings ?? [];
            $selected = $settings[$key] ?? [];
            abort_unless(in_array($area, $selected, true), 404);
            $settings[$key] = array_values(array_diff($selected, [$area]));
            $shop->update(['mechanic_settings' => $settings]);
        });

        return back()->with('status', __('mechanic.saved'));
    }

    public static function profileRules(): array
    {
        return [
            'experience_years' => ['nullable', 'integer', 'between:0,80'],
            'specializations' => ['nullable', 'string', 'max:255'],
            'vehicle_types' => ['nullable', 'array', 'max:3'],
            'vehicle_types.*' => ['distinct', 'in:car,motorcycle,truck'],
            'certifications' => ['nullable', 'string', 'max:255'],
            'work_history' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->shop($request)->update(['mechanic_profile' => $request->validate(self::profileRules())]);

        return back()->with('status', __('mechanic.saved'));
    }

    public function saveTool(Request $request, ?string $item = null): RedirectResponse
    {
        $tool = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:equipment,carried_part'],
            'quantity' => ['required', 'integer', 'between:0,9999'],
            'available' => ['required', 'boolean'],
            'condition' => ['required', 'in:good,worn,damaged'],
            'maintenance' => ['nullable', 'string', 'max:255'],
            'supports' => ['nullable', 'string', 'max:255'],
        ]);
        $this->updateRecord($request, 'mechanic_tools', $item, $tool, 100);

        return back()->with('status', __('mechanic.saved'));
    }

    public function removeTool(Request $request, string $item): RedirectResponse
    {
        $this->updateRecord($request, 'mechanic_tools', $item, null, 100);

        return back()->with('status', __('mechanic.saved'));
    }

    public function saveProject(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $project = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'vehicle' => ['required', 'string', 'max:100'],
            'permission' => ['accepted'],
            'before' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'after' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $files = [];
        try {
            foreach (['before', 'after'] as $field) {
                if ($request->hasFile($field)) {
                    $files[$field] = $request->file($field)->store("mechanic-portfolio/{$shop->id}", 'local');
                    if (! $files[$field]) {
                        throw ValidationException::withMessages([$field => __('mechanic.photo_failed')]);
                    }
                }
                unset($project[$field]);
            }
            $this->updateRecord($request, 'mechanic_portfolio', null, [...$project, ...$files], (int) $shop->mechanicEntitlements()['portfolio_limit']);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete(array_filter($files));

            throw $exception;
        }

        return back()->with('status', __('mechanic.saved'));
    }

    public function removeProject(Request $request, string $item): RedirectResponse
    {
        $project = $this->updateRecord($request, 'mechanic_portfolio', $item, null, 20);
        Storage::disk('local')->delete(array_filter([$project['before'] ?? null, $project['after'] ?? null]));

        return back()->with('status', __('mechanic.saved'));
    }

    public function publicPhoto(Shop $shop, string $item, string $phase): StreamedResponse
    {
        abort_unless($shop->isIndependentMechanic() && $shop->verification_status === 'approved' && $shop->shop_status === 'active' && $shop->owner->status === 'active', 404);
        $project = $shop->mechanic_portfolio[$item] ?? [];
        $path = $project[$phase] ?? null;
        abort_unless(($project['permission'] ?? false) && $path && Storage::disk('local')->exists($path) && in_array($phase, ['before', 'after'], true), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function recordExpense(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $expense = $request->validate([
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today(Appointment::TIMEZONE)->toDateString()],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'between:0.01,1000000', 'decimal:0,2'],
        ]);
        DB::table('mechanic_expenses')->insert(['shop_id' => $shop->id, ...$expense, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('mechanic.saved'));
    }

    public function export(Request $request): StreamedResponse
    {
        $shop = $this->shop($request);
        $period = $request->validate(['period' => ['nullable', 'in:day,week,month']])['period'] ?? 'month';
        $from = $this->periodStart($period);

        return response()->streamDownload(function () use ($shop, $from): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Type', 'Record ID', 'Date', 'Status', 'Amount (PHP)'], escape: '');
            $payments = Payment::query()->where('shop_id', $shop->id)->where('payment_date', '>=', $from->copy()->utc());
            foreach ($payments->lazyById() as $payment) {
                fputcsv($output, ['Payment', $payment->id, $payment->payment_date->timezone(Appointment::TIMEZONE)->toDateString(), $payment->status, $payment->amount], escape: '');
            }
            foreach (DB::table('mechanic_expenses')->where('shop_id', $shop->id)->where('spent_on', '>=', $from->toDateString())->lazyById() as $expense) {
                fputcsv($output, ['Expense', $expense->id, $expense->spent_on, 'Recorded', $expense->amount], escape: '');
            }
            foreach (Payment::query()->where('shop_id', $shop->id)->where('refund_status', 'succeeded')->where('refunded_at', '>=', $from->copy()->utc())->lazyById() as $refund) {
                fputcsv($output, ['Refund', $refund->id, $refund->refunded_at->timezone(Appointment::TIMEZONE)->toDateString(), 'Succeeded', $refund->refund_amount], escape: '');
            }
            fclose($output);
        }, 'mechanic-earnings.csv', ['Content-Type' => 'text/csv']);
    }

    public function travel(Request $request, Appointment $appointment, AppointmentSchedule $schedule): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($appointment->shop_id === $shop->id, 404);
        $stage = $request->validate(['stage' => ['required', 'in:en_route,arrived']])['stage'];

        DB::transaction(function () use ($shop, $appointment, $stage, $schedule): void {
            Shop::query()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $appointment = $shop->appointments()->lockForUpdate()->findOrFail($appointment->id);
            $field = $stage.'_at';
            if ($appointment->{$field}) {
                return;
            }
            if ($appointment->status !== 'confirmed' || ! $appointment->service_address || ($stage === 'arrived' && ! $appointment->en_route_at)
                || $appointment->rescheduleRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['stage' => __('mechanic.travel_unavailable')]);
            }
            if ($stage === 'en_route' && $schedule->mechanicHasActiveVisit($shop, $appointment)) {
                throw ValidationException::withMessages(['stage' => __('mechanic.active_visit')]);
            }
            $appointment->update([$field => now()]);
            $appointment->customer->notifications()->create([
                'type' => 'appointment_status', 'title' => __('mechanic.'.$stage, [], $appointment->customer->locale ?? 'en'),
                'message' => __('mechanic.travel_update', ['mechanic' => $shop->name], $appointment->customer->locale ?? 'en'),
                'data' => ['appointment_id' => $appointment->id, 'status' => $stage, 'mechanic_update' => $stage === 'en_route'],
            ]);
        });

        return back()->with('status', __('mechanic.saved'));
    }

    private function periodStart(string $period): Carbon
    {
        $now = now(Appointment::TIMEZONE);

        return match ($period) {
            'day' => $now->startOfDay(),
            'week' => $now->startOfWeek(),
            default => $now->startOfMonth(),
        };
    }

    private function updateSettings(Request $request, array $settings, bool $requiresLocation = false, bool $appendAreas = false): void
    {
        $shop = $this->shop($request);
        DB::transaction(function () use ($shop, $settings, $requiresLocation, $appendAreas): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($requiresLocation && ($shop->latitude === null || $shop->longitude === null)) {
                throw ValidationException::withMessages(['radius_km' => __('mechanic.base_required')]);
            }
            $current = $shop->mechanic_settings ?? [];
            if ($appendAreas) {
                foreach (['coverage_barangays', 'coverage_cities', 'coverage_provinces'] as $key) {
                    $settings[$key] = array_values(array_unique([...($current[$key] ?? []), ...$settings[$key]]));
                    if (count($settings[$key]) > 20) {
                        throw ValidationException::withMessages(['coverage_level' => __('mechanic.record_limit')]);
                    }
                }
            }
            $shop->update(['mechanic_settings' => array_replace($current, $settings)]);
        });
    }

    private function updateRecord(Request $request, string $field, ?string $id, ?array $record, int $limit): ?array
    {
        $shop = $this->shop($request);

        return DB::transaction(function () use ($shop, $field, $id, $record, $limit): ?array {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $records = $shop->{$field} ?? [];
            if ($id !== null) {
                abort_unless(isset($records[$id]), 404);
            } elseif (count($records) >= $limit) {
                throw ValidationException::withMessages(['name' => __('mechanic.record_limit')]);
            }
            $id ??= (string) Str::uuid();
            $previous = $records[$id] ?? null;
            if ($record === null) {
                unset($records[$id]);
            } else {
                $records[$id] = $record;
            }
            $shop->update([$field => $records]);

            return $previous;
        });
    }

    private function shop(Request $request): Shop
    {
        $shop = $request->user()->shops()->firstOrFail();
        abort_unless($shop->isIndependentMechanic(), 404);

        return $shop;
    }
}
