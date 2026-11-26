<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPromotion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'disabled_at' => 'datetime', 'service_ids' => 'array', 'value' => 'decimal:2', 'minimum' => 'decimal:2'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $date = now('Asia/Manila')->toDateString();

        return $query->whereNull('disabled_at')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public function discountLabel(): string
    {
        return $this->kind === 'percent' ? rtrim(rtrim((string) $this->value, '0'), '.').'%' : 'PHP '.number_format($this->value, 2);
    }
}
