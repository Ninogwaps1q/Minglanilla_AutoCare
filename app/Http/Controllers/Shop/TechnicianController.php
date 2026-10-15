<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\StoreTechnicianRequest;
use App\Http\Requests\UpdateTechnicianRequest;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        return view('shop.technicians.index', [
            'technicians' => $shop->technicians()->withCount('activeAssignments')->orderBy('name')->get(),
            'repairJobs' => $shop->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])->latest()->get(),
        ]);
    }

    public function store(StoreTechnicianRequest $request): RedirectResponse
    {
        $attributes = $request->safe()->except('photo');
        if ($request->hasFile('photo')) {
            $attributes['photo'] = $request->file('photo')->store('technicians', 'public');
        }

        $this->shop($request)->technicians()->create($attributes);

        return back()->with('status', 'Technician added.');
    }

    public function update(UpdateTechnicianRequest $request, Technician $technician): RedirectResponse
    {
        abort_unless($technician->shop_id === $this->shop($request)->id, 404);
        $attributes = $request->safe()->except('photo');
        if ($request->hasFile('photo')) {
            $previousPhoto = $technician->photo;
            $attributes['photo'] = $request->file('photo')->store('technicians', 'public');
            if ($previousPhoto !== null) {
                Storage::disk('public')->delete($previousPhoto);
            }
        }
        $technician->update($attributes);

        return back()->with('status', 'Technician updated.');
    }

    public function assign(AssignTechnicianRequest $request, RepairJob $repairJob): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($repairJob->shop_id === $shop->id, 404);
        abort_if(in_array($repairJob->status, ['completed', 'released', 'cancelled'], true), 409, 'This repair job is closed.');
        $technician = $shop->technicians()->whereKey($request->integer('technician_id'))->where('status', 'active')->firstOrFail();
        $repairJob->technicianAssignments()->updateOrCreate(['technician_id' => $technician->id], ['assigned_at' => now(), 'completed_at' => null, 'notes' => $request->validated('notes')]);

        return back()->with('status', "{$technician->name} assigned to {$repairJob->job_number}.");
    }

    public function unassign(Request $request, RepairJob $repairJob, Technician $technician): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $this->shop($request)->id && $technician->shop_id === $repairJob->shop_id, 404);
        $repairJob->technicianAssignments()->where('technician_id', $technician->id)->update(['completed_at' => now()]);

        return back()->with('status', 'Technician assignment closed.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
