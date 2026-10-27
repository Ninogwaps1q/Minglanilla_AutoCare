<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'is_supplemental' => 'boolean', 'supplemental_items' => 'array'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(QuotationApprovalRecord::class)->latest('decided_at');
    }
}
