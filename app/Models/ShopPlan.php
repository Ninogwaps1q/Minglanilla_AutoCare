<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPlan extends Model
{
    protected $fillable = ['monthly_price', 'summary', 'features', 'payment_instructions'];

    protected function casts(): array
    {
        return ['features' => 'array'];
    }
}
