<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = ['plate_number', 'make', 'model', 'year', 'color', 'vehicle_type', 'engine_type', 'current_mileage', 'vin', 'photo'];

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(VehicleServiceRecord::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function maintenanceReminders(): HasMany
    {
        return $this->hasMany(MaintenanceReminder::class);
    }

    public function maintenanceRecommendations(): HasMany
    {
        return $this->hasMany(MaintenanceRecommendation::class);
    }
}
