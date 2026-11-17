<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShopPlan extends Model
{
    protected $fillable = ['monthly_price', 'summary', 'features', 'payment_instructions', 'payment_account_name', 'payment_account_number', 'payment_qr_path'];

    public function hasPaymentQr(): bool
    {
        return $this->payment_qr_path && Storage::disk('local')->exists($this->payment_qr_path);
    }

    public function hasPaymentDetails(): bool
    {
        return filled($this->payment_instructions)
            || (filled($this->payment_account_name) && filled($this->payment_account_number))
            || $this->hasPaymentQr();
    }

    protected function casts(): array
    {
        return ['features' => 'array'];
    }
}
