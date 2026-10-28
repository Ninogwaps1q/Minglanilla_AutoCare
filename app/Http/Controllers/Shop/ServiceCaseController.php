<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\Shop;
use App\Services\AppointmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCaseController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->shops()->firstOrFail();

        return view('shop.service-cases.index', [
            'cases' => ServiceCase::query()->where('shop_id', $shop->id)
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['customer', 'repairJob.vehicle', 'appointment', 'events.actor'])->latest()->paginate(12)->withQueryString(),
        ]);
    }

    public function update(Request $request, ServiceCase $serviceCase, AppointmentSchedule $schedule): RedirectResponse
    {
        abort_unless($serviceCase->shop_id === $request->user()->shops()->firstOrFail()->id, 404);
        $data = $request->validate([
            'shop_response' => ['required', 'string', 'min:5', 'max:3000'],
            'status' => ['nullable', Rule::in(['reviewing', 'scheduled', 'resolved', 'declined'])],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:status,scheduled'],
        ]);

        DB::transaction(function () use ($serviceCase, $request, $data, $schedule): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($serviceCase->shop_id);
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($serviceCase->id);
            if (in_array($case->status, ['resolved', 'declined', 'dismissed'], true)) {
                throw ValidationException::withMessages(['status' => 'This request is closed. Open a new case if more help is needed.']);
            }
            $nextStatus = $data['status'] ?? ($case->type === 'complaint' ? 'reviewing' : $case->status);
            if ($case->type === 'complaint' && $nextStatus !== 'reviewing') {
                throw ValidationException::withMessages(['status' => 'The admin team resolves complaints. You can respond and mark this case as reviewing.']);
            }
            if (in_array($case->appointment?->status, ['confirmed', 'in_progress'], true)) {
                throw ValidationException::withMessages(['status' => 'Finish or cancel the confirmed follow-up appointment before changing this case. Use Appointments for a time change.']);
            }
            if ($case->appointment?->status === 'completed' && $nextStatus === 'scheduled') {
                throw ValidationException::withMessages(['status' => 'The follow-up visit is complete. Respond to the customer and resolve the case, or ask them to open a new request if another visit is needed.']);
            }
            $scheduledAt = null;
            if ($nextStatus === 'scheduled') {
                if ($shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                    throw ValidationException::withMessages(['scheduled_at' => 'This shop is not accepting visits.']);
                }
                $scheduledAt = Carbon::parse($data['scheduled_at']);
                $date = $scheduledAt->toDateString();
                $time = $scheduledAt->format('H:i');
                $intervals = $schedule->bookedIntervals($shop, $date, null, $case->id);
                if (! $schedule->fitsHours($shop, $time, 60)
                    || ! $schedule->hasCapacity($intervals, $time, 60, (int) $shop->booking_capacity)) {
                    throw ValidationException::withMessages(['scheduled_at' => 'This follow-up time is unavailable. Choose another one-hour slot within shop hours.']);
                }
            }
            $previousStatus = $case->status;
            $case->update(['shop_response' => $data['shop_response'], 'status' => $nextStatus,
                'scheduled_at' => $scheduledAt, 'schedule_status' => $scheduledAt ? 'pending' : null,
                'resolved_at' => in_array($nextStatus, ['resolved', 'declined'], true) ? now() : null,
            ]);
            $case->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => $previousStatus,
                'to_status' => $nextStatus, 'note' => $data['shop_response'],
            ]);
            $case->customer->notifications()->create([
                'type' => 'service_case', 'title' => 'Update on your aftercare request',
                'message' => $scheduledAt ? $case->subject.': confirm your proposed visit on '.$scheduledAt->format('M j, Y g:i A') : $case->subject.': '.str_replace('_', ' ', $nextStatus),
                'data' => ['service_case_id' => $case->id],
            ]);
        });

        return back()->with('status', 'Response saved and customer notified.');
    }
}
