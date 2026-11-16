<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPlanPayment extends Model
{
    protected $fillable = ['shop_id', 'plan_slug', 'amount', 'reference', 'proof_path', 'status', 'review_notes', 'reviewed_by', 'reviewed_at', 'period_starts_at', 'period_ends_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'period_starts_at' => 'datetime', 'period_ends_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
