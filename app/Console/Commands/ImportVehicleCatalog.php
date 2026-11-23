<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ImportVehicleCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:import-vehicles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import vPIC makes and models once into the local vehicle catalog';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baseUrl = 'https://vpic.nhtsa.dot.gov/api/vehicles';
        try {
            $makes = Http::timeout(45)->get($baseUrl.'/GetAllMakes', ['format' => 'json'])->throw()->json('Results', []);
        } catch (Throwable $exception) {
            $this->error('vPIC makes could not be downloaded: '.$exception->getMessage());

            return self::FAILURE;
        }

        $catalogMakes = VehicleMake::query()->orderBy('id')->get();
        $importedModels = 0;
        foreach ($catalogMakes as $make) {
            $match = collect($makes)->first(fn (array $entry): bool => strcasecmp($entry['Make_Name'] ?? '', $make->name) === 0);
            if ($match === null) {
                $this->warn("No vPIC match for {$make->vehicle_type} {$make->name}; local entries remain available.");

                continue;
            }
            $make->update(['nhtsa_id' => $match['Make_ID']]);
            try {
                $models = Http::timeout(45)->get($baseUrl.'/GetModelsForMakeId/'.$match['Make_ID'], ['format' => 'json'])->throw()->json('Results', []);
            } catch (Throwable $exception) {
                $this->warn("Could not import models for {$make->name}: {$exception->getMessage()}");

                continue;
            }
            foreach ($models as $entry) {
                $name = trim((string) ($entry['Model_Name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    continue;
                }
                VehicleModel::query()->firstOrCreate(
                    ['vehicle_make_id' => $make->id, 'name' => $name],
                    ['variants' => ['Base'], 'source' => 'vpic'],
                );
                $importedModels++;
            }
        }

        Vehicle::query()->whereNull('vehicle_model_id')->chunkById(200, function ($vehicles): void {
            foreach ($vehicles as $vehicle) {
                $match = VehicleModel::query()->where('name', $vehicle->model)
                    ->whereHas('make', fn ($query) => $query->where('name', $vehicle->make)
                        ->when($vehicle->vehicle_type, fn ($query, $type) => $query->where('vehicle_type', $type)))
                    ->first();
                if ($match !== null) {
                    $vehicle->update(['vehicle_make_id' => $match->vehicle_make_id, 'vehicle_model_id' => $match->id]);
                }
            }
        });

        $this->info("Imported {$importedModels} vPIC model entries. Existing vehicle text was preserved.");

        return self::SUCCESS;
    }
}
