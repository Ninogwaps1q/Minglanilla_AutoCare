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
            $table->string('provider_type', 32)->default('shop')->index();
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('service_address', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('service_address');
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['provider_type']);
            $table->dropColumn('provider_type');
        });
    }
};
