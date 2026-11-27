<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShopPlan extends Model
{
    protected $fillable = ['monthly_price', 'summary', 'features', 'payment_instructions', 'payment_account_name', 'payment_account_number', 'payment_qr_path', 'mechanic_entitlements'];

    public static function mechanicDefaults(string $slug): array
    {
        return match ($slug) {
            'free' => ['monthly_limit' => 3, 'travel_pricing' => false, 'live_tracking' => false, 'portfolio_limit' => 3, 'reminders' => false],
            'basic' => ['monthly_limit' => 30, 'travel_pricing' => true, 'live_tracking' => false, 'portfolio_limit' => 10, 'reminders' => true],
            default => ['monthly_limit' => null, 'travel_pricing' => true, 'live_tracking' => true, 'portfolio_limit' => 20, 'reminders' => true],
        };
    }

    public function mechanicFeatures(): array
    {
        return array_replace(self::mechanicDefaults($this->slug), $this->mechanic_entitlements ?? []);
    }

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
        return ['features' => 'array', 'mechanic_entitlements' => 'array'];
    }
}
