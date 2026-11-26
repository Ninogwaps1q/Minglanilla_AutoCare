<?php

namespace App\Models;

use Database\Factories\AppointmentServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentService extends Model
{
    /** @use HasFactory<AppointmentServiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'labor_included' => 'boolean', 'parts_included' => 'boolean'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
