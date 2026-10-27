<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustInventoryRequest;
use App\Http\Requests\ConsumeRepairPartRequest;
use App\Http\Requests\StoreSparePartRequest;
use App\Models\InventoryTransaction;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use App\Models\Shop;
use App\Models\SparePart;
use App\Services\RepairInventoryService;
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
            'parts' => $shop->spareParts()->withSum(['reservations as reserved_units' => fn ($query) => $query->where('status', 'reserved')], 'quantity')->orderBy('name')->paginate(20),
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

    public function adjust(AdjustInventoryRequest $request, SparePart $sparePart, RepairInventoryService $inventory): RedirectResponse
    {
        abort_unless($sparePart->shop_id === $this->shop($request)->id, 404);

        DB::transaction(function () use ($request, $sparePart, $inventory): void {
            $part = SparePart::query()->lockForUpdate()->findOrFail($sparePart->id);
            $quantity = $request->integer('quantity');
            abort_if($part->quantity + $quantity < 0, 422, 'The adjustment would result in negative stock.');
            if ($inventory->availableQuantity($part) + $quantity < 0) {
                throw ValidationException::withMessages(['quantity' => 'This adjustment would remove parts reserved for approved repairs. Release or use those reservations first.']);
            }

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

    public function consume(ConsumeRepairPartRequest $request, SparePart $sparePart, RepairInventoryService $inventory): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($sparePart->shop_id === $shop->id, 404);

        DB::transaction(function () use ($request, $sparePart, $shop, $inventory): void {
            $repairJob = RepairJob::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($request->integer('repair_job_id'));
            if (! in_array($repairJob->status, ['approved', 'in_progress', 'repairing', 'waiting_parts'], true)) {
                throw ValidationException::withMessages(['repair_job_id' => 'Parts can only be used for an active repair job.']);
            }
            if ($repairJob->quotation?->status === 'sent') {
                throw ValidationException::withMessages(['repair_job_id' => 'Wait for the customer to approve the estimate before using stock.']);
            }
            $part = SparePart::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($sparePart->id);
            $quantity = $request->integer('quantity');

            $itemId = $request->integer('repair_job_item_id');
            if (! $itemId && in_array($repairJob->quotation?->status, ['accepted', 'approved'], true)) {
                $itemId = $repairJob->items()->where('type', 'part')->where('spare_part_id', $part->id)
                    ->where('quantity', $quantity)->whereDoesntHave('inventoryTransactions', fn ($query) => $query->where('type', 'repair_consumption'))
                    ->value('id');
            }
            $item = $itemId ? RepairJobItem::query()->where('repair_job_id', $repairJob->id)->lockForUpdate()->findOrFail($itemId) : null;
            if ($item && ($item->type !== 'part' || (int) $item->quantity !== $quantity)) {
                throw ValidationException::withMessages(['quantity' => 'Use the full quantity shown on the part work item.']);
            }
            if ($item && $item->spare_part_id !== null && (int) $item->spare_part_id !== $part->id) {
                throw ValidationException::withMessages(['repair_job_item_id' => 'This work item is linked to a different inventory part.']);
            }
            $reservation = $item ? $item->reservation()->lockForUpdate()->first() : null;
            if ($reservation?->status === 'reserved' && (int) $reservation->spare_part_id !== $part->id) {
                throw ValidationException::withMessages(['repair_job_item_id' => 'This work item reserves a different inventory part.']);
            }
            if ($quantity > ($reservation?->status === 'reserved' ? $part->quantity : $inventory->availableQuantity($part))) {
                throw ValidationException::withMessages(['quantity' => 'Not enough unreserved stock is available for this repair.']);
            }
            if ($item && $item->inventoryTransactions()->where('type', 'repair_consumption')->exists()) {
                throw ValidationException::withMessages(['repair_job_item_id' => 'Stock has already been used for this work item.']);
            }
            if ($item && $item->spare_part_id === null) {
                $item->update(['spare_part_id' => $part->id]);
            }
            if (! $item && in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true)) {
                throw ValidationException::withMessages(['repair_job_item_id' => 'Open the repair job and choose the matching part work item under Use parts from stock. The approved price cannot change.']);
            }
            if (! $item) {
                $subtotal = round($quantity * (float) $part->selling_price, 2);
                if ($subtotal > 9999999999.99 || (float) $repairJob->items()->sum('subtotal') + $subtotal > 9999999999.99) {
                    throw ValidationException::withMessages(['quantity' => 'The part total is too large for this repair.']);
                }
                $item = $repairJob->items()->create([
                    'type' => 'part', 'description' => $part->name, 'quantity' => $quantity,
                    'unit_price' => $part->selling_price, 'subtotal' => $subtotal, 'spare_part_id' => $part->id,
                ]);
                $repairJob->update(['actual_cost' => $repairJob->items()->sum('subtotal')]);
            }
            $part->decrement('quantity', $quantity);
            $item->reservation()->updateOrCreate(
                ['repair_job_item_id' => $item->id],
                ['spare_part_id' => $part->id, 'quantity' => $quantity, 'status' => 'consumed'],
            );
            $part->transactions()->create([
                'type' => 'repair_consumption', 'quantity' => -$quantity,
                'reference_type' => 'repair_job', 'reference_id' => $repairJob->id,
                'repair_job_item_id' => $item->id,
                'notes' => $request->validated('notes'), 'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Part used for the repair and deducted from stock.');
    }

    public function reserveItem(Request $request, RepairJobItem $repairJobItem, RepairInventoryService $inventory): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($repairJobItem->repairJob->shop_id === $shop->id, 404);

        DB::transaction(function () use ($repairJobItem, $inventory): void {
            $job = RepairJob::query()->lockForUpdate()->findOrFail($repairJobItem->repair_job_id);
            if (! in_array($job->status, ['approved', 'waiting_parts', 'repairing', 'in_progress'], true)
                || ! in_array($job->quotation?->status, ['accepted', 'approved'], true)) {
                throw ValidationException::withMessages(['part' => 'Customer approval is required before reserving parts.']);
            }
            $inventory->reserveItem($job->items()->findOrFail($repairJobItem->id));
        });

        return back()->with('status', 'Part reserved for this approved repair.');
    }

    public function returnItem(Request $request, RepairJobItem $repairJobItem): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($repairJobItem->repairJob->shop_id === $shop->id, 404);
        $attributes = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($repairJobItem, $shop, $request, $attributes): void {
            $job = RepairJob::query()->lockForUpdate()->findOrFail($repairJobItem->repair_job_id);
            if ($job->status !== 'cancelled') {
                throw ValidationException::withMessages(['quantity' => 'Unused parts can be returned to stock after this repair is cancelled.']);
            }
            $item = $job->items()->lockForUpdate()->findOrFail($repairJobItem->id);
            if ($item->spare_part_id === null) {
                throw ValidationException::withMessages(['quantity' => 'This work item has no linked inventory part.']);
            }
            $part = SparePart::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($item->spare_part_id);
            $used = -(int) $item->inventoryTransactions()->where('type', 'repair_consumption')->sum('quantity');
            $returned = (int) $item->inventoryTransactions()->where('type', 'repair_return')->sum('quantity');
            if ($attributes['quantity'] > $used - $returned) {
                throw ValidationException::withMessages(['quantity' => 'Return only the unused units previously taken for this repair.']);
            }
            $part->increment('quantity', $attributes['quantity']);
            $part->transactions()->create([
                'type' => 'repair_return', 'quantity' => $attributes['quantity'],
                'reference_type' => 'repair_job', 'reference_id' => $job->id,
                'repair_job_item_id' => $item->id,
                'notes' => $attributes['notes'], 'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Unused part returned to stock.');
    }

    public function consumeItem(ConsumeRepairPartRequest $request, RepairJobItem $repairJobItem, RepairInventoryService $inventory): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($repairJobItem->repairJob->shop_id === $shop->id
            && $request->integer('repair_job_id') === $repairJobItem->repair_job_id
            && $request->integer('repair_job_item_id') === $repairJobItem->id, 404);

        if (! $request->filled('spare_part_id')) {
            throw ValidationException::withMessages(['spare_part_id' => 'Choose the inventory part used for this repair.']);
        }

        $part = SparePart::query()->where('shop_id', $shop->id)->findOrFail($request->integer('spare_part_id'));

        return $this->consume($request, $part, $inventory);
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
