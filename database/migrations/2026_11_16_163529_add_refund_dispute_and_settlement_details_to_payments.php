<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->string('settlement_proof_path')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('refund_reason')->nullable();
            $table->string('refund_status')->nullable();
            $table->string('paymongo_refund_id')->nullable()->unique();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('dispute_status')->nullable();
            $table->string('paymongo_dispute_id')->nullable();
            $table->timestamp('payment_alerted_at')->nullable();
        });
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->timestamp('payment_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->dropColumn('payment_alerted_at');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_refund_id']);
            $table->dropColumn(['platform_fee', 'settlement_proof_path', 'refund_amount', 'refund_reason', 'refund_status', 'paymongo_refund_id', 'refund_requested_at', 'refunded_at', 'dispute_status', 'paymongo_dispute_id', 'payment_alerted_at']);
        });
    }
};
