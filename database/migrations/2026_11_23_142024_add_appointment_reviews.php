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
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('repair_job_id')->nullable()->change();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete()->unique();
        });

        DB::table('reviews')->join('repair_jobs', 'reviews.repair_job_id', '=', 'repair_jobs.id')
            ->whereNotNull('repair_jobs.appointment_id')
            ->select('reviews.id', 'repair_jobs.appointment_id')
            ->orderBy('reviews.id')->chunkById(200, function ($reviews): void {
                foreach ($reviews as $review) {
                    DB::table('reviews')->where('id', $review->id)->update(['appointment_id' => $review->appointment_id]);
                }
            }, 'reviews.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('reviews')->whereNull('repair_job_id')->exists()) {
            throw new RuntimeException('Cannot roll back appointment reviews while booking-only reviews exist.');
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
            $table->foreignId('repair_job_id')->nullable(false)->change();
        });
    }
};
