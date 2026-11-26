<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    public const TIMEZONE = 'Asia/Manila';

    protected $guarded = [];

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->appointment_date->format('Y-m-d').' '.$this->appointment_time->format('H:i:s'), self::TIMEZONE);
    }

    public function needsLateChangeAcknowledgement(): bool
    {
        return $this->status === 'confirmed' && $this->cancellation_notice_hours > 0
            && now()->gt($this->startsAt()->subHours($this->cancellation_notice_hours));
    }

    /** @return array<string, string> */
    public function availableStatusTransitions(): array
    {
        return match ($this->status) {
            'pending' => ['confirmed' => 'Confirm', 'cancelled' => 'Cancel'],
            'confirmed' => ['completed' => 'Complete', 'cancelled' => 'Cancel'] + (
                now()->gt($this->startsAt()) ? ['no_show' => __('extensions.appointments.mark_no_show')] : []
            ),
            default => [],
        };
    }

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'appointment_time' => 'datetime:H:i',
            'service_price' => 'decimal:2',
            'service_labor_included' => 'boolean',
            'service_parts_included' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function bookedServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class)->orderBy('position');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->latest('changed_at');
    }

    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(AppointmentRescheduleRequest::class)->latest();
    }

    public function latestRescheduleRequest(): HasOne
    {
        return $this->hasOne(AppointmentRescheduleRequest::class)->latestOfMany();
    }

    public function repairJob(): HasOne
    {
        return $this->hasOne(RepairJob::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function serviceCase(): HasOne
    {
        return $this->hasOne(ServiceCase::class);
    }
}
