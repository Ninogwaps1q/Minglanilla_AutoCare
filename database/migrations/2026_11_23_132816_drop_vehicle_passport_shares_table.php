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
        Schema::dropIfExists('vehicle_passport_shares');
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropUnique(['qr_code']);
            $table->dropColumn('qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('qr_code')->nullable()->unique();
        });
        Schema::create('vehicle_passport_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'revoked_at', 'expires_at']);
        });
    }
};
