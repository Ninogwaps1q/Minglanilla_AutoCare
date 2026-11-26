<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_timeline_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_job_id')->constrained();
            $table->char('token_hash', 64)->unique();
            $table->json('photo_ids');
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['repair_job_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_timeline_shares');
    }
};
