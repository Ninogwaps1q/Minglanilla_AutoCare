<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('monthly_price');
            $table->string('summary');
            $table->json('features');
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('plan_slug')->default('free');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('plan_expires_at')->nullable();
            $table->string('requested_plan')->nullable();
        });

        $now = now();
        DB::table('shop_plans')->insert([
            ['slug' => 'free', 'name' => 'Free', 'monthly_price' => 0, 'summary' => 'Get your shop discovered by local drivers.', 'features' => json_encode(['Shop name and profile', 'Services in the directory', 'Map location after approval']), 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'basic', 'name' => 'Basic', 'monthly_price' => 499, 'summary' => 'Get extra visibility in the shop directory.', 'features' => json_encode(['Everything in Free', 'Priority in default shop results', 'Basic badge on shop listings']), 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'premium', 'name' => 'Premium', 'monthly_price' => 999, 'summary' => 'Make your shop stand out first.', 'features' => json_encode(['Everything in Basic', 'Top of default shop results', 'Premium badge on shop listings']), 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['plan_slug', 'trial_started_at', 'plan_expires_at', 'requested_plan']);
        });
        Schema::dropIfExists('shop_plans');
    }
};
