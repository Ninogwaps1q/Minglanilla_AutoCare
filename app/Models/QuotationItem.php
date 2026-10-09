<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $guarded = [];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
