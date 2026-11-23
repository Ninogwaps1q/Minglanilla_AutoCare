<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInspectionRequest;
use App\Models\RepairJob;
use App\Models\VehicleInspection;
use App\Services\InspectionChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function create(Request $request, RepairJob $repairJob, InspectionChecklist $checklist): View
    {
        abort_unless($request->user()->canAccessShop($repairJob->shop_id), 404);

        return view('shop.inspections.create', [
            'repairJob' => $repairJob->load('vehicle'),
            'checklist' => $checklist->forRepairJob($repairJob),
        ]);
    }

    public function store(StoreInspectionRequest $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($request->user()->canAccessShop($repairJob->shop_id), 404);

        DB::transaction(function () use ($request, $repairJob): void {
            $inspection = VehicleInspection::query()->create([
                ...$request->safe()->only(['inspection_date', 'overall_condition', 'notes']),
                'vehicle_id' => $repairJob->vehicle_id,
                'shop_id' => $repairJob->shop_id,
                'appointment_id' => $repairJob->appointment_id,
                'repair_job_id' => $repairJob->id,
            ]);
            $inspection->items()->createMany(collect($request->validated('items'))->map(fn (array $item): array => [
                'category' => $item['category'],
                'item_name' => $item['item_name'],
                'condition' => ! empty($item['has_problem']) ? 'Needs Attention' : ($item['condition'] ?? 'Good'),
                'recommendation' => $item['recommendation'] ?? null,
            ])->all());
        });

        return redirect()->route('shop.repair-jobs.index')->with('status', 'Vehicle inspection saved.');
    }
}
