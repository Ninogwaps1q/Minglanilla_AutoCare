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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('settlement_reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settlement_reserved_at')->nullable();
            $table->string('refund_resolution', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_reserved_by');
            $table->dropColumn(['settlement_reserved_at', 'refund_resolution']);
        });
    }
};
