<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
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

        $payment = DB::transaction(function () use ($invoice, $request): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->customer_id === $request->user()->id && $invoice->status === 'unpaid', 404);
            $submittedTotal = $invoice->payments()->whereIn('status', ['pending', 'confirmed'])->sum('amount');
            $submittedCents = (int) round((float) $submittedTotal * 100);
            $paymentCents = (int) round((float) $request->validated('amount') * 100);
            $totalCents = (int) round((float) $invoice->total * 100);
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
