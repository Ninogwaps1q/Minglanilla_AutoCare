<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paymongo_fee' => 'decimal:2', 'paymongo_net_amount' => 'decimal:2', 'platform_fee' => 'decimal:2', 'refund_amount' => 'decimal:2', 'dispute_amount' => 'decimal:2', 'payment_date' => 'datetime', 'reviewed_at' => 'datetime', 'settlement_amount' => 'decimal:2', 'settled_at' => 'datetime', 'settlement_reserved_at' => 'datetime', 'refund_requested_at' => 'datetime', 'refunded_at' => 'datetime', 'payment_alerted_at' => 'datetime', 'paymongo_checked_at' => 'datetime', 'refund_reviewed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refundReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_reviewed_by');
    }

    public function settlementReservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settlement_reserved_by');
    }
}
