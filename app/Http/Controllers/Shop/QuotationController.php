<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationRequest;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.quotations.index', ['quotations' => Quotation::query()
            ->where('shop_id', $this->shop($request)->id)->with(['customer', 'vehicle', 'items'])->latest()->paginate()]);
    }

    public function edit(Request $request, Quotation $quotation): View
    {
        abort_unless($quotation->shop_id === $this->shop($request)->id && $quotation->status === 'draft', 404);

        return view('shop.quotations.create', ['quotation' => $quotation]);
    }

    public function update(StoreQuotationRequest $request, Quotation $quotation, QuotationService $service): RedirectResponse
    {
        abort_unless($quotation->shop_id === $this->shop($request)->id, 404);
        DB::transaction(function () use ($request, $quotation, $service): void {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            abort_unless($quotation->status === 'draft', 404);
            $service->send($quotation, $request->validated('items'), $request->validated('valid_until'), $quotation->notes."\n\nShop: ".$request->validated('notes'));
        });

        return redirect()->route('shop.quotations.index')->with('status', 'Quotation sent to the customer.');
    }

    public function create(RepairJob $repairJob, Request $request): View
    {
        abort_unless($repairJob->shop_id === $this->shop($request)->id, 404);

        return view('shop.quotations.create', compact('repairJob'));
    }

    public function store(StoreQuotationRequest $request, RepairJob $repairJob, QuotationService $service): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $this->shop($request)->id, 404);

        DB::transaction(function () use ($repairJob, $request, $service): void {
            $repairJob = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            abort_if(in_array($repairJob->status, ['completed', 'released', 'cancelled'], true), 409);
            abort_if(in_array($repairJob->quotation?->status, ['accepted', 'approved'], true), 409, 'The approved estimate is locked.');
            abort_if($repairJob->quotation?->status === 'sent', 409, 'An estimate is already awaiting customer approval.');
            $estimateTotal = round(collect($request->validated('items'))->sum(fn (array $item): float => (int) $item['quantity'] * (float) $item['unit_price']), 2);
            if ($repairJob->items()->exists() && abs((float) $repairJob->items()->sum('subtotal') - $estimateTotal) > 0.001) {
                throw ValidationException::withMessages(['items' => 'The estimate total must match the existing repair work items.']);
            }
            $quotation = Quotation::query()->create([
                'customer_id' => $repairJob->customer_id,
                'shop_id' => $repairJob->shop_id,
                'vehicle_id' => $repairJob->vehicle_id,
                'appointment_id' => $repairJob->appointment_id,
                'quotation_number' => 'QT-'.Str::upper(Str::random(10)),
                'status' => 'draft',
            ]);
            $service->send($quotation, $request->validated('items'), $request->validated('valid_until'), $request->validated('notes'));
            $repairJob->update(['quotation_id' => $quotation->id, 'estimated_cost' => $quotation->total, 'status' => 'waiting_approval']);
        });

        return redirect()->route('shop.repair-jobs.index')->with('status', 'Quotation sent to the customer.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
