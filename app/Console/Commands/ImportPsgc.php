<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ImportPsgc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:import-psgc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import PSGC regions, provinces, cities, municipalities, and barangays into the local database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baseUrl = 'https://psgc.gitlab.io/api';
        try {
            $regions = Http::timeout(120)->get($baseUrl.'/regions.json')->throw()->json();
            $provinces = Http::timeout(120)->get($baseUrl.'/provinces.json')->throw()->json();
            $cities = Http::timeout(120)->get($baseUrl.'/cities-municipalities.json')->throw()->json();
            $barangays = Http::timeout(120)->get($baseUrl.'/barangays.json')->throw()->json();
        } catch (Throwable $exception) {
            $this->error('PSGC data could not be downloaded: '.$exception->getMessage());

            return self::FAILURE;
        }
        if (! is_array($regions) || ! is_array($provinces) || ! is_array($cities) || ! is_array($barangays)
            || count($regions) < 10 || count($barangays) < 10000) {
            $this->error('PSGC returned an incomplete dataset. No locations were imported.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($regions, $provinces, $cities, $barangays): void {
            $regionIds = [];
            foreach ($regions as $entry) {
                $region = Region::query()->updateOrCreate(['psgc_code' => $entry['code']], ['name' => $entry['name']]);
                $regionIds[$entry['code']] = $region->id;
            }
            $provinceIds = [];
            foreach ($provinces as $entry) {
                $province = Province::query()->where('psgc_code', $entry['code'])->first()
                    ?? Province::query()->where('name', $entry['name'])->first();
                if ($province === null) {
                    $province = new Province(['name' => $entry['name'], 'code' => $entry['code']]);
                }
                $province->fill(['region_id' => $regionIds[$entry['regionCode']], 'psgc_code' => $entry['code']])->save();
                $provinceIds[$entry['code']] = $province->id;
            }
            $cityIds = [];
            foreach ($cities as $entry) {
                $provinceId = $provinceIds[$entry['provinceCode'] ?? ''] ?? null;
                if ($provinceId === null) {
                    $region = Region::query()->findOrFail($regionIds[$entry['regionCode']]);
                    $placeholder = Province::query()->firstOrCreate(
                        ['name' => $region->name.' cities'],
                        ['code' => 'R'.$region->id.'C', 'region_id' => $region->id],
                    );
                    $provinceId = $placeholder->id;
                }
                $city = Municipality::query()->where('psgc_code', $entry['code'])->first()
                    ?? Municipality::query()->where('province_id', $provinceId)->where('name', $entry['name'])->first();
                if ($city === null) {
                    $city = new Municipality(['name' => $entry['name'], 'code' => $entry['code']]);
                }
                $city->fill(['province_id' => $provinceId, 'psgc_code' => $entry['code']])->save();
                $cityIds[$entry['code']] = $city->id;
            }
            $legacy = Barangay::query()->whereNull('psgc_code')->get()->keyBy(fn (Barangay $barangay): string => $barangay->municipality_id.'|'.mb_strtolower($barangay->name));
            foreach (array_chunk($barangays, 100) as $chunk) {
                $rows = [];
                foreach ($chunk as $entry) {
                    $cityCode = $entry['cityCode'] ?: $entry['municipalityCode'];
                    $cityId = $cityIds[$cityCode] ?? null;
                    if ($cityId === null) {
                        continue;
                    }
                    $legacyBarangay = $legacy->get($cityId.'|'.mb_strtolower($entry['name']));
                    if ($legacyBarangay !== null) {
                        $legacyBarangay->update(['psgc_code' => $entry['code']]);
                        $legacy->forget($cityId.'|'.mb_strtolower($entry['name']));

                        continue;
                    }
                    $rows[] = [
                        'municipality_id' => $cityId,
                        'name' => $entry['name'],
                        'psgc_code' => $entry['code'],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($rows !== []) {
                    DB::table('barangays')->upsert($rows, ['psgc_code'], ['municipality_id', 'name', 'updated_at']);
                }
            }
        });

        $this->info('PSGC locations imported. Address pickers now use local endpoints.');

        return self::SUCCESS;
    }
}
