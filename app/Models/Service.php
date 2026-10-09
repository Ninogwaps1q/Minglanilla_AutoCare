<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'description', 'default_price', 'estimated_duration'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }
}
