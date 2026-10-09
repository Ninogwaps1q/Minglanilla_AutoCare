<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInspectionRequest;
use App\Models\RepairJob;
use App\Models\VehicleInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function create(Request $request, RepairJob $repairJob): View
    {
        abort_unless($repairJob->shop->owner_id === $request->user()->id, 404);

        return view('shop.inspections.create', ['repairJob' => $repairJob->load('vehicle')]);
    }

    public function store(StoreInspectionRequest $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop->owner_id === $request->user()->id, 404);

        DB::transaction(function () use ($request, $repairJob): void {
            $inspection = VehicleInspection::query()->create([
                ...$request->safe()->only(['inspection_date', 'overall_condition', 'notes']),
                'vehicle_id' => $repairJob->vehicle_id,
                'shop_id' => $repairJob->shop_id,
                'appointment_id' => $repairJob->appointment_id,
            ]);
            $inspection->items()->createMany($request->validated('items'));
        });

        return redirect()->route('shop.repair-jobs.index')->with('status', 'Vehicle inspection saved.');
    }
}
