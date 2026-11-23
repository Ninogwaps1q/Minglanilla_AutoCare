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
        Schema::table('provinces', function (Blueprint $table): void {
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('psgc_code', 10)->nullable()->unique();
        });
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->string('psgc_code', 10)->nullable()->unique();
        });
        Schema::table('barangays', function (Blueprint $table): void {
            $table->string('psgc_code', 10)->nullable()->unique();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('landmark')->nullable();
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('landmark')->nullable();
        });
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('service_barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->string('service_street')->nullable();
            $table->string('service_landmark')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_barangay_id');
            $table->dropColumn(['service_street', 'service_landmark']);
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('landmark');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('landmark');
        });
        Schema::table('barangays', function (Blueprint $table): void {
            $table->dropColumn('psgc_code');
        });
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropColumn('psgc_code');
        });
        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn('psgc_code');
        });
    }
};
