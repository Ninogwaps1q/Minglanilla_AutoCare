<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_services', function (Blueprint $table): void {
            $table->boolean('labor_included')->nullable();
            $table->boolean('parts_included')->nullable();
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('service_labor_included')->nullable();
            $table->boolean('service_parts_included')->nullable();
            $table->text('service_scope')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['service_labor_included', 'service_parts_included', 'service_scope']);
        });

        Schema::table('shop_services', function (Blueprint $table): void {
            $table->dropColumn(['labor_included', 'parts_included']);
        });
    }
};
