<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Barangay;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppointmentSchedule;
use App\Services\BookingPromotion;
use App\Services\MechanicBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'], true) ? $status : null;

        return view('customer.appointments.index', [
            'appointments' => $request->user()->appointments()
                ->with(['shop', 'vehicle', 'service', 'bookedServices.service', 'serviceCase', 'repairJob', 'latestRescheduleRequest'])
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
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today(Appointment::TIMEZONE)->toDateString()],
            'service_ids' => ['nullable', 'array', 'max:10'],
            'service_ids.*' => ['integer', 'distinct'],
        ]);
        $shop = Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')
            ->whereHas('owner', fn ($query) => $query->where('status', 'active'))
            ->withAppointmentBookings()
            ->findOrFail($attributes['shop_id']);
        $serviceIds = $attributes['service_ids'] ?? array_filter([$request->integer('service_id')]);
        $offers = ShopService::query()->where('shop_id', $shop->id)
            ->whereIn('service_id', $serviceIds)->where('availability', true)
            ->with('service:id,estimated_duration')->get();
        if ($offers->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['service_ids' => 'Choose services offered by this provider.']);
        }
        $intervals = $schedule->bookedIntervals($shop, $attributes['date']);

        return response()->json([
            'opening_time' => $shop->opening_time ? substr($shop->opening_time, 0, 5) : null,
            'closing_time' => $shop->closing_time ? substr($shop->closing_time, 0, 5) : null,
            'is_open_day' => $shop->operating_days === null || in_array(Carbon::parse($attributes['date'])->dayOfWeekIso, array_map('intval', $shop->operating_days), true),
            'schedule_blocks' => $schedule->blocksFor($shop, $attributes['date']),
            'taken_times' => collect($intervals)->map(fn (array $interval): string => sprintf('%02d:%02d', intdiv($interval['start'], 60), $interval['start'] % 60))->values(),
            'booked_intervals' => $intervals,
            'duration_minutes' => $schedule->duration($shop, $offers->isEmpty() ? 60 : $offers->sum(fn (ShopService $offer): int => (int) ($offer->service?->estimated_duration ?: 60))),
            'capacity' => $shop->appointmentCapacity(),
        ]);
    }

    public function create(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->orderBy('plate_number')->get();
        $selectedVehicleId = $request->integer('vehicle_id') ?: $request->user()->appointments()->latest()->value('vehicle_id');
        if (! $vehicles->contains('id', $selectedVehicleId)) {
            $selectedVehicleId = $vehicles->count() === 1 ? $vehicles->first()->id : null;
        }

        return view('customer.appointments.create', [
            'shops' => Shop::query()
                ->where('verification_status', 'approved')
                ->where('shop_status', 'active')
                ->whereHas('owner', fn ($query) => $query->where('status', 'active'))
                ->withAppointmentBookings()
                ->with(['services' => fn ($query) => $query->where('availability', true), 'services.service', 'barangay.municipality.province.region'])
                ->orderBy('name')
                ->get(),
            'vehicles' => $vehicles,
            'newVehicle' => new Vehicle,
            'selectedShopId' => $request->integer('shop_id'),
            'selectedVehicleId' => $selectedVehicleId,
            'bookingDraft' => (array) $request->old('booking_draft', []),
        ]);
    }

    public function quote(StoreAppointmentRequest $request, MechanicBooking $booking): JsonResponse
    {
        $attributes = $request->validated();
        $shop = Shop::query()->publicListing()->findOrFail($attributes['shop_id']);
        abort_unless($shop->isIndependentMechanic() && $shop->acceptsNewBookings(), 404);
        $booking->checkNewBooking($shop);
        $barangay = Barangay::query()->with('municipality')->findOrFail($attributes['service_barangay_id']);
        $serviceCents = $shop->services()->where('availability', true)
            ->whereIn('service_id', $attributes['service_ids'] ?? [$attributes['service_id']])
            ->get(['price'])->sum(fn (ShopService $offer): int => (int) round((float) $offer->price * 100));

        return response()->json($booking->quote($shop, $barangay, $attributes, $serviceCents));
    }

    public function store(StoreAppointmentRequest $request, AppointmentSchedule $schedule, BookingPromotion $promotions, MechanicBooking $booking): RedirectResponse
    {
        $attributes = $request->validated();
        $serviceIds = $attributes['service_ids'] ?? [$attributes['service_id']];
        $visit = array_intersect_key($attributes, array_flip(['latitude', 'longitude', 'mobile_quote_total']));
        unset($attributes['service_ids'], $attributes['promo_code'], $attributes['latitude'], $attributes['longitude'], $attributes['mobile_quote_total']);
        $appointment = DB::transaction(function () use ($request, $attributes, $serviceIds, $schedule, $promotions, $booking, $visit): Appointment {
            $shop = Shop::query()->lockForUpdate()->findOrFail($attributes['shop_id']);
            $customer = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($customer->vehicles()->lockForUpdate()->find($attributes['vehicle_id']), 404);
            if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active' || $shop->owner->status !== 'active') {
                throw ValidationException::withMessages(['shop_id' => 'This shop is no longer accepting appointments.']);
            }
            if (! $shop->acceptsNewBookings()) {
                throw ValidationException::withMessages(['shop_id' => 'This shop is listed for discovery but is not accepting online bookings right now.']);
            }
            if ($shop->isIndependentMechanic() && (blank($attributes['service_street'] ?? null) || empty($attributes['service_barangay_id']))) {
                throw ValidationException::withMessages(['service_street' => 'Enter the full visit address for this mobile service.']);
            }
            if ($shop->isIndependentMechanic()) {
                $booking->checkNewBooking($shop);
                $barangay = Barangay::query()->with('municipality.province.region')->findOrFail($attributes['service_barangay_id']);
                $attributes['service_address'] = Str::limit($attributes['service_street'].', '.$barangay->areaLabel(), 500, '');
            } else {
                unset($attributes['service_barangay_id'], $attributes['service_street'], $attributes['service_landmark']);
            }
            $offers = ShopService::query()->where('shop_id', $shop->id)
                ->whereIn('service_id', $serviceIds)->where('availability', true)
                ->with('service:id,estimated_duration')->get()->keyBy('service_id');
            if ($offers->count() !== count($serviceIds) || $offers->contains(fn (ShopService $offer): bool => ! $offer->service)) {
                throw ValidationException::withMessages(['service_ids' => 'A selected service is no longer available at this provider.']);
            }
            $serviceMinutes = collect($serviceIds)->sum(fn (int $id): int => (int) ($offers[$id]->service->estimated_duration ?: 60));
            $duration = $schedule->duration($shop, $serviceMinutes);
            if (! $schedule->isUpcoming($attributes['appointment_date'], $attributes['appointment_time'])) {
                throw ValidationException::withMessages(['appointment_time' => 'Choose a future appointment time.']);
            }
            if (! $schedule->isAvailable($shop, $attributes['appointment_date'], $attributes['appointment_time'], $duration)) {
                throw ValidationException::withMessages(['appointment_time' => 'The shop is closed or this time is blocked. Choose another slot.']);
            }
            if (! $schedule->hasCapacity($schedule->bookedIntervals($shop, $attributes['appointment_date']), $attributes['appointment_time'], $duration, $shop->appointmentCapacity())) {
                throw ValidationException::withMessages([
                    'appointment_time' => 'The shop is fully booked during this service. Choose another time.',
                ]);
            }

            $primary = $offers[$serviceIds[0]];
            $attributes['service_id'] = $primary->service_id;
            $attributes['duration_minutes'] = $serviceMinutes;
            $serviceCents = $offers->sum(fn (ShopService $offer): int => (int) round((float) $offer->price * 100));
            $attributes['service_price'] = number_format($serviceCents / 100, 2, '.', '');
            if (isset($barangay)) {
                $quote = $booking->quote($shop, $barangay, $visit, $serviceCents);
                if (($quote['total'] !== $serviceCents || isset($visit['mobile_quote_total'])) && (int) ($visit['mobile_quote_total'] ?? -1) !== $quote['total']) {
                    throw ValidationException::withMessages(['mobile_quote_total' => __('mechanic.review_quote')]);
                }
                $attributes['mobile_service_quote'] = $quote;
            }
            $attributes['service_labor_included'] = $primary->labor_included;
            $attributes['service_parts_included'] = $primary->parts_included;
            $attributes['service_scope'] = $primary->description;
            $attributes['cancellation_notice_hours'] = $shop->cancellation_notice_hours;
            $attributes += $promotions->forBooking($shop, $offers, $request->user()->id, $request->validated('promo_code'));
            $appointment = $customer->appointments()->create($attributes);
            foreach ($serviceIds as $index => $serviceId) {
                $offer = $offers[$serviceId];
                $appointment->bookedServices()->create([
                    'service_id' => $serviceId,
                    'position' => $index + 1,
                    'price' => $offer->price,
                    'duration_minutes' => $offer->service->estimated_duration ?: 60,
                    'labor_included' => $offer->labor_included,
                    'parts_included' => $offer->parts_included,
                    'scope' => $offer->description,
                ]);
            }
            $shop->owner->notifications()->create([
                'type' => 'appointment_requested',
                'title' => 'New appointment request',
                'message' => "Appointment #{$appointment->id} is awaiting your confirmation.",
                'data' => ['appointment_id' => $appointment->id, 'mechanic_update' => $shop->isIndependentMechanic()],
            ]);

            return $appointment;
        });

        return redirect()->route('customer.appointments.index')->with('status', "Appointment #{$appointment->id} requested successfully.");
    }

    public function requestReschedule(Appointment $appointment, Request $request, AppointmentSchedule $schedule): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 404);
        $attributes = $request->validate([
            'requested_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today(Appointment::TIMEZONE)->toDateString()],
            'requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($appointment, $schedule, $attributes, $request): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($appointment->shop_id);
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== 'confirmed' || $appointment->en_route_at || $appointment->repairJob()->exists()) {
                throw ValidationException::withMessages(['requested_date' => 'This appointment can no longer be rescheduled.']);
            }
            $this->validateLateChange($request, $appointment);
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
        abort_unless($appointment->customer_id === $request->user()->id, 404);

        DB::transaction(function () use ($appointment, $request): void {
            Shop::query()->lockForUpdate()->findOrFail($appointment->shop_id);
            $appointment = $request->user()->appointments()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless(in_array($appointment->status, ['pending', 'confirmed'], true) && ! $appointment->repairJob()->exists(), 404);
            $this->validateLateChange($request, $appointment);
            $previousStatus = $appointment->status;
            $appointment->update(['status' => 'cancelled']);
            $appointment->rescheduleRequests()->where('status', 'pending')->update([
                'status' => 'declined', 'decided_by' => $request->user()->id, 'decided_at' => now(),
            ]);
            if ($case = $appointment->serviceCase) {
                $case->update(['status' => 'reviewing', 'scheduled_at' => null, 'schedule_status' => 'cancelled', 'appointment_id' => null]);
                $case->events()->create(['actor_id' => $request->user()->id, 'from_status' => 'scheduled',
                    'to_status' => 'reviewing', 'note' => __('extensions.appointments.cancelled_by_customer')]);
            }
            $appointment->statusHistories()->create([
                'from_status' => $previousStatus,
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

    public function decideProposal(Request $request, Appointment $appointment, AppointmentSchedule $schedule): RedirectResponse
    {
        abort_unless($appointment->customer_id === $request->user()->id, 404);
        $approved = $request->validate(['decision' => ['required', 'in:approve,decline']])['decision'] === 'approve';
        DB::transaction(function () use ($appointment, $request, $schedule, $approved): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($appointment->shop_id);
            $appointment = $request->user()->appointments()->lockForUpdate()->findOrFail($appointment->id);
            $proposal = $appointment->rescheduleRequests()->where('status', 'pending')->where('proposed_by_provider', true)->lockForUpdate()->first();
            abort_unless($shop->isIndependentMechanic() && $proposal && in_array($appointment->status, ['pending', 'confirmed'], true) && ! $appointment->en_route_at && ! $appointment->repairJob()->exists(), 404);
            if ($approved) {
                $date = $proposal->requested_date->toDateString();
                $time = $proposal->requested_time->format('H:i');
                $schedule->assertRescheduleAvailable($shop, $appointment, $date, $time);
                $oldStatus = $appointment->status;
                $appointment->update(['appointment_date' => $date, 'appointment_time' => $time, 'status' => 'confirmed']);
                if ($oldStatus !== 'confirmed') {
                    $appointment->statusHistories()->create([
                        'from_status' => $oldStatus, 'to_status' => 'confirmed', 'changed_by' => $request->user()->id, 'changed_at' => now(),
                    ]);
                }
                $appointment->serviceCase?->update(['scheduled_at' => $date.' '.$time]);
            }
            $proposal->update(['status' => $approved ? 'approved' : 'declined', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $shop->owner->notifications()->create([
                'type' => 'appointment_reschedule_decided', 'title' => __('mechanic.proposal_answered'),
                'message' => __($approved ? 'mechanic.proposal_accepted' : 'mechanic.proposal_declined'), 'data' => ['appointment_id' => $appointment->id],
            ]);
        });

        return back()->with('status', __($approved ? 'mechanic.proposal_accepted' : 'mechanic.proposal_declined'));
    }

    private function validateLateChange(Request $request, Appointment $appointment): void
    {
        if ($appointment->needsLateChangeAcknowledgement()) {
            $request->validate(['acknowledge_late_change' => ['accepted']], ['acknowledge_late_change.accepted' => __('extensions.appointments.acknowledge_late')]);
        }
    }
}
