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
        Schema::table('service_cases', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable();
            $table->string('schedule_status', 24)->nullable();
            $table->index(['shop_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'scheduled_at']);
            $table->dropColumn(['scheduled_at', 'schedule_status']);
        });
    }
};
