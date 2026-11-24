<?php

namespace App\Models;

use Database\Factories\BarangayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    /** @use HasFactory<BarangayFactory> */
    use HasFactory;

    protected $fillable = ['municipality_id', 'name', 'status', 'psgc_code'];

    public const AREA_FILTER_RULES = [
        'region_id' => ['nullable', 'integer', 'exists:regions,id'],
        'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
    ];

    /** @param array{region_id?: int|string|null, province_id?: int|string|null, municipality_id?: int|string|null} $area */
    public function scopeInArea(Builder $query, array $area): Builder
    {
        return $query
            ->when($area['municipality_id'] ?? null, fn (Builder $query, int|string $city): Builder => $query->where('municipality_id', $city))
            ->when($area['province_id'] ?? null, fn (Builder $query, int|string $province): Builder => $query->whereHas('municipality', fn (Builder $city): Builder => $city->where('province_id', $province)))
            ->when($area['region_id'] ?? null, fn (Builder $query, int|string $region): Builder => $query->whereHas('municipality.province', fn (Builder $province): Builder => $province->where('region_id', $region)));
    }

    public function areaLabel(): string
    {
        $province = $this->municipality->province;
        $region = $province->region?->name;
        $provinceName = preg_match('/^R\d+C$/', $province->code) ? null : $province->name;

        return implode(', ', array_filter([$this->name, $this->municipality->name, $provinceName, $region]));
    }

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
