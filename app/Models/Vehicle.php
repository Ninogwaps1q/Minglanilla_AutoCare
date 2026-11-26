<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['plate_number', 'make', 'model', 'year', 'variant', 'vehicle_make_id', 'vehicle_model_id', 'color', 'vehicle_type', 'engine_type', 'current_mileage', 'vin', 'photo'];

    public function catalogMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(VehicleServiceRecord::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function maintenanceReminders(): HasMany
    {
        return $this->hasMany(MaintenanceReminder::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function maintenanceRecommendations(): HasMany
    {
        return $this->hasMany(MaintenanceRecommendation::class);
    }
}
