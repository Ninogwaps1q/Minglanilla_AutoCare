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
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('repair_job_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_supplemental')->default(false);
            $table->json('supplemental_items')->nullable();
            $table->string('previous_job_status', 24)->nullable();
            $table->index(['repair_job_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['repair_job_id', 'status']);
            $table->dropConstrainedForeignId('repair_job_id');
            $table->dropColumn(['is_supplemental', 'supplemental_items', 'previous_job_status']);
        });
    }
};
