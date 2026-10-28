<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\StoreTechnicianRequest;
use App\Http\Requests\UpdateTechnicianRequest;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\Technician;
use App\Services\AppointmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        return view('shop.technicians.index', [
            'technicians' => $shop->technicians()->withCount('activeAssignments')
                ->with('activeAssignments.repairJob.appointment')->orderBy('name')->get(),
            'repairJobs' => $shop->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])
                ->with('appointment')->latest()->get(),
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
        $previousPhoto = null;
        if ($request->hasFile('photo')) {
            $previousPhoto = $technician->photo;
            $attributes['photo'] = $request->file('photo')->store('technicians', 'public');
        }
        $technician->update($attributes);
        if ($previousPhoto !== null) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return back()->with('status', 'Technician updated.');
    }

    public function assign(AssignTechnicianRequest $request, RepairJob $repairJob, AppointmentSchedule $schedule): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($repairJob->shop_id === $shop->id, 404);
        abort_if(in_array($repairJob->status, ['completed', 'released', 'cancelled'], true), 409, 'This repair job is closed.');
        $technician = DB::transaction(function () use ($shop, $request, $repairJob, $schedule): Technician {
            $technician = $shop->technicians()->whereKey($request->integer('technician_id'))->where('status', 'active')->lockForUpdate()->firstOrFail();
            $alreadyAssigned = $repairJob->technicianAssignments()->where('technician_id', $technician->id)->whereNull('completed_at')->exists();
            if (! $alreadyAssigned && $technician->activeAssignments()->count() >= $technician->max_active_jobs) {
                throw ValidationException::withMessages(['technician_id' => "{$technician->name} has reached their active job limit. Choose another technician or adjust the team's capacity."]);
            }
            $appointment = $repairJob->appointment;
            if (! $alreadyAssigned && $appointment && $appointment->appointment_date->toDateString() >= today()->toDateString()) {
                $duration = (int) ($appointment->duration_minutes ?: $appointment->service?->estimated_duration ?: 60);
                $otherAssignments = $technician->activeAssignments()->where('repair_job_id', '!=', $repairJob->id)
                    ->with('repairJob.appointment.service')->get();
                foreach ($otherAssignments as $assignment) {
                    $other = $assignment->repairJob?->appointment;
                    if (! $other || $other->appointment_date->toDateString() !== $appointment->appointment_date->toDateString()) {
                        continue;
                    }
                    $start = ((int) $other->appointment_time->format('H')) * 60 + (int) $other->appointment_time->format('i');
                    $otherDuration = (int) ($other->duration_minutes ?: $other->service?->estimated_duration ?: 60);
                    if (! $schedule->hasCapacity([
                        ['start' => $start, 'end' => $start + $otherDuration],
                    ], $appointment->appointment_time->format('H:i'), $duration, 1)) {
                        throw ValidationException::withMessages([
                            'technician_id' => "{$technician->name} is already assigned to another repair during this appointment. Choose another technician or a different visit time.",
                        ]);
                    }
                }
            }
            $repairJob->technicianAssignments()->updateOrCreate(['technician_id' => $technician->id], ['assigned_at' => now(), 'completed_at' => null, 'notes' => $request->validated('notes')]);

            return $technician;
        });

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
