<?php

namespace App\Models;

use Database\Factories\ServiceCaseEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCaseEvent extends Model
{
    /** @use HasFactory<ServiceCaseEventFactory> */
    use HasFactory;

    protected $guarded = [];

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
