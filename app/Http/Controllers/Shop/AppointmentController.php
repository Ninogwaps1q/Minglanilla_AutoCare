<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\Shop;
use App\Services\AppointmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);
        $status = $request->query('status');
        $status = in_array($status, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true) ? $status : null;

        return view('shop.appointments.index', [
            'appointments' => $shop->appointments()
                ->with(['customer', 'vehicle', 'service', 'serviceCase', 'repairJob', 'latestRescheduleRequest'])
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->paginate(10)->withQueryString(),
            'selectedStatus' => $status,
        ]);
    }

    public function update(UpdateAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->shop_id === $this->shop($request)->id, 404);

        DB::transaction(function () use ($appointment, $request): void {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $nextStatus = $request->validated('status');
            if ($nextStatus === 'confirmed' && ($appointment->shop->verification_status !== 'approved' || $appointment->shop->shop_status !== 'active')) {
                throw ValidationException::withMessages(['status' => 'This shop must be approved and active before confirming new appointments.']);
            }
            if (! array_key_exists($nextStatus, $appointment->availableStatusTransitions())) {
                throw ValidationException::withMessages(['status' => 'This status change is not allowed for the current appointment stage.']);
            }
            $previousStatus = $appointment->status;
            $appointment->update(['status' => $nextStatus]);
            if ($nextStatus === 'cancelled' && $appointment->serviceCase) {
                $case = $appointment->serviceCase;
                $case->update(['status' => 'reviewing', 'scheduled_at' => null, 'schedule_status' => 'cancelled', 'appointment_id' => null]);
                $case->events()->create([
                    'actor_id' => $request->user()->id, 'from_status' => 'scheduled',
                    'to_status' => 'reviewing', 'note' => 'Follow-up appointment cancelled. A new visit time is needed.',
                ]);
            }
            if ($previousStatus === 'confirmed' && $nextStatus !== 'confirmed') {
                $appointment->rescheduleRequests()->where('status', 'pending')->update([
                    'status' => 'declined', 'decided_by' => $request->user()->id, 'decided_at' => now(),
                ]);
            }
            $appointment->statusHistories()->create(['from_status' => $previousStatus, 'to_status' => $nextStatus, 'changed_by' => $request->user()->id, 'changed_at' => now()]);
            $appointment->customer->notifications()->create(['type' => 'appointment_status', 'title' => 'Appointment status updated', 'message' => "Your appointment is now {$nextStatus}.", 'data' => ['appointment_id' => $appointment->id]]);
        });

        return back()->with('status', 'Appointment status updated.');
    }

    public function decideReschedule(Request $request, Appointment $appointment, AppointmentSchedule $schedule): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($appointment->shop_id === $shop->id, 404);
        $attributes = $request->validate(['decision' => ['required', 'in:approve,decline']]);

        DB::transaction(function () use ($shop, $appointment, $request, $schedule, $attributes): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $reschedule = $appointment->rescheduleRequests()->where('status', 'pending')->lockForUpdate()->first();
            if (! $reschedule || $appointment->status !== 'confirmed' || $appointment->repairJob()->exists()) {
                throw ValidationException::withMessages(['decision' => 'There is no active time-change request for this appointment.']);
            }
            if ($attributes['decision'] === 'approve') {
                $date = $reschedule->requested_date->toDateString();
                $time = $reschedule->requested_time->format('H:i');
                $schedule->assertRescheduleAvailable($shop, $appointment, $date, $time);
                $appointment->update(['appointment_date' => $date, 'appointment_time' => $time]);
                if ($appointment->serviceCase) {
                    $appointment->serviceCase->update(['scheduled_at' => $date.' '.$time]);
                    $appointment->serviceCase->events()->create([
                        'actor_id' => $request->user()->id, 'from_status' => 'scheduled',
                        'to_status' => 'scheduled', 'note' => 'Follow-up appointment moved to '.$date.' '.$time.'.',
                    ]);
                }
            }
            $reschedule->update([
                'status' => $attributes['decision'] === 'approve' ? 'approved' : 'declined',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            $appointment->customer->notifications()->create([
                'type' => 'appointment_reschedule_decided',
                'title' => $attributes['decision'] === 'approve' ? 'New appointment time approved' : 'Time change declined',
                'message' => $attributes['decision'] === 'approve'
                    ? 'Appointment #'.$appointment->id.' has been moved to the requested time.'
                    : 'Appointment #'.$appointment->id.' keeps its original confirmed time.',
                'data' => ['appointment_id' => $appointment->id],
            ]);
        });

        return back()->with('status', $attributes['decision'] === 'approve' ? 'New appointment time approved.' : 'Time change declined; original appointment kept.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
