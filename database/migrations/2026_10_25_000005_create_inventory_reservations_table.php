<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_job_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('reserved');
            $table->timestamps();
            $table->index(['spare_part_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
