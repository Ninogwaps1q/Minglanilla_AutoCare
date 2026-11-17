<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ShopPlanPayment extends Model
{
    protected $fillable = ['shop_id', 'plan_slug', 'amount', 'reference', 'proof_path', 'status', 'review_notes', 'reviewed_by', 'reviewed_at', 'period_starts_at', 'period_ends_at', 'paymongo_session_id', 'paymongo_checkout_url', 'paymongo_payment_id', 'paymongo_method', 'payment_alerted_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'period_starts_at' => 'datetime', 'period_ends_at' => 'datetime', 'payment_alerted_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function hasProof(): bool
    {
        return $this->proof_path && Storage::exists($this->proof_path);
    }

    public function hasImageProof(): bool
    {
        return $this->hasProof() && in_array(strtolower(pathinfo($this->proof_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
