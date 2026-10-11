<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairJobItemRequest;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairJobItemController extends Controller
{
    public function store(StoreRepairJobItemRequest $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop->owner_id === $request->user()->id, 404);
        abort_if(in_array($repairJob->status, ['released', 'cancelled'], true), 409, 'Closed repair jobs cannot be changed.');
        $attributes = $request->validated();
        $attributes['subtotal'] = round((int) $attributes['quantity'] * (float) $attributes['unit_price'], 2);
        $repairJob->items()->create($attributes);

        return back()->with('status', 'Repair line item added.');
    }

    public function destroy(Request $request, RepairJobItem $repairJobItem): RedirectResponse
    {
        abort_unless($repairJobItem->repairJob->shop->owner_id === $request->user()->id, 404);
        abort_if(in_array($repairJobItem->repairJob->status, ['released', 'cancelled'], true), 409, 'Closed repair jobs cannot be changed.');
        $repairJobItem->delete();

        return back()->with('status', 'Repair line item removed.');
    }
}
