<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ShopPlanPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'period_starts_at' => 'datetime', 'period_ends_at' => 'datetime', 'payment_alerted_at' => 'datetime', 'paymongo_checked_at' => 'datetime', 'refund_requested_at' => 'datetime', 'refund_reviewed_at' => 'datetime', 'refunded_at' => 'datetime', 'refund_amount' => 'decimal:2'];
    }

    public function scopeValidPeriods(Builder $query): Builder
    {
        return $query->where('status', 'approved')->whereNotNull('period_starts_at')->whereNotNull('period_ends_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('refund_status')->orWhere('refund_status', '!=', 'succeeded'));
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function refundReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_reviewed_by');
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
