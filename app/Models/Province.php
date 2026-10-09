<?php

namespace App\Models;

use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
