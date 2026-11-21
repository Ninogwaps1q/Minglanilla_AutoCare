<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('schedule_blocks')->nullable();
            $table->boolean('roadside_available')->default(true);
            $table->unsignedSmallInteger('roadside_radius_km')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['schedule_blocks', 'roadside_available', 'roadside_radius_km']);
        });
    }
};
