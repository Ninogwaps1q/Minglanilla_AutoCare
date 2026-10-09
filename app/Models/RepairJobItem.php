<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJobItem extends Model
{
    protected $guarded = [];

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }
}
