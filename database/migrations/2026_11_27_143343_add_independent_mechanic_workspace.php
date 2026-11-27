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
        Schema::table('shops', function (Blueprint $table): void {
            $table->json('mechanic_profile')->nullable();
            $table->json('mechanic_settings')->nullable();
            $table->json('mechanic_tools')->nullable();
            $table->json('mechanic_portfolio')->nullable();
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->json('mobile_service_quote')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
        });
        Schema::table('shop_plans', function (Blueprint $table): void {
            $table->json('mechanic_entitlements')->nullable();
        });
        Schema::create('mechanic_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->date('spent_on');
            $table->string('description', 160);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['shop_id', 'spent_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mechanic_expenses');
        Schema::table('shop_plans', function (Blueprint $table): void {
            $table->dropColumn('mechanic_entitlements');
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['mobile_service_quote', 'en_route_at', 'arrived_at']);
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn(['mechanic_profile', 'mechanic_settings', 'mechanic_tools', 'mechanic_portfolio']);
        });
    }
};
