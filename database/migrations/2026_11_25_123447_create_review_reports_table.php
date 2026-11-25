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
        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending')->index();
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'reporter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
