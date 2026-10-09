<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyRequest extends Model
{
    protected $guarded = [];

    /** @return array<int, string> */
    public function allowedStatuses(): array
    {
        return match ($this->status) {
            'pending' => ['accepted', 'cancelled'],
            'accepted' => ['en_route', 'cancelled'],
            'en_route' => ['completed', 'cancelled'],
            default => [],
        };
    }

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'requested_at' => 'datetime', 'responded_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assignedShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'assigned_shop_id');
    }
}
