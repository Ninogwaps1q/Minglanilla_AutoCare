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
        Schema::table('shop_plans', function (Blueprint $table): void {
            $table->string('payment_account_name')->nullable();
            $table->string('payment_account_number', 100)->nullable();
            $table->string('payment_qr_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_plans', function (Blueprint $table): void {
            $table->dropColumn(['payment_account_name', 'payment_account_number', 'payment_qr_path']);
        });
    }
};
