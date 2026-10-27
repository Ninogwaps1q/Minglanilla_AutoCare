<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceScheduleRequest;
use App\Models\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceScheduleController extends Controller
{
    public function store(StoreMaintenanceScheduleRequest $request): RedirectResponse
    {
        $vehicle = $request->user()->vehicles()->whereKey($request->integer('vehicle_id'))->firstOrFail();
        $attributes = $request->safe()->except('vehicle_id');
        $lastDate = $request->date('last_service_date');
        $lastMileage = $request->filled('last_service_mileage') ? $request->integer('last_service_mileage') : null;
        $intervalDays = $request->integer('recommended_interval_days') ?: null;
        $intervalMileage = $request->integer('recommended_interval_mileage') ?: null;
        if ($intervalDays !== null && $lastDate === null) {
            throw ValidationException::withMessages(['last_service_date' => 'Enter the last service date to calculate the next date.']);
        }
        if ($intervalMileage !== null && $lastMileage === null) {
            throw ValidationException::withMessages(['last_service_mileage' => 'Enter the last mileage to calculate the next distance.']);
        }
        $attributes['next_service_date'] = $lastDate && $intervalDays ? CarbonImmutable::instance($lastDate)->addDays($intervalDays)->toDateString() : null;
        $attributes['next_service_mileage'] = $lastMileage !== null && $intervalMileage ? $lastMileage + $intervalMileage : null;
        $attributes['status'] = 'active';
        $vehicle->maintenanceSchedules()->create($attributes);

        return back()->with('status', 'Maintenance schedule created.');
    }

    public function destroy(Request $request, MaintenanceSchedule $maintenanceSchedule): RedirectResponse
    {
        abort_unless($maintenanceSchedule->vehicle->customer_id === $request->user()->id, 404);
        $maintenanceSchedule->delete();

        return back()->with('status', 'Maintenance schedule removed.');
    }
}
