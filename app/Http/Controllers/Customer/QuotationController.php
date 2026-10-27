<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestQuotationRequest;
use App\Http\Requests\RespondToQuotationRequest;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Services\RepairInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function create(Request $request): View
    {
        return view('customer.quotations.create', [
            'shops' => Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')->orderBy('name')->get(),
            'vehicles' => $request->user()->vehicles()->orderBy('plate_number')->get(),
        ]);
    }

    public function store(RequestQuotationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $quotation = Quotation::query()->create([
                ...$request->validated(), 'customer_id' => $request->user()->id,
                'quotation_number' => 'QT-'.Str::upper(Str::random(10)), 'status' => 'draft',
            ]);
            $quotation->shop->owner->notifications()->create([
                'type' => 'quotation_requested', 'title' => 'New quotation request',
                'message' => "{$request->user()->name} requested an estimate for {$quotation->vehicle->plate_number}.",
                'data' => ['quotation_id' => $quotation->id],
            ]);
        });

        return redirect()->route('customer.quotations.index')->with('status', 'Quotation request sent to the shop.');
    }

    public function index(Request $request): View
    {
        return view('customer.quotations.index', [
            'quotations' => Quotation::query()->whereBelongsTo($request->user(), 'customer')
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['shop', 'vehicle', 'items'])
                ->latest()
                ->get(),
        ]);
    }

    public function update(RespondToQuotationRequest $request, Quotation $quotation, RepairInventoryService $inventory): RedirectResponse
    {
        abort_unless($quotation->customer_id === $request->user()->id, 404);
        $expired = DB::transaction(function () use ($request, $quotation, $inventory): bool {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            abort_unless($quotation->status === 'sent', 404);
            if ($quotation->valid_until?->isBefore(today())) {
                $quotation->update(['status' => 'expired']);
                if ($quotation->is_supplemental) {
                    $this->applySupplementalDecision($quotation, 'expired', $inventory, $request->user()->id);
                }

                return true;
            }
            $status = $request->validated('status');
            $quotation->update(['status' => $status]);
            $quotation->approvalRecords()->create(['customer_id' => $request->user()->id, 'decision' => $status, 'decided_at' => now()]);
            if ($quotation->is_supplemental) {
                $this->applySupplementalDecision($quotation, $status, $inventory, $request->user()->id);
            } elseif (in_array($status, ['accepted', 'approved'], true)) {
                $repairJobs = RepairJob::query()->where('quotation_id', $quotation->id)
                    ->whereNotIn('status', ['completed', 'released', 'cancelled'])->lockForUpdate()->get();
                foreach ($repairJobs as $repairJob) {
                    if (! $repairJob->items()->exists()) {
                        $repairJob->items()->createMany($quotation->items->map(fn ($item): array => [
                            'type' => $item->service_id ? 'service' : 'other',
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                        ])->all());
                    }
                    $previousStatus = $repairJob->status;
                    $nextStatus = $inventory->reserveAvailableForJob($repairJob) ? 'approved' : 'waiting_parts';
                    $repairJob->update(['status' => $nextStatus,
                        'estimated_cost' => $quotation->total, 'actual_cost' => $quotation->total]);
                    if ($previousStatus !== $nextStatus) {
                        $repairJob->statusHistories()->create(['from_status' => $previousStatus, 'to_status' => $nextStatus, 'changed_by' => $request->user()->id, 'changed_at' => now(), 'note' => $nextStatus === 'waiting_parts' ? 'Customer approved estimate; linked parts need restocking' : 'Customer approved estimate']);
                    }
                }
            }
            $quotation->shop->owner->notifications()->create([
                'type' => 'quotation_response', 'title' => 'Quotation response',
                'message' => "Quotation {$quotation->quotation_number} was {$status}.", 'data' => ['quotation_id' => $quotation->id],
            ]);

            return false;
        });

        return back()->with('status', $expired ? 'This quotation has expired. Request a new estimate.' : 'Quotation response saved.');
    }

    private function applySupplementalDecision(Quotation $quotation, string $decision, RepairInventoryService $inventory, int $actorId): void
    {
        $job = RepairJob::query()->lockForUpdate()->findOrFail($quotation->repair_job_id);
        if ($job->status !== 'waiting_approval' || in_array($job->status, ['completed', 'released', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'This repair is no longer awaiting the revised estimate.']);
        }
        if (in_array($decision, ['accepted', 'approved'], true)) {
            foreach ($quotation->supplemental_items ?? [] as $item) {
                $job->items()->create([
                    'type' => $item['type'], 'description' => $item['description'],
                    'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                    'spare_part_id' => $item['spare_part_id'] ?? null,
                    'subtotal' => round((int) $item['quantity'] * (float) $item['unit_price'], 2),
                ]);
            }
            if ((int) round((float) $job->items()->sum('subtotal') * 100) !== (int) round((float) $quotation->total * 100)) {
                throw ValidationException::withMessages(['status' => 'The revised work items do not match the approved price. Contact the shop before continuing.']);
            }
            $nextStatus = $inventory->reserveAvailableForJob($job) ? 'approved' : 'waiting_parts';
            $job->update(['quotation_id' => $quotation->id, 'estimated_cost' => $quotation->total,
                'actual_cost' => $quotation->total, 'status' => $nextStatus]);
            $note = 'Customer approved revised estimate '.$quotation->quotation_number;
        } else {
            $nextStatus = $quotation->previous_job_status ?: 'approved';
            $job->update(['status' => $nextStatus]);
            $note = 'Customer '.($decision === 'expired' ? 'did not approve before expiry' : 'declined').' revised estimate '.$quotation->quotation_number;
        }
        $job->statusHistories()->create(['from_status' => 'waiting_approval', 'to_status' => $nextStatus,
            'changed_by' => $actorId, 'changed_at' => now(), 'note' => $note]);
    }
}
