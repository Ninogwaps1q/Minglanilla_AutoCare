<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianAssignment extends Model
{
    protected $guarded = [];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }
}
