<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technician extends Model
{
    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TechnicianAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('completed_at');
    }
}
