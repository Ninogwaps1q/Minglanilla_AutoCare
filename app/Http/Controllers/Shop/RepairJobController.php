<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairJobRequest;
use App\Http\Requests\UpdateRepairJobStatusRequest;
use App\Models\Appointment;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\VehicleServiceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RepairJobController extends Controller
{
    public function storeFromQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($quotation->shop_id === $this->shop($request)->id, 404);
        DB::transaction(function () use ($quotation, $request): void {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            abort_unless(in_array($quotation->status, ['accepted', 'approved'], true), 409);
            if (RepairJob::query()->where('quotation_id', $quotation->id)->exists()) {
                return;
            }
            $repairJob = RepairJob::query()->create([
                'customer_id' => $quotation->customer_id, 'shop_id' => $quotation->shop_id,
                'vehicle_id' => $quotation->vehicle_id, 'quotation_id' => $quotation->id,
                'job_number' => 'RJ-'.Str::upper(Str::random(10)), 'status' => 'approved',
                'estimated_cost' => $quotation->total, 'description' => $quotation->items->pluck('description')->implode('; '),
                'customer_notes' => $quotation->notes,
            ]);
            $repairJob->items()->createMany($quotation->items->map(fn ($item): array => [
                'type' => $item->service_id ? 'service' : 'other',
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->all());
            $repairJob->update(['actual_cost' => $repairJob->items()->sum('subtotal')]);
            $repairJob->statusHistories()->create(['to_status' => 'approved', 'changed_by' => $request->user()->id, 'changed_at' => now(), 'note' => 'Created from customer-approved estimate']);
        });

        return redirect()->route('shop.repair-jobs.index')->with('status', 'Repair job ready for the accepted quotation.');
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, ['pending', 'diagnosis', 'waiting_approval', 'approved', 'repairing', 'in_progress', 'waiting_parts', 'completed', 'released', 'cancelled'], true) ? $status : null;

        return view('shop.repair-jobs.index', [
            'repairJobs' => $this->shop($request)->repairJobs()
                ->with(['customer', 'vehicle', 'appointment', 'invoice.issuedBy', 'invoice.payments.customer', 'invoice.payments.reviewedBy', 'items', 'quotation.approvalRecords.customer', 'statusHistories.changedBy', 'technicianAssignments.technician'])
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($request->integer('appointment_id') > 0, fn ($query) => $query->where('appointment_id', $request->integer('appointment_id')))
                ->latest()
                ->paginate(10)->withQueryString(),
            'technicians' => $this->shop($request)->technicians()->where('status', 'active')->orderBy('name')->get(),
            'selectedStatus' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $appointments = $this->shop($request)->appointments()
            ->where('status', 'confirmed')
            ->whereDoesntHave('repairJob')
            ->with(['customer', 'vehicle', 'service'])
            ->orderBy('appointment_date')
            ->get();
        $requestedAppointmentId = $request->integer('appointment_id');

        return view('shop.repair-jobs.create', [
            'appointments' => $appointments,
            'selectedAppointmentId' => $appointments->contains('id', $requestedAppointmentId) ? $requestedAppointmentId : null,
        ]);
    }

    public function store(StoreRepairJobRequest $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $repairJob = DB::transaction(function () use ($request, $shop): RepairJob {
            $appointment = Appointment::query()->whereKey($request->integer('appointment_id'))
                ->where('shop_id', $shop->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()->firstOrFail();
            if ($appointment->repairJob()->exists()) {
                throw ValidationException::withMessages(['appointment_id' => 'A repair job already exists for this appointment. Open it from Shop appointments.']);
            }
            $repairJob = $shop->repairJobs()->create([
                ...$request->validated(),
                'customer_id' => $appointment->customer_id,
                'vehicle_id' => $appointment->vehicle_id,
                'job_number' => 'RJ-'.Str::upper(Str::random(10)),
                'status' => 'pending',
            ]);
            $repairJob->statusHistories()->create(['to_status' => 'pending', 'changed_by' => $request->user()->id, 'changed_at' => now()]);

            $appointment->update(['status' => 'in_progress']);

            return $repairJob;
        });

        return redirect()->route('shop.repair-jobs.index')->with('status', "Repair job {$repairJob->job_number} created.");
    }

    public function update(UpdateRepairJobStatusRequest $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $this->shop($request)->id, 404);

        DB::transaction(function () use ($repairJob, $request): void {
            $repairJob = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            $nextStatus = $request->validated('status');
            if ($nextStatus === 'waiting_approval' && ($repairJob->quotation === null || $repairJob->quotation->status !== 'sent')) {
                throw ValidationException::withMessages(['status' => 'Send an estimate to the customer before waiting for approval.']);
            }
            if ($nextStatus === 'approved' && ! in_array($repairJob->quotation?->status, ['accepted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'The customer must approve the estimate first.']);
            }
            if ($nextStatus === $repairJob->status && in_array($repairJob->status, ['completed', 'released', 'cancelled'], true)) {
                return;
            }
            if ($nextStatus !== $repairJob->status && ! array_key_exists($nextStatus, $repairJob->availableStatuses())) {
                throw ValidationException::withMessages(['status' => 'This status change is not allowed for the current repair stage.']);
            }
            if (in_array($nextStatus, ['approved', 'repairing', 'in_progress', 'completed'], true) && ! in_array($repairJob->quotation?->status, ['accepted', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'Send an estimate and wait for the customer to approve it before repair work proceeds.']);
            }
            $attributes = $request->validated();
            unset($attributes['actual_cost']);
            $previousStatus = $repairJob->status;
            $repairJob->update($attributes);
            if ($previousStatus !== $repairJob->status) {
                $repairJob->statusHistories()->create(['from_status' => $previousStatus, 'to_status' => $repairJob->status, 'changed_by' => $request->user()->id, 'changed_at' => now()]);
            }

            if (in_array($repairJob->status, ['in_progress', 'repairing'], true) && $repairJob->started_at === null) {
                $repairJob->update(['started_at' => now()]);
            }
            if ($repairJob->status === 'cancelled') {
                $repairJob->appointment?->update(['status' => 'cancelled']);
            }
            if (in_array($repairJob->status, ['completed', 'released', 'cancelled'], true)) {
                $repairJob->technicianAssignments()->whereNull('completed_at')->update(['completed_at' => now()]);
            }
            if ($previousStatus !== $repairJob->status) {
                $repairJob->customer->notifications()->create([
                    'type' => 'repair_status', 'title' => 'Repair status updated',
                    'message' => "{$repairJob->job_number}: ".str_replace('_', ' ', $repairJob->status),
                    'data' => ['repair_job_id' => $repairJob->id],
                ]);
            }

            if ($repairJob->status === 'completed') {
                if ($repairJob->actual_cost === null) {
                    $repairJob->update(['actual_cost' => $repairJob->estimated_cost ?? 0]);
                }
                if ($repairJob->completed_at === null) {
                    $repairJob->update(['completed_at' => now()]);
                }
                $repairJob->appointment?->update(['status' => 'completed']);
                VehicleServiceRecord::query()->firstOrCreate(
                    ['repair_job_id' => $repairJob->id],
                    [
                        'vehicle_id' => $repairJob->vehicle_id,
                        'shop_id' => $repairJob->shop_id,
                        'customer_id' => $repairJob->customer_id,
                        'service_date' => today(),
                        'service_type' => 'Repair job '.$repairJob->job_number,
                        'description' => $repairJob->description,
                        'labor_cost' => $repairJob->actual_cost ?? 0,
                        'total_cost' => $repairJob->actual_cost ?? 0,
                        'technician_notes' => $repairJob->technician_notes,
                    ],
                );
            }
        });

        return back()->with('status', 'Repair job updated.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
