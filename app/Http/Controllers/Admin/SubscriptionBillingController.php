<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\ShopPlanPayment;
use App\Services\PayMongoRefunds;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionBillingController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->dates($request);
        $collected = ShopPlanPayment::query()->where('status', 'approved')->whereBetween('reviewed_at', [$from, $to]);
        $refunded = ShopPlanPayment::query()->where('refund_status', 'succeeded')->whereBetween('refunded_at', [$from, $to]);
        $unsettled = Payment::query()->where('status', 'confirmed')->whereNotNull('paymongo_payment_id')->where('settlement_status', 'pending');
        $knownBalance = (clone $unsettled)->whereNotNull('paymongo_net_amount')->selectRaw('COALESCE(SUM(CASE WHEN paymongo_net_amount - platform_fee - CASE WHEN refund_status = ? THEN COALESCE(refund_amount, 0) ELSE 0 END > 0 THEN paymongo_net_amount - platform_fee - CASE WHEN refund_status = ? THEN COALESCE(refund_amount, 0) ELSE 0 END ELSE 0 END), 0) AS balance', ['succeeded', 'succeeded'])->value('balance');

        return view('admin.subscription-billing', [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'gross' => (clone $collected)->sum('amount'), 'refunded' => (clone $refunded)->sum('refund_amount'),
            'payments' => $this->reportPayments($from, $to)->with('shop.owner')->latest('reviewed_at')->paginate(20)->withQueryString(),
            'refundRequests' => ShopPlanPayment::query()->with(['shop.owner', 'refundReviewer'])->whereIn('refund_status', PayMongoRefunds::HOLDS_SETTLEMENT)->oldest('refund_requested_at')->paginate(10, ['*'], 'refunds_page')->withQueryString(),
            'unsettledBalance' => $knownBalance, 'unknownFees' => (clone $unsettled)->whereNull('paymongo_net_amount')->count(),
            'heldCount' => (clone $unsettled)->where(fn (Builder $query): Builder => $query->whereIn('refund_status', PayMongoRefunds::HOLDS_SETTLEMENT)->orWhereIn('dispute_status', ['open', 'lost']))->count(),
            'history' => ActivityLog::query()->with('user')->where('module', 'payments')->where(fn (Builder $query): Builder => $query->where('action', 'like', 'refund_%')->orWhereIn('action', ['settled_shop_payment', 'reviewed_payment_dispute']))->latest()->limit(30)->get(),
        ]);
    }

    public function review(Request $request, ShopPlanPayment $shopPlanPayment, PayMongoRefunds $refunds): RedirectResponse
    {
        $attributes = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $refunds->review($shopPlanPayment, $request->user()->id, $attributes['decision'], $attributes['notes'] ?? null);

        return back()->with('status', 'Refund review saved. Status: '.str_replace('_', ' ', $shopPlanPayment->fresh()->refund_status).'.');
    }

    public function check(Request $request, ShopPlanPayment $shopPlanPayment, PayMongoRefunds $refunds): RedirectResponse
    {
        $attributes = $request->validate(['refund_id' => ['nullable', 'string', 'regex:/^ref_[A-Za-z0-9_-]+$/', 'max:100']]);
        if (! $refunds->check($shopPlanPayment, $attributes['refund_id'] ?? null)) {
            throw ValidationException::withMessages(['refund' => 'The refund could not be verified. Check its ID, payment, and full amount in PayMongo.']);
        }

        return back()->with('status', 'Refund status checked with PayMongo.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dates($request);

        return response()->streamDownload(function () use ($from, $to): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Subscription payment', 'Shop', 'Plan', 'Confirmed at', 'Amount PHP', 'Method', 'Reference', 'Coverage starts', 'Coverage ends', 'Refund status', 'Refund PHP', 'Refunded at', 'Refund reviewer'], ',', '"', '');
            foreach ($this->reportPayments($from, $to)->with(['shop', 'refundReviewer'])->lazyById(200) as $payment) {
                $row = [$payment->id, $payment->shop->name, $payment->plan_slug, $payment->reviewed_at?->toDateTimeString(), $payment->amount, $payment->paymongo_method ?? 'manual', $payment->reference, $payment->period_starts_at?->toDateTimeString(), $payment->period_ends_at?->toDateTimeString(), $payment->refund_status, $payment->refund_status === 'succeeded' ? $payment->refund_amount : 0, $payment->refunded_at?->toDateTimeString(), $payment->refundReviewer?->name];
                fputcsv($file, array_map($this->csvValue(...), $row), ',', '"', '');
            }
            fputcsv($file, [], ',', '"', '');
            fputcsv($file, ['Current unsettled invoice payments (all dates)', 'Shop', 'Customer payment PHP', 'Gateway fee PHP', 'Platform fee PHP', 'Refunded PHP', 'Balance PHP', 'Refund status', 'Dispute status'], ',', '"', '');
            foreach (Payment::query()->with('shop')->where('status', 'confirmed')->whereNotNull('paymongo_payment_id')->where('settlement_status', 'pending')->lazyById(200) as $payment) {
                $refund = $payment->refund_status === 'succeeded' ? (float) $payment->refund_amount : 0;
                $balance = $payment->paymongo_net_amount === null ? 'Awaiting gateway fees' : max(0, round((float) $payment->paymongo_net_amount - (float) $payment->platform_fee - $refund, 2));
                fputcsv($file, array_map($this->csvValue(...), [$payment->id, $payment->shop->name, $payment->amount, $payment->paymongo_fee, $payment->platform_fee, $refund, $balance, $payment->refund_status, $payment->dispute_status]), ',', '"', '');
            }
            fclose($file);
        }, 'billing-'.$from->toDateString().'-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{Carbon, Carbon} */
    private function dates(Request $request): array
    {
        $attributes = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $from = isset($attributes['from']) ? Carbon::parse($attributes['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($attributes['to']) ? Carbon::parse($attributes['to'])->endOfDay() : now()->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a date range of up to one year, ending on or after the start date.']);
        }

        return [$from, $to];
    }

    private function reportPayments(Carbon $from, Carbon $to): Builder
    {
        return ShopPlanPayment::query()->where('status', 'approved')->where(fn (Builder $query): Builder => $query->whereBetween('reviewed_at', [$from, $to])->orWhere(fn (Builder $query): Builder => $query->where('refund_status', 'succeeded')->whereBetween('refunded_at', [$from, $to])));
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
