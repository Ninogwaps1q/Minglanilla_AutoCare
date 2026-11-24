<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportPsgc extends Command
{
    protected $signature = 'catalog:import-psgc';

    protected $description = 'Import the nationwide PSA geographic catalog into the local database';

    public function handle(): int
    {
        try {
            $regions = json_decode(file_get_contents(database_path('seeders/psgc-2026-q3.json')), true, flags: JSON_THROW_ON_ERROR);
            DB::transaction(function () use ($regions): void {
                foreach ($regions as [$code, $name, $previousCode, $provinces]) {
                    $region = Region::query()->where('psgc_code', $code)->first()
                        ?? Region::query()->where('psgc_code', $previousCode)->first()
                        ?? new Region;
                    $region->fill(['psgc_code' => $code, 'name' => $name])->save();

                    foreach ($provinces as [$provinceCode, $provinceName, $oldProvinceCode, $cities]) {
                        $province = $this->importProvince($region, $provinceCode, $provinceName, $oldProvinceCode);

                        foreach ($cities as [$cityCode, $cityName, $oldCityCode, $barangays]) {
                            $city = Municipality::query()->where('psgc_code', $cityCode)->first()
                                ?? ($oldCityCode ? Municipality::query()->where('psgc_code', $oldCityCode)->first() : null)
                                ?? $province->municipalities()->where('name', $cityName)->first()
                                ?? new Municipality(['code' => $cityCode]);
                            $city->fill(['province_id' => $province->id, 'psgc_code' => $cityCode, 'name' => $cityName])->save();

                            $this->importBarangays($city, $barangays);
                        }
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->error('PSGC import failed. No location changes were saved. '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('PSGC locations imported from the September 2026 release. Existing address references were retained.');

        return self::SUCCESS;
    }

    private function importProvince(Region $region, ?string $code, ?string $name, ?string $previousCode): Province
    {
        if ($code === null) {
            return Province::query()->updateOrCreate(
                ['code' => "R{$region->id}C"],
                ['name' => $region->name.' cities and municipalities', 'region_id' => $region->id],
            );
        }

        $province = Province::query()->where('psgc_code', $code)->first()
            ?? ($previousCode ? Province::query()->where('psgc_code', $previousCode)->first() : null)
            ?? Province::query()->where('name', $name)->first()
            ?? new Province(['code' => $code]);

        $province->fill(['region_id' => $region->id, 'psgc_code' => $code, 'name' => $name])->save();

        return $province;
    }

    /**
     * @param  list<array{string, string, ?string}>  $barangays
     */
    private function importBarangays(Municipality $city, array $barangays): void
    {
        $codes = array_merge(array_column($barangays, 0), array_filter(array_column($barangays, 2)));
        $existing = Barangay::query()->where('municipality_id', $city->id)->orWhereIn('psgc_code', $codes)->get();
        $byName = $existing->where('municipality_id', $city->id)->keyBy(fn (Barangay $barangay): string => $this->addressName($barangay->name));
        $byCode = $existing->whereNotNull('psgc_code')->keyBy('psgc_code');

        $now = now();
        $new = [];
        foreach ($barangays as [$code, $name, $previousCode]) {
            $barangay = $byCode->get($code) ?? $byCode->get($previousCode) ?? $byName->get($this->addressName($name));
            $attributes = ['municipality_id' => $city->id, 'name' => $name, 'psgc_code' => $code];
            if ($barangay !== null) {
                $barangay->fill($attributes)->save();

                continue;
            }

            $new[] = $attributes + ['status' => 'active', 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($new, 100) as $chunk) {
            DB::table('barangays')->insert($chunk);
        }
    }

    private function addressName(string $name): string
    {
        return Str::lower(Str::ascii(trim($name)));
    }
}
