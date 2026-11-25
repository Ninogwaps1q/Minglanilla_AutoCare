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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('legal_version', 40)->nullable();
            $table->timestamp('legal_accepted_at')->nullable();
            $table->json('communication_preferences')->nullable();
            $table->json('optional_consents')->nullable();
            $table->string('locale', 5)->nullable();
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['user_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['legal_version', 'legal_accepted_at', 'communication_preferences', 'optional_consents', 'locale']);
        });
    }
};
