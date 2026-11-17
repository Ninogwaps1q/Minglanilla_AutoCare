<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('paymongo_session_id')->nullable()->unique();
            $table->text('paymongo_checkout_url')->nullable();
            $table->string('paymongo_payment_id')->nullable()->unique();
            $table->string('settlement_status')->nullable();
            $table->string('settlement_reference')->nullable();
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
        });
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->string('paymongo_session_id')->nullable()->unique();
            $table->text('paymongo_checkout_url')->nullable();
            $table->string('paymongo_payment_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_session_id']);
            $table->dropUnique(['paymongo_payment_id']);
            $table->dropColumn(['paymongo_session_id', 'paymongo_checkout_url', 'paymongo_payment_id']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_session_id']);
            $table->dropUnique(['paymongo_payment_id']);
            $table->dropForeign(['settled_by']);
            $table->dropColumn(['paymongo_session_id', 'paymongo_checkout_url', 'paymongo_payment_id', 'settlement_status', 'settlement_reference', 'settlement_amount', 'settled_by', 'settled_at']);
        });
    }
};
