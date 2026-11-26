<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'shops', 'vehicles', 'appointments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes()->index());
        }
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('anonymized_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('anonymized_at'));
        foreach (['users', 'shops', 'vehicles', 'appointments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropIndex($name.'_deleted_at_index');
                $table->dropSoftDeletes();
            });
        }
    }
};
