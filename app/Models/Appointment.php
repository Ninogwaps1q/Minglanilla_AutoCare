<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    public function availableStatusTransitions(): array
    {
        return match ($this->status) {
            'pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Cancel'],
            'confirmed' => ['completed' => 'Complete', 'cancelled' => 'Cancel'],
            default => [],
        };
    }

    protected function casts(): array
    {
        return ['appointment_date' => 'date', 'appointment_time' => 'datetime:H:i'];
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->latest('changed_at');
    }

    public function repairJob(): HasOne
    {
        return $this->hasOne(RepairJob::class);
    }
}
