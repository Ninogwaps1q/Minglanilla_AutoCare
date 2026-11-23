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
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('labor_included')->nullable();
            $table->boolean('parts_included')->nullable();
            $table->text('scope')->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'service_id']);
        });

        DB::table('appointments')->whereNotNull('service_id')->orderBy('id')->chunkById(200, function ($appointments): void {
            $rows = [];
            foreach ($appointments as $appointment) {
                $rows[] = [
                    'appointment_id' => $appointment->id,
                    'service_id' => $appointment->service_id,
                    'position' => 1,
                    'price' => $appointment->service_price ?? 0,
                    'duration_minutes' => $appointment->duration_minutes ?: 60,
                    'labor_included' => $appointment->service_labor_included,
                    'parts_included' => $appointment->service_parts_included,
                    'scope' => $appointment->service_scope,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('appointment_services')->insert($rows);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
    }
};
