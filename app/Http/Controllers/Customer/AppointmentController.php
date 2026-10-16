<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true) ? $status : null;

        return view('customer.appointments.index', [
            'appointments' => $request->user()->appointments()
                ->with(['shop', 'vehicle', 'service', 'repairJob'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->paginate(10)->withQueryString(),
            'selectedStatus' => $status,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'shop_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $shop = Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')
            ->findOrFail($attributes['shop_id']);

        return response()->json([
            'opening_time' => $shop->opening_time ? substr($shop->opening_time, 0, 5) : null,
            'closing_time' => $shop->closing_time ? substr($shop->closing_time, 0, 5) : null,
            'taken_times' => $shop->appointments()->whereDate('appointment_date', $attributes['date'])
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->get(['appointment_time'])->map(fn (Appointment $appointment): string => $appointment->appointment_time->format('H:i'))->values(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('customer.appointments.create', [
            'shops' => Shop::query()
                ->where('verification_status', 'approved')
                ->where('shop_status', 'active')
                ->with(['services.service'])
                ->orderBy('name')
                ->get(),
            'vehicles' => $request->user()->vehicles()->orderBy('plate_number')->get(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $appointment = DB::transaction(function () use ($request, $attributes): Appointment {
            $shop = Shop::query()->lockForUpdate()->findOrFail($attributes['shop_id']);
            if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                throw ValidationException::withMessages(['shop_id' => 'This shop is no longer accepting appointments.']);
            }
            $conflicts = Appointment::query()
                ->where('shop_id', $attributes['shop_id'])
                ->whereDate('appointment_date', $attributes['appointment_date'])
                ->where('appointment_time', $attributes['appointment_time'])
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->lockForUpdate()
                ->exists();

            if ($conflicts) {
                throw ValidationException::withMessages([
                    'appointment_time' => 'This shop already has an active appointment at the selected time. Please choose another time.',
                ]);
            }

            $appointment = $request->user()->appointments()->create($attributes);
            $shop->owner->notifications()->create([
                'type' => 'appointment_requested',
                'title' => 'New appointment request',
                'message' => "Appointment #{$appointment->id} is awaiting your confirmation.",
                'data' => ['appointment_id' => $appointment->id],
            ]);

            return $appointment;
        });

        return redirect()->route('customer.appointments.index')->with('status', "Appointment #{$appointment->id} requested successfully.");
    }

    public function destroy(Appointment $appointment, Request $request): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id && $appointment->status === 'pending', 404);

        DB::transaction(function () use ($appointment, $request): void {
            $appointment = $request->user()->appointments()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($appointment->status === 'pending', 404);
            $appointment->update(['status' => 'cancelled']);
            $appointment->statusHistories()->create([
                'from_status' => 'pending',
                'to_status' => 'cancelled',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
            $appointment->shop->owner->notifications()->create([
                'type' => 'appointment_cancelled',
                'title' => 'Appointment cancelled',
                'message' => "Appointment #{$appointment->id} was cancelled by the customer.",
                'data' => ['appointment_id' => $appointment->id],
            ]);
        });

        return back()->with('status', 'Appointment cancelled.');
    }
}
