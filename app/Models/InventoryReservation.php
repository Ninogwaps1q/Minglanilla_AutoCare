<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function repairJobItem(): BelongsTo
    {
        return $this->belongsTo(RepairJobItem::class);
    }
}
