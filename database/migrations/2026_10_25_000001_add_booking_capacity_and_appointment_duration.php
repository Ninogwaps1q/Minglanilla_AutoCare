<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->unsignedTinyInteger('booking_capacity')->default(1);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('booking_capacity');
        });
    }
};
