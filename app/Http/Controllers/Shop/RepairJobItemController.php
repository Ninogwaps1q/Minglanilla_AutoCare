<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairJobItemRequest;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairJobItemController extends Controller
{
    public function store(StoreRepairJobItemRequest $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop->owner_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $repairJob): void {
            $repairJob = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            abort_if(in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) || $repairJob->invoice()->exists(), 409, 'Completed or invoiced repair jobs cannot be changed.');
            abort_if(in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true), 409, 'The estimate is awaiting or has received customer approval. Its price cannot be changed.');
            $attributes = $request->validated();
            $attributes['subtotal'] = round((int) $attributes['quantity'] * (float) $attributes['unit_price'], 2);
            if ($attributes['subtotal'] > 9999999999.99) {
                throw ValidationException::withMessages(['unit_price' => 'The item total is too large. Reduce the price or quantity.']);
            }
            $repairJob->items()->create($attributes);
            $actualCost = $repairJob->items()->sum('subtotal');
            if ($actualCost > 9999999999.99) {
                throw ValidationException::withMessages(['unit_price' => 'The repair total is too large. Reduce the item price or quantity.']);
            }
            $repairJob->update(['actual_cost' => $actualCost]);
        });

        return back()->with('status', 'Repair line item added.');
    }

    public function destroy(Request $request, RepairJobItem $repairJobItem): RedirectResponse
    {
        abort_unless($repairJobItem->repairJob->shop->owner_id === $request->user()->id, 404);
        DB::transaction(function () use ($repairJobItem): void {
            $repairJob = RepairJob::query()->lockForUpdate()->findOrFail($repairJobItem->repair_job_id);
            abort_if(in_array($repairJob->status, ['completed', 'released', 'cancelled'], true) || $repairJob->invoice()->exists(), 409, 'Completed or invoiced repair jobs cannot be changed.');
            abort_if(in_array($repairJob->quotation?->status, ['sent', 'accepted', 'approved'], true), 409, 'The estimate is awaiting or has received customer approval. Its price cannot be changed.');
            $repairJobItem->delete();
            $repairJob->update(['actual_cost' => $repairJob->items()->sum('subtotal')]);
        });

        return back()->with('status', 'Repair line item removed.');
    }
}
