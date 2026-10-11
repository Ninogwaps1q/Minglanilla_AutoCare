<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepairJob extends Model
{
    /** @return array<string, string> */
    public function availableStatuses(): array
    {
        return match ($this->status) {
            'completed' => ['released' => 'Release vehicle'],
            'released', 'cancelled' => [],
            'waiting_approval' => ['cancelled' => 'Cancel'],
            'approved', 'repairing', 'in_progress' => ['repairing' => 'Repairing', 'waiting_parts' => 'Waiting for parts', 'completed' => 'Complete', 'cancelled' => 'Cancel'],
            'waiting_parts' => ['repairing' => 'Resume repair', 'cancelled' => 'Cancel'],
            default => ['diagnosis' => 'Diagnosis', 'waiting_approval' => 'Waiting for approval', 'approved' => 'Approved', 'repairing' => 'Repairing', 'completed' => 'Complete', 'cancelled' => 'Cancel'],
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
}
