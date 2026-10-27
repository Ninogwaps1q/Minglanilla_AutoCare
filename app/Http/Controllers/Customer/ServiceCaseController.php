<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use App\Models\ServiceCase;
use App\Models\Shop;
use App\Services\AppointmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCaseController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.service-cases.index', [
            'cases' => ServiceCase::query()->where('customer_id', $request->user()->id)
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['repairJob.vehicle', 'shop', 'appointment', 'events.actor'])->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->customer_id === $request->user()->id, 404);
        $data = $request->validate([
            'type' => ['required', Rule::in(['follow_up', 'warranty_claim', 'complaint'])],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        DB::transaction(function () use ($repairJob, $request, $data): void {
            $job = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            if ($data['type'] !== 'complaint' && ! in_array($job->status, ['completed', 'released'], true)) {
                throw ValidationException::withMessages(['repair' => 'Follow-up and warranty requests become available after the repair is completed. You can report a concern at any stage.']);
            }
            if ($data['type'] === 'warranty_claim' && (! $job->warranty || $job->warranty->valid_until->isBefore(today()))) {
                throw ValidationException::withMessages(['type' => 'This repair has no active warranty. Choose a follow-up request or complaint instead.']);
            }
            if ($job->serviceCases()->where('type', $data['type'])->whereNotIn('status', ['resolved', 'declined', 'dismissed'])->exists()) {
                throw ValidationException::withMessages(['type' => 'An open request of this type already exists for this repair.']);
            }

            $case = $job->serviceCases()->create([
                ...$data,
                'customer_id' => $request->user()->id,
                'shop_id' => $job->shop_id,
                'status' => 'open',
            ]);
            $case->events()->create(['actor_id' => $request->user()->id, 'to_status' => 'open', 'note' => 'Request submitted']);
            $job->shop->owner->notifications()->create([
                'type' => 'service_case', 'title' => 'New customer aftercare request',
                'message' => $job->job_number.': '.$case->subject,
                'data' => ['service_case_id' => $case->id],
            ]);
        });

        return to_route('customer.service-cases.index')->with('status', 'Your request was sent. Track its progress here.');
    }

    public function decideSchedule(Request $request, ServiceCase $serviceCase, AppointmentSchedule $schedule): RedirectResponse
    {
        abort_unless($serviceCase->customer_id === $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['confirm', 'decline'])]]);

        DB::transaction(function () use ($serviceCase, $request, $data, $schedule): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($serviceCase->shop_id);
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($serviceCase->id);
            if ($case->status !== 'scheduled' || $case->schedule_status !== 'pending' || $case->scheduled_at === null) {
                throw ValidationException::withMessages(['decision' => 'This visit proposal is no longer awaiting your answer.']);
            }
            if ($data['decision'] === 'confirm') {
                $date = $case->scheduled_at->toDateString();
                $time = $case->scheduled_at->format('H:i');
                if ($case->scheduled_at->isPast() || $shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
                    throw ValidationException::withMessages(['decision' => 'This visit time is no longer available. Ask the shop for a new proposal.']);
                }
                $intervals = $schedule->bookedIntervals($shop, $date, null, $case->id);
                if (! $schedule->fitsHours($shop, $time, 60)
                    || ! $schedule->hasCapacity($intervals, $time, 60, (int) $shop->booking_capacity)) {
                    throw ValidationException::withMessages(['decision' => 'This time has filled up. Ask the shop for a new visit time.']);
                }
                $appointment = $shop->appointments()->create([
                    'customer_id' => $case->customer_id,
                    'vehicle_id' => $case->repairJob->vehicle_id,
                    'service_id' => null,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'duration_minutes' => 60,
                    'reason' => 'Follow-up for '.$case->repairJob->job_number.': '.$case->subject,
                    'status' => 'confirmed',
                ]);
                $appointment->statusHistories()->create([
                    'from_status' => null, 'to_status' => 'confirmed',
                    'changed_by' => $request->user()->id, 'changed_at' => now(),
                ]);
                $case->update(['schedule_status' => 'confirmed', 'appointment_id' => $appointment->id]);
                $case->events()->create(['actor_id' => $request->user()->id, 'from_status' => 'scheduled', 'to_status' => 'scheduled', 'note' => 'Customer confirmed the follow-up visit.']);
            } else {
                $case->update(['status' => 'reviewing', 'scheduled_at' => null, 'schedule_status' => 'declined']);
                $case->events()->create(['actor_id' => $request->user()->id, 'from_status' => 'scheduled', 'to_status' => 'reviewing', 'note' => 'Customer declined the proposed visit time.']);
            }
            $shop->owner->notifications()->create([
                'type' => 'service_case', 'title' => 'Follow-up visit response',
                'message' => $case->subject.': customer '.($data['decision'] === 'confirm' ? 'confirmed' : 'declined').' the proposed visit.',
                'data' => ['service_case_id' => $case->id],
            ]);
        });

        return back()->with('status', $data['decision'] === 'confirm' ? 'Visit confirmed and added to My appointments. The shop has been notified.' : 'Visit declined. Ask the shop for another time.');
    }
}
