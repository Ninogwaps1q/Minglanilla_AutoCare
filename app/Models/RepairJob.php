<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RepairJob extends Model
{
    /** @return array<string, string> */
    public function availableStatuses(): array
    {
        if ($this->status === 'completed') {
            return ['released' => 'Release vehicle'];
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
            'completed' => ['released' => 'Release vehicle'],
            'released', 'cancelled' => [],
            'waiting_approval' => ['cancelled' => 'Cancel'],
            'approved', 'repairing', 'in_progress' => ['repairing' => 'Repairing', 'waiting_parts' => 'Waiting for parts', 'completed' => 'Complete', 'cancelled' => 'Cancel'],
            'waiting_parts' => ['repairing' => 'Resume repair', 'cancelled' => 'Cancel'],
            default => ['diagnosis' => 'Diagnosis', 'repairing' => 'Repairing', 'completed' => 'Complete', 'cancelled' => 'Cancel'],
        };
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
