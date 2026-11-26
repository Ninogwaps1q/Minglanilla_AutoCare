<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['shops', 'appointments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedSmallInteger('cancellation_notice_hours')->default(0));
        }
        Schema::table('appointment_status_histories', fn (Blueprint $table) => $table->string('note', 500)->nullable());
        Schema::table('appointments', fn (Blueprint $table) => $table->index(['status', 'appointment_date']));
    }

    public function down(): void
    {
        Schema::table('appointment_status_histories', fn (Blueprint $table) => $table->dropColumn('note'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropIndex(['status', 'appointment_date']));
        foreach (['appointments', 'shops'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('cancellation_notice_hours'));
        }
    }
};
