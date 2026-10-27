<?php

namespace App\Models;

use Database\Factories\RepairJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RepairJob extends Model
{
    /** @use HasFactory<RepairJobFactory> */
    use HasFactory;

    /** @return array<string, string> */
    public function availableStatuses(): array
    {
        if ($this->pendingSupplementalQuotation !== null) {
            return $this->status === 'cancelled' ? [] : ['cancelled' => 'Cancel'];
        }
        if ($this->status === 'completed') {
            return $this->invoice?->status === 'paid' ? ['released' => 'Release vehicle'] : [];
        }

        if (in_array($this->status, ['released', 'cancelled'], true)) {
            return [];
        }

        if (! in_array($this->quotation?->status, ['accepted', 'approved'], true)) {
            return match ($this->status) {
                'pending' => ['diagnosis' => 'Record diagnosis', 'cancelled' => 'Cancel'],
                default => ['cancelled' => 'Cancel'],
            };
        }

        return match ($this->status) {
            'pending', 'diagnosis', 'waiting_approval', 'approved' => ['repairing' => 'Start repair', 'cancelled' => 'Cancel'],
            'repairing', 'in_progress' => ['waiting_parts' => 'Waiting for parts', 'completed' => 'Complete repair', 'cancelled' => 'Cancel'],
            'waiting_parts' => ['repairing' => 'Resume repair', 'cancelled' => 'Cancel'],
            default => ['cancelled' => 'Cancel'],
        };
    }

    /** @return array<int, array{label: string, state: string}> */
    public function serviceStages(): array
    {
        if ($this->status === 'cancelled') {
            return [];
        }

        $diagnosed = filled($this->diagnosis) || ! in_array($this->status, ['pending', 'diagnosis'], true);
        $estimateApproved = in_array($this->quotation?->status, ['accepted', 'approved'], true);
        $repairStarted = $this->started_at !== null || in_array($this->status, ['repairing', 'in_progress', 'waiting_parts', 'completed', 'released'], true);
        $repairFinished = in_array($this->status, ['completed', 'released'], true);
        $paymentConfirmed = $this->invoice?->status === 'paid';

        return [
            ['label' => $this->appointment_id ? 'Booking' : 'Repair opened', 'state' => 'complete'],
            ['label' => 'Diagnosis', 'state' => $diagnosed ? 'complete' : 'current'],
            ['label' => 'Estimate approval', 'state' => $estimateApproved ? 'complete' : ($diagnosed ? 'current' : 'upcoming')],
            ['label' => 'Repair', 'state' => $repairFinished ? 'complete' : ($estimateApproved || $repairStarted ? 'current' : 'upcoming')],
            ['label' => 'Payment', 'state' => $paymentConfirmed ? 'complete' : ($repairFinished ? 'current' : 'upcoming')],
            ['label' => 'Vehicle release', 'state' => $this->status === 'released' ? 'complete' : ($repairFinished && $paymentConfirmed ? 'current' : 'upcoming')],
        ];
    }

    /** @return array{actor: string, action: string} */
    public function nextAction(): array
    {
        if ($this->status === 'cancelled') {
            return ['actor' => 'None', 'action' => 'This repair was cancelled.'];
        }
        if ($this->status === 'released') {
            return ['actor' => 'None', 'action' => 'The vehicle has been released.'];
        }
        if ($this->pendingSupplementalQuotation !== null) {
            return ['actor' => 'Customer', 'action' => 'Review the additional work and revised price before repairs continue.'];
        }
        if ($this->status === 'completed') {
            if ($this->invoice === null) {
                return ['actor' => 'Shop', 'action' => 'Issue the itemized invoice.'];
            }
            if ($this->invoice->status === 'paid') {
                return ['actor' => 'Shop', 'action' => 'Release the vehicle when it is ready for pickup.'];
            }
            $hasPendingPayment = $this->invoice->relationLoaded('payments')
                ? $this->invoice->payments->contains('status', 'pending')
                : $this->invoice->payments()->where('status', 'pending')->exists();
            if ($hasPendingPayment) {
                return ['actor' => 'Shop', 'action' => 'Review the customer payment.'];
            }

            return ['actor' => 'Customer', 'action' => 'Submit payment for the invoice.'];
        }
        if ($this->quotation?->status === 'sent') {
            if ($this->quotation->valid_until?->isBefore(today())) {
                return ['actor' => 'Shop', 'action' => 'Send a new estimate because the previous one expired.'];
            }

            return ['actor' => 'Customer', 'action' => 'Review and approve or decline the estimate.'];
        }
        if ($this->status === 'waiting_parts') {
            return ['actor' => 'Shop', 'action' => 'Restock and reserve missing parts, then resume the repair.'];
        }
        if (in_array($this->quotation?->status, ['accepted', 'approved'], true)
            && in_array($this->status, ['pending', 'diagnosis', 'waiting_approval', 'approved'], true)) {
            return ['actor' => 'Shop', 'action' => 'Start the customer-approved repair.'];
        }
        if (in_array($this->status, ['repairing', 'in_progress'], true)) {
            return ['actor' => 'Shop', 'action' => 'Record parts used and complete the repair when work is finished.'];
        }
        if ($this->hasUnbilledBookedService()) {
            return ['actor' => 'Shop', 'action' => 'Review and add the booked service before sending the estimate.'];
        }

        return ['actor' => 'Shop', 'action' => 'Record the diagnosis and work items, then send an estimate.'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function supplementalQuotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function pendingSupplementalQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_supplemental', true)->where('status', 'sent')->latestOfMany();
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function technicianAssignments(): HasMany
    {
        return $this->hasMany(TechnicianAssignment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepairJobItem::class);
    }

    public function hasUnbilledBookedService(): bool
    {
        $serviceId = $this->appointment?->service_id;
        if ($serviceId === null) {
            return false;
        }

        return $this->relationLoaded('items')
            ? ! $this->items->contains('service_id', $serviceId)
            : ! $this->items()->where('service_id', $serviceId)->exists();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RepairPhoto::class)->oldest();
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(RepairWarranty::class);
    }

    public function serviceCases(): HasMany
    {
        return $this->hasMany(ServiceCase::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RepairJobStatusHistory::class)->oldest('changed_at');
    }

    /** @return Collection<int, array{label: string, actor: string, at: Carbon}> */
    public function workflowEvents(): Collection
    {
        $events = $this->statusHistories->map(fn (RepairJobStatusHistory $history): array => [
            'label' => ucfirst(str_replace('_', ' ', $history->to_status)).($history->note ? ' · '.$history->note : ''),
            'actor' => $history->changedBy?->name ?? 'Recorded status',
            'at' => $history->changed_at,
        ]);

        foreach ($this->quotation?->approvalRecords ?? [] as $approval) {
            $events->push([
                'label' => 'Estimate '.($approval->decision === 'accepted' ? 'approved' : str_replace('_', ' ', $approval->decision)),
                'actor' => $approval->customer?->name ?? 'Customer',
                'at' => $approval->decided_at,
            ]);
        }

        if ($this->invoice !== null) {
            $events->push(['label' => 'Invoice issued', 'actor' => $this->invoice->issuedBy?->name ?? 'Shop (actor not recorded)', 'at' => $this->invoice->created_at]);
            foreach ($this->invoice->payments as $payment) {
                $events->push([
                    'label' => 'Payment '.str_replace('_', ' ', $payment->status),
                    'actor' => $payment->status === 'pending' ? ($payment->customer?->name ?? 'Customer') : ($payment->reviewedBy?->name ?? 'Shop (actor not recorded)'),
                    'at' => $payment->status === 'pending' ? $payment->created_at : ($payment->reviewed_at ?? $payment->updated_at),
                ]);
            }
        }

        return $events->sortBy(fn (array $event): int => $event['at']->timestamp)->values();
    }
}
