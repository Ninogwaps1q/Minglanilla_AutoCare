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
        Schema::create('sms_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('delivery_day');
            $table->string('status', 20);
            $table->index(['user_id', 'delivery_day']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key', 100);
            $table->string('auth_secret', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('sms_deliveries');
    }
};
