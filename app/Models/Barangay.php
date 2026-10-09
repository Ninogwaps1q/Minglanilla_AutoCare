<?php

namespace App\Models;

use Database\Factories\BarangayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    /** @use HasFactory<BarangayFactory> */
    use HasFactory;

    protected $fillable = ['municipality_id', 'name', 'status'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
