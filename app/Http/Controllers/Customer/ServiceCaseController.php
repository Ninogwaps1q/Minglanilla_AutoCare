<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use App\Models\ServiceCase;
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
                ->with(['repairJob.vehicle', 'shop', 'events.actor'])->latest()->paginate(10)->withQueryString(),
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
            if (! in_array($job->status, ['completed', 'released'], true)) {
                throw ValidationException::withMessages(['repair' => 'Follow-up requests and complaints become available after the repair is completed.']);
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
}
