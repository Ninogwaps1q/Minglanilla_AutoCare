<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->decimal('service_price', 12, 2)->nullable();
        });

        Schema::table('repair_job_items', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_job_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('service_price');
        });
    }
};
