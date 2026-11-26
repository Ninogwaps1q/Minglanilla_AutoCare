<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleInspection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['inspection_date' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }
}
