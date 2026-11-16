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
        Schema::table('shop_plans', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable();
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('trial_revoked_at')->nullable();
        });

        Schema::create('shop_plan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('plan_slug');
            $table->unsignedInteger('amount');
            $table->string('reference', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_plan_payments');
        Schema::table('shop_plans', function (Blueprint $table) {
            $table->dropColumn('payment_instructions');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('trial_revoked_at');
        });
    }
};
