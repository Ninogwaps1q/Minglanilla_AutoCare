<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRescheduleRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_date' => 'date', 'original_time' => 'datetime:H:i',
            'requested_date' => 'date', 'requested_time' => 'datetime:H:i', 'decided_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
