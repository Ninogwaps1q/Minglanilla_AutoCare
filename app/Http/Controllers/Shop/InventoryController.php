<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustInventoryRequest;
use App\Http\Requests\ConsumeRepairPartRequest;
use App\Http\Requests\StoreSparePartRequest;
use App\Models\InventoryTransaction;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\SparePart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        return view('shop.inventory.index', [
            'parts' => $shop->spareParts()->orderBy('name')->paginate(20),
            'lowStockCount' => $shop->spareParts()->whereColumn('quantity', '<=', 'minimum_stock')->count(),
            'repairJobs' => $shop->repairJobs()->whereIn('status', ['approved', 'in_progress', 'repairing', 'waiting_parts'])
                ->latest()->limit(100)->get(['id', 'job_number']),
        ]);
    }

    public function store(StoreSparePartRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $part = $this->shop($request)->spareParts()->create($request->validated());

            if ($part->quantity > 0) {
                $part->transactions()->create([
                    'type' => 'opening_stock',
                    'quantity' => $part->quantity,
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return back()->with('status', 'Part added to inventory.');
    }

    public function adjust(AdjustInventoryRequest $request, SparePart $sparePart): RedirectResponse
    {
        abort_unless($sparePart->shop_id === $this->shop($request)->id, 404);

        DB::transaction(function () use ($request, $sparePart): void {
            $part = SparePart::query()->lockForUpdate()->findOrFail($sparePart->id);
            $quantity = $request->integer('quantity');
            abort_if($part->quantity + $quantity < 0, 422, 'The adjustment would result in negative stock.');

            $part->increment('quantity', $quantity);
            InventoryTransaction::query()->create([
                'spare_part_id' => $part->id,
                'type' => $quantity > 0 ? 'restock' : 'adjustment',
                'quantity' => $quantity,
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Inventory updated.');
    }

    public function consume(ConsumeRepairPartRequest $request, SparePart $sparePart): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($sparePart->shop_id === $shop->id, 404);

        DB::transaction(function () use ($request, $sparePart, $shop): void {
            $part = SparePart::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($sparePart->id);
            $repairJob = RepairJob::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($request->integer('repair_job_id'));
            if (! in_array($repairJob->status, ['approved', 'in_progress', 'repairing', 'waiting_parts'], true)) {
                throw ValidationException::withMessages(['repair_job_id' => 'Parts can only be used for an active repair job.']);
            }
            $quantity = $request->integer('quantity');
            if ($quantity > $part->quantity) {
                throw ValidationException::withMessages(['quantity' => "Only {$part->quantity} units are currently in stock."]);
            }

            $part->decrement('quantity', $quantity);
            $part->transactions()->create([
                'type' => 'repair_consumption', 'quantity' => -$quantity,
                'reference_type' => 'repair_job', 'reference_id' => $repairJob->id,
                'notes' => $request->validated('notes'), 'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Part usage recorded for the repair job.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
