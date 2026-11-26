<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->string('name', 100);
            $table->string('code', 32)->nullable();
            $table->string('kind', 12);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum', 12, 2)->default(0);
            $table->json('service_ids')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('customer_limit')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'code']);
            $table->index(['shop_id', 'disabled_at', 'starts_on', 'ends_on']);
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('shop_promotion_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('promotion_discount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_promotion_id');
            $table->dropColumn('promotion_discount');
        });
        Schema::dropIfExists('shop_promotions');
    }
};
