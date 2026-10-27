<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cases', function (Blueprint $table): void {
            $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('appointment_id');
        });
    }
};
