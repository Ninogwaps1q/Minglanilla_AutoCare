<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'quantity' => 'integer', 'minimum_stock' => 'integer'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
