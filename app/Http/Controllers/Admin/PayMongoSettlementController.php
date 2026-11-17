<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Services\PayMongoCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayMongoSettlementController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['payment_id' => ['nullable', 'string', 'max:100']]);
        $paymentId = trim((string) $request->query('payment_id', ''));

        return view('admin.paymongo-settlements', [
            'pendingPayments' => Payment::query()->with(['shop', 'invoice', 'customer'])->whereNotNull('paymongo_payment_id')->where('status', 'confirmed')->where('settlement_status', 'pending')->oldest()->paginate(15),
            'settledPayments' => Payment::query()->with(['shop', 'invoice'])->where('settlement_status', 'settled')->latest('settled_at')->limit(20)->get(),
            'refundRequests' => Payment::query()->with(['shop', 'invoice', 'customer'])->where('refund_status', 'requested')->oldest('refund_requested_at')->get(),
            'paymentLookup' => $paymentId !== '' ? Payment::query()->with(['shop', 'invoice'])->where('paymongo_payment_id', $paymentId)->first() : null,
            'paymentLookupId' => $paymentId,
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $attributes = $request->validate([
            'settlement_reference' => ['required', 'string', 'max:255'],
            'settlement_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'platform_fee' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $proofPath = $request->file('proof')->store('shop-settlements/'.$payment->shop_id);
        if (! $proofPath) {
            throw ValidationException::withMessages(['proof' => 'Transfer proof could not be saved. Please try again.']);
        }
        try {
            DB::transaction(function () use ($request, $payment, $attributes, $proofPath): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $availableCents = (int) round((float) ($payment->paymongo_net_amount ?? $payment->amount) * 100) - (int) round((float) $attributes['platform_fee'] * 100) - ($payment->refund_status === 'succeeded' ? (int) round((float) $payment->refund_amount * 100) : 0);
                if (! $payment->paymongo_payment_id || $payment->paymongo_fee === null || $payment->paymongo_net_amount === null || $payment->status !== 'confirmed' || $payment->settlement_status !== 'pending' || in_array($payment->refund_status, ['requested', 'processing'], true) || in_array($payment->dispute_status, ['open', 'lost'], true) || $availableCents < 1 || (int) round((float) $attributes['settlement_amount'] * 100) !== $availableCents) {
                    throw ValidationException::withMessages(['settlement_amount' => 'Settlement is on hold or the transfer does not match the balance after fees and refunds.']);
                }
                $payment->update(['settlement_status' => 'settled', 'settlement_reference' => $attributes['settlement_reference'], 'settlement_amount' => $attributes['settlement_amount'], 'platform_fee' => $attributes['platform_fee'], 'settlement_proof_path' => $proofPath, 'settled_by' => $request->user()->id, 'settled_at' => now()]);
                ActivityLog::query()->create(['user_id' => $request->user()->id, 'action' => 'settled_shop_payment', 'module' => 'payments', 'description' => "PayMongo payment #{$payment->id} settled to shop #{$payment->shop_id}.", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
                $payment->shop->owner->notifications()->create(['type' => 'payment_settlement', 'title' => 'Invoice payment settled', 'message' => 'The platform recorded settlement for invoice '.$payment->invoice->invoice_number.'. Reference: '.$attributes['settlement_reference'], 'data' => ['payment_id' => $payment->id]]);
            });
        } catch (\Throwable $exception) {
            Storage::delete($proofPath);
            throw $exception;
        }

        return back()->with('status', 'Shop settlement recorded.');
    }

    public function proof(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($payment->settlement_proof_path && ($request->user()->role === 'admin' || $payment->shop->owner_id === $request->user()->id), 404);

        return Storage::download($payment->settlement_proof_path);
    }

    public function refresh(Payment $payment, PayMongoCheckout $checkout, PayMongoWebhookController $webhook): RedirectResponse
    {
        abort_unless($payment->paymongo_payment_id && $payment->paymongo_session_id && $payment->status === 'confirmed', 404);
        $session = $checkout->retrieve($payment->paymongo_session_id);
        if (! $session || $webhook->processSession($session)->getStatusCode() !== 200 || $payment->fresh()->paymongo_net_amount === null || $payment->fresh()->paymongo_fee === null) {
            throw ValidationException::withMessages(['payment' => 'PayMongo fee details are still unavailable. Check this payment in PayMongo.']);
        }

        return back()->with('status', 'PayMongo payment details refreshed.');
    }

    public function dispute(Request $request, Payment $payment): RedirectResponse
    {
        $attributes = $request->validate(['dispute_status' => ['required', 'in:open,won,lost,resolved'], 'dispute_id' => ['required', 'string', 'max:100'], 'dispute_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'], 'dispute_reason' => ['required', 'string', 'max:255']]);
        abort_unless($payment->paymongo_payment_id, 404);
        $payment->update(['dispute_status' => $attributes['dispute_status'], 'paymongo_dispute_id' => $attributes['dispute_id'], 'dispute_amount' => $attributes['dispute_amount'], 'dispute_reason' => $attributes['dispute_reason']]);

        return back()->with('status', 'Dispute status saved.');
    }
}
