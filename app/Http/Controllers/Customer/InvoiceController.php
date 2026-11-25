<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PayMongoCheckout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class InvoiceController extends Controller
{
    public function cancel(Request $request, Payment $payment, PayMongoCheckout $checkout): RedirectResponse
    {
        abort_unless($payment->customer_id === $request->user()->id && $payment->status === 'checkout_pending', 404);
        if (! $payment->paymongo_session_id || ! $checkout->expire($payment->paymongo_session_id)) {
            throw ValidationException::withMessages(['payment' => 'This checkout cannot be cancelled while payment may be processing. Please wait and refresh.']);
        }
        Payment::query()->whereKey($payment->id)->where('status', 'checkout_pending')->update(['status' => 'cancelled']);

        return to_route('customer.invoices.index')->with('status', 'Checkout cancelled. You can start a new payment.');
    }

    public function checkout(Request $request, Invoice $invoice, PayMongoCheckout $checkout): RedirectResponse
    {
        abort_unless($invoice->customer_id === $request->user()->id && $invoice->status === 'unpaid', 404);
        if (! $checkout->configured()) {
            throw ValidationException::withMessages(['payment' => 'Online checkout is unavailable. Please contact support.']);
        }

        $payment = DB::transaction(function () use ($invoice, $request): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->customer_id === $request->user()->id && $invoice->status === 'unpaid', 404);
            $existing = $invoice->payments()->where('status', 'checkout_pending')->first();
            if ($existing) {
                return $existing;
            }
            $reservedCents = (int) round(((float) $invoice->payments()->whereIn('status', ['pending', 'confirmed'])->sum('amount') - (float) $invoice->payments()->where('refund_status', 'succeeded')->sum('refund_amount')) * 100);
            $amountCentavos = (int) round($invoice->payableTotal() * 100) - $reservedCents;
            if ($amountCentavos < 100) {
                throw ValidationException::withMessages(['payment' => 'There is no available invoice balance to pay online.']);
            }

            return $invoice->payments()->create([
                'customer_id' => $request->user()->id,
                'shop_id' => $invoice->shop_id,
                'amount' => number_format($amountCentavos / 100, 2, '.', ''),
                'payment_method' => 'paymongo',
                'payment_date' => now(),
                'status' => 'checkout_pending',
            ]);
        });

        if ($payment->paymongo_checkout_url) {
            return redirect()->away($payment->paymongo_checkout_url);
        }

        try {
            $session = $checkout->create('invoice-'.$payment->id, 'Invoice '.$invoice->invoice_number, (int) round((float) $payment->amount * 100), $request->user()->email, route('customer.invoices.index'), route('customer.invoices.index'));
        } catch (\Throwable $exception) {
            report($exception);
            if ($exception instanceof RuntimeException && $exception->getCode() >= 400 && $exception->getCode() < 500 && $exception->getCode() !== 429) {
                $payment->update(['status' => 'failed']);
                $request->user()->notifications()->create([
                    'type' => 'payment_verification', 'title' => 'Payment needs attention',
                    'message' => 'PayMongo checkout could not start. You can try again from your invoice.',
                    'data' => ['payment_id' => $payment->id, 'status' => 'failed'],
                ]);
            }
            throw ValidationException::withMessages(['payment' => 'PayMongo checkout could not start. Please try again.']);
        }
        $payment->update(['paymongo_session_id' => $session['id'], 'paymongo_checkout_url' => $session['url']]);

        return redirect()->away($session['url']);
    }

    public function index(Request $request): View
    {
        return view('customer.invoices.index', [
            'invoices' => Invoice::query()
                ->whereBelongsTo($request->user(), 'customer')
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['shop', 'repairJob.quotation', 'repairJob.review', 'items', 'payments'])
                ->latest()
                ->paginate(10)->withQueryString(),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->customer_id === $request->user()->id, 404);

        return view('customer.invoices.show', ['invoice' => $invoice->load(['shop', 'customer', 'repairJob.quotation', 'repairJob.appointment.service', 'items', 'payments'])]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->customer_id === $request->user()->id, 404);

        return Pdf::loadView('components.invoice-document', ['invoice' => $invoice->load(['shop', 'customer', 'repairJob.appointment.service', 'items', 'payments'])])
            ->setPaper('a4')->download("invoice-{$invoice->id}.pdf")
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(StorePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->customer_id === $request->user()->id && $invoice->status === 'unpaid', 404);
        if (! config('services.paymongo.legacy_manual_payments')) {
            throw ValidationException::withMessages(['payment' => 'New payments must use PayMongo checkout. Choose Pay with PayMongo on your invoice.']);
        }

        $payment = DB::transaction(function () use ($invoice, $request): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->customer_id === $request->user()->id && $invoice->status === 'unpaid', 404);
            $submittedTotal = $invoice->payments()->whereIn('status', ['pending', 'confirmed', 'checkout_pending'])->sum('amount');
            $refundedTotal = $invoice->payments()->where('refund_status', 'succeeded')->sum('refund_amount');
            $submittedCents = (int) round(((float) $submittedTotal - (float) $refundedTotal) * 100);
            $paymentCents = (int) round((float) $request->validated('amount') * 100);
            $totalCents = (int) round($invoice->payableTotal() * 100);
            if ($submittedCents + $paymentCents > $totalCents) {
                throw ValidationException::withMessages(['amount' => 'The payment exceeds the remaining invoice balance.']);
            }

            return $invoice->payments()->create([
                'customer_id' => $request->user()->id,
                'shop_id' => $invoice->shop_id,
                'amount' => $request->validated('amount'),
                'payment_method' => $request->validated('payment_method'),
                'reference_number' => $request->validated('reference_number'),
                'payment_date' => now(),
                'notes' => $request->validated('notes'),
                'status' => 'pending',
            ]);
        });
        $invoice->shop->owner->notifications()->create([
            'type' => 'payment_submitted',
            'title' => 'Payment awaiting verification',
            'message' => "{$request->user()->name} submitted a payment of ₱{$payment->amount}.",
            'data' => ['payment_id' => $payment->id],
        ]);

        return back()->with('status', 'Payment submitted for shop verification.');
    }
}
