<?php

namespace Database\Seeders;

use App\Models\RepairJob;
use Illuminate\Database\Seeder;

class RepairJobStatusHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RepairJob::query()->whereDoesntHave('statusHistories')->chunkById(500, function ($repairJobs): void {
            foreach ($repairJobs as $repairJob) {
                $repairJob->statusHistories()->create([
                    'to_status' => $repairJob->status,
                    'note' => 'Current status when tracking began',
                    'changed_at' => now(),
                ]);
            }
        });
    }
}
