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
        if (! Schema::hasTable('repair_photos')) {
            Schema::create('repair_photos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('stage', 16);
                $table->string('path');
                $table->string('caption', 255)->nullable();
                $table->timestamps();
                $table->index(['repair_job_id', 'stage']);
            });
        }
        if (! Schema::hasTable('repair_warranties')) {
            Schema::create('repair_warranties', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_job_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('coverage');
                $table->date('valid_from');
                $table->date('valid_until');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('service_cases')) {
            Schema::create('service_cases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->string('type', 24);
                $table->string('subject', 150);
                $table->text('description');
                $table->string('status', 24)->default('open');
                $table->text('shop_response')->nullable();
                $table->text('admin_resolution')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'status']);
                $table->index(['shop_id', 'status']);
                $table->index(['type', 'status']);
            });
        } else {
            Schema::table('service_cases', function (Blueprint $table): void {
                if (! Schema::hasColumn('service_cases', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('service_cases', 'shop_response')) {
                    $table->text('shop_response')->nullable();
                }
                if (! Schema::hasColumn('service_cases', 'admin_resolution')) {
                    $table->text('admin_resolution')->nullable();
                }
                if (! Schema::hasColumn('service_cases', 'resolved_by')) {
                    $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
        if (! Schema::hasTable('service_case_events')) {
            Schema::create('service_case_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_case_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing aftercare tables may predate this migration. Preserve their data on rollback.
    }
};
