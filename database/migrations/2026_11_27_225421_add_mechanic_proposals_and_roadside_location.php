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
        Schema::table('appointment_reschedule_requests', function (Blueprint $table): void {
            $table->boolean('proposed_by_provider')->default(false);
        });
        Schema::table('emergency_requests', function (Blueprint $table): void {
            $table->json('responder_location')->nullable();
            $table->timestamp('pickup_consented_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table): void {
            $table->dropColumn(['responder_location', 'pickup_consented_at', 'arrived_at']);
        });
        Schema::table('appointment_reschedule_requests', function (Blueprint $table): void {
            $table->dropColumn('proposed_by_provider');
        });
    }
};
