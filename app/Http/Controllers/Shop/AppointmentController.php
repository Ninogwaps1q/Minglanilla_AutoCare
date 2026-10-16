<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\Shop;
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
                ->with(['customer', 'vehicle', 'service', 'repairJob'])
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
            if (! array_key_exists($nextStatus, $appointment->availableStatusTransitions())) {
                throw ValidationException::withMessages(['status' => 'This status change is not allowed for the current appointment stage.']);
            }
            $previousStatus = $appointment->status;
            $appointment->update(['status' => $nextStatus]);
            $appointment->statusHistories()->create(['from_status' => $previousStatus, 'to_status' => $nextStatus, 'changed_by' => $request->user()->id, 'changed_at' => now()]);
            $appointment->customer->notifications()->create(['type' => 'appointment_status', 'title' => 'Appointment status updated', 'message' => "Your appointment is now {$nextStatus}.", 'data' => ['appointment_id' => $appointment->id]]);
        });

        return back()->with('status', 'Appointment status updated.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
