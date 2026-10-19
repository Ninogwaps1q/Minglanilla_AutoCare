<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RepairJob;
use App\Models\Shop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->shop_id === $this->shop($request)->id, 404);

        return view('shop.invoices.show', ['invoice' => $invoice->load(['shop', 'customer', 'repairJob', 'items', 'payments'])]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->shop_id === $this->shop($request)->id, 404);

        return Pdf::loadView('components.invoice-document', ['invoice' => $invoice->load(['shop', 'customer', 'repairJob', 'items', 'payments'])])
            ->setPaper('a4')->download("invoice-{$invoice->id}.pdf")
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): View
    {
        return view('shop.invoices.index', [
            'invoices' => $this->shop($request)->invoices()
                ->with(['customer', 'repairJob', 'payments'])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(Request $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $this->shop($request)->id && in_array($repairJob->status, ['completed', 'released'], true), 404);

        $invoice = DB::transaction(function () use ($repairJob, $request): Invoice {
            $repairJob = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            abort_unless(in_array($repairJob->status, ['completed', 'released'], true), 404);
            $existingInvoice = $repairJob->invoice()->first();
            if ($existingInvoice !== null) {
                return $existingInvoice;
            }
            $repairItems = $repairJob->items()->orderBy('id')->get();
            $total = $repairItems->isNotEmpty()
                ? number_format($repairItems->sum(fn ($item): int => (int) round((float) $item->subtotal * 100)) / 100, 2, '.', '')
                : ($repairJob->actual_cost ?? $repairJob->estimated_cost ?? 0);
            if (in_array($repairJob->quotation?->status, ['accepted', 'approved'], true)
                && (int) round((float) $repairJob->quotation->total * 100) !== (int) round((float) $total * 100)) {
                throw ValidationException::withMessages(['invoice' => 'The repair items do not match the customer-approved estimate. Review the repair before issuing an invoice.']);
            }
            if ($repairItems->isNotEmpty() && (int) round((float) $repairJob->actual_cost * 100) !== (int) round((float) $total * 100)) {
                $repairJob->update(['actual_cost' => $total]);
            }
            $invoice = Invoice::query()->create([
                'customer_id' => $repairJob->customer_id,
                'shop_id' => $repairJob->shop_id,
                'repair_job_id' => $repairJob->id,
                'created_by' => $request->user()->id,
                'invoice_number' => 'INV-'.Str::upper(Str::random(10)),
                'subtotal' => $total,
                'total' => $total,
                'status' => 'unpaid',
            ]);

            if ($repairItems->isNotEmpty()) {
                $invoice->items()->createMany($repairItems->map(fn ($item): array => [
                    'description' => ucfirst($item->type).': '.$item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ])->all());
            } else {
                $invoice->items()->create([
                    'description' => $repairJob->description,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'subtotal' => $total,
                ]);
            }

            return $invoice;
        });

        return redirect()->route('shop.invoices.index')->with('status', "Invoice {$invoice->invoice_number} issued.");
    }

    public function updatePayment(UpdatePaymentStatusRequest $request, Payment $payment): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($payment->shop_id === $shop->id && $payment->status === 'pending', 404);

        DB::transaction(function () use ($payment, $request): void {
            $invoice = $payment->invoice()->lockForUpdate()->firstOrFail();
            $payment = $invoice->payments()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === 'pending', 404);
            if ($request->validated('status') === 'confirmed' && (int) round(((float) $invoice->payments()->where('status', 'confirmed')->sum('amount') + (float) $payment->amount) * 100) > (int) round((float) $invoice->total * 100)) {
                throw ValidationException::withMessages(['status' => 'This payment would exceed the invoice total. Review the recorded payments before confirming.']);
            }
            $payment->update([...$request->validated(), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $confirmedTotal = $invoice->payments()->where('status', 'confirmed')->sum('amount');
            $confirmedCents = (int) round((float) $confirmedTotal * 100);
            $totalCents = (int) round((float) $invoice->total * 100);
            $invoice->update(['status' => $confirmedCents >= $totalCents ? 'paid' : 'unpaid']);
            $payment->customer->notifications()->create([
                'type' => 'payment_verification',
                'title' => 'Payment verification updated',
                'message' => $payment->status === 'rejected'
                    ? "Your payment was rejected. Reason: {$payment->review_note}"
                    : 'Your payment was confirmed by the shop.',
                'data' => ['payment_id' => $payment->id],
            ]);
        });

        return redirect()->route('shop.invoices.index')->with('status', $request->validated('status') === 'confirmed' ? 'Payment confirmed. The invoice balance has been updated.' : 'Payment rejected. The customer can submit a corrected payment.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
