<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_job_items', function (Blueprint $table): void {
            $table->foreignId('spare_part_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('repair_job_item_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repair_job_item_id');
        });

        Schema::table('repair_job_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('spare_part_id');
        });
    }
};
