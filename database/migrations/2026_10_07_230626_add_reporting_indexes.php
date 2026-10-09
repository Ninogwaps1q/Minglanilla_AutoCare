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
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'payment_date']);
            $table->index(['shop_id', 'status', 'payment_date']);
        });

        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->index(['status', 'completed_at']);
            $table->index(['shop_id', 'status', 'completed_at']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['status', 'payment_date']);
            $table->dropIndex(['shop_id', 'status', 'payment_date']);
        });

        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->dropIndex(['status', 'completed_at']);
            $table->dropIndex(['shop_id', 'status', 'completed_at']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
