<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Shop;
use App\Models\ShopService;
use App\Services\AppointmentSchedule;
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
                ->with(['shop', 'vehicle', 'service', 'serviceCase', 'repairJob', 'latestRescheduleRequest'])
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->paginate(10)->withQueryString(),
            'selectedStatus' => $status,
        ]);
    }

    public function availability(Request $request, AppointmentSchedule $schedule): JsonResponse
    {
        $attributes = $request->validate([
            'shop_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $shop = Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')
            ->findOrFail($attributes['shop_id']);
        $serviceId = $request->integer('service_id');
        $offer = $serviceId > 0 ? ShopService::query()->where('shop_id', $shop->id)
            ->where('service_id', $serviceId)->where('availability', true)
            ->with('service:id,estimated_duration')->first() : null;
        if ($serviceId > 0 && ! $offer?->service) {
            throw ValidationException::withMessages(['service_id' => 'Choose a service offered by this shop.']);
        }
        $intervals = $schedule->bookedIntervals($shop, $attributes['date']);

        return response()->json([
            'opening_time' => $shop->opening_time ? substr($shop->opening_time, 0, 5) : null,
            'closing_time' => $shop->closing_time ? substr($shop->closing_time, 0, 5) : null,
            'taken_times' => collect($intervals)->map(fn (array $interval): string => sprintf('%02d:%02d', intdiv($interval['start'], 60), $interval['start'] % 60))->values(),
            'booked_intervals' => $intervals,
            'duration_minutes' => (int) ($offer?->service?->estimated_duration ?: 60),
            'capacity' => (int) $shop->booking_capacity,
        ]);
    }

    public function create(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('plate_number')->get();
        $selectedVehicleId = $vehicles->contains('id', $request->integer('vehicle_id'))
            ? $request->integer('vehicle_id')
            : null;

        return view('customer.appointments.create', [
            'shops' => Shop::query()
                ->where('verification_status', 'approved')
                ->where('shop_status', 'active')
                ->with(['services.service'])
                ->orderBy('name')
                ->get(),
            'vehicles' => $vehicles,
            'selectedShopId' => $request->integer('shop_id'),
            'selectedVehicleId' => $selectedVehicleId,
        ]);
    }

    public function store(StoreAppointmentRequest $request, AppointmentSchedule $schedule): RedirectResponse
    {
        $attributes = $request->validated();
        $appointment = DB::transaction(function () use ($request, $attributes, $schedule): Appointment {
            $shop = Shop::query()->lockForUpdate()->findOrFail($attributes['shop_id']);
            if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                throw ValidationException::withMessages(['shop_id' => 'This shop is no longer accepting appointments.']);
            }
            $offer = ShopService::query()->where('shop_id', $shop->id)
                ->where('service_id', $attributes['service_id'])->where('availability', true)
                ->with('service:id,estimated_duration')->first();
            if (! $offer?->service) {
                throw ValidationException::withMessages(['service_id' => 'This service is no longer available at the shop.']);
            }
            $duration = (int) ($offer->service->estimated_duration ?: 60);
            if (now()->format('Y-m-d H:i') >= $attributes['appointment_date'].' '.$attributes['appointment_time']) {
                throw ValidationException::withMessages(['appointment_time' => 'Choose a future appointment time.']);
            }
            if (! $schedule->fitsHours($shop, $attributes['appointment_time'], $duration)) {
                throw ValidationException::withMessages(['appointment_time' => 'Choose a time that leaves enough time for this service before the shop closes.']);
            }
            if (! $schedule->hasCapacity($schedule->bookedIntervals($shop, $attributes['appointment_date']), $attributes['appointment_time'], $duration, (int) $shop->booking_capacity)) {
                throw ValidationException::withMessages([
                    'appointment_time' => 'The shop is fully booked during this service. Choose another time.',
                ]);
            }

            $attributes['duration_minutes'] = $duration;
            $attributes['service_price'] = $offer->price;
            $attributes['service_labor_included'] = $offer->labor_included;
            $attributes['service_parts_included'] = $offer->parts_included;
            $attributes['service_scope'] = $offer->description;
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

    public function requestReschedule(Appointment $appointment, Request $request, AppointmentSchedule $schedule): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 404);
        $attributes = $request->validate([
            'requested_date' => ['required', 'date', 'after_or_equal:today'],
            'requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($appointment, $schedule, $attributes): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($appointment->shop_id);
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== 'confirmed' || $appointment->repairJob()->exists()) {
                throw ValidationException::withMessages(['requested_date' => 'This appointment can no longer be rescheduled.']);
            }
            if ($appointment->rescheduleRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['requested_date' => 'Your time-change request is already waiting for the shop.']);
            }
            if ($appointment->appointment_date->toDateString() === $attributes['requested_date']
                && $appointment->appointment_time->format('H:i') === $attributes['requested_time']) {
                throw ValidationException::withMessages(['requested_time' => 'Choose a different appointment date or time.']);
            }
            $schedule->assertRescheduleAvailable($shop, $appointment, $attributes['requested_date'], $attributes['requested_time']);

            $appointment->rescheduleRequests()->create([
                'original_date' => $appointment->appointment_date->toDateString(),
                'original_time' => $appointment->appointment_time->format('H:i'),
                'requested_date' => $attributes['requested_date'],
                'requested_time' => $attributes['requested_time'],
                'reason' => $attributes['reason'] ?? null,
                'status' => 'pending',
            ]);
            $shop->owner->notifications()->create([
                'type' => 'appointment_reschedule_requested',
                'title' => 'Appointment time change requested',
                'message' => 'Appointment #'.$appointment->id.' has a new time request to review.',
                'data' => ['appointment_id' => $appointment->id],
            ]);
        });

        return back()->with('status', 'Your time-change request was sent. The original time remains confirmed until the shop approves it.');
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
