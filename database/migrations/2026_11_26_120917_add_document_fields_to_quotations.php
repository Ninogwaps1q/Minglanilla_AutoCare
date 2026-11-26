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
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignId('superseded_by_id')->nullable()->constrained('quotations')->nullOnDelete();
        });
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->string('type', 16)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropConstrainedForeignId('superseded_by_id'));
    }
};
