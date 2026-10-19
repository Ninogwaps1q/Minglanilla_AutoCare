<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repair_job_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note')->nullable();
            $table->timestamp('changed_at');
            $table->index(['repair_job_id', 'changed_at']);
        });

        DB::table('repair_jobs')->select(['id', 'status'])->orderBy('id')->chunkById(500, function ($repairJobs): void {
            DB::table('repair_job_status_histories')->insert($repairJobs->map(fn ($repairJob): array => [
                'repair_job_id' => $repairJob->id,
                'to_status' => $repairJob->status,
                'note' => 'Current status when tracking began',
                'changed_at' => now(),
            ])->all());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_job_status_histories');
    }
};
