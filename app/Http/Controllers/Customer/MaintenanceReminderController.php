<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceReminderRequest;
use App\Models\MaintenanceReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceReminderController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.reminders.index', [
            'reminders' => $request->user()->maintenanceReminders()->with('vehicle')->latest('reminder_date')->get(),
            'vehicles' => $request->user()->vehicles()->orderBy('plate_number')->get(),
        ]);
    }

    public function store(StoreMaintenanceReminderRequest $request): RedirectResponse
    {
        $vehicle = $request->user()->vehicles()->whereKey($request->integer('vehicle_id'))->firstOrFail();
        $reminder = $request->user()->maintenanceReminders()->create([
            ...$request->validated(),
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
        ]);
        $request->user()->notifications()->create([
            'type' => 'maintenance_reminder',
            'title' => 'Maintenance reminder created',
            'message' => "{$reminder->title} is scheduled for {$reminder->reminder_date->format('M j, Y')}.",
            'data' => ['reminder_id' => $reminder->id],
        ]);

        return back()->with('status', 'Maintenance reminder created.');
    }

    public function update(Request $request, MaintenanceReminder $reminder): RedirectResponse
    {
        abort_unless($reminder->customer_id === $request->user()->id, 404);
        $attributes = $request->validate(['status' => ['required', 'in:pending,completed,dismissed']]);
        $reminder->update($attributes);

        return back()->with('status', 'Reminder updated.');
    }
}
