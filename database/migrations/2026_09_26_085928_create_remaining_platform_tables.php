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
        Schema::create('shop_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_path');
            $table->string('verification_status')->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
        Schema::create('shop_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->text('description')->nullable();
            $table->boolean('availability')->default(true);
            $table->timestamps();
            $table->unique(['shop_id', 'service_id']);
        });
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quotation_number')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->foreignId('quotation_id')->nullable()->after('appointment_id')->constrained()->nullOnDelete();
            $table->text('customer_notes')->nullable();
            $table->text('technician_notes')->nullable();
        });
        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
        Schema::create('repair_job_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
        Schema::create('technicians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('specialization')->nullable();
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->string('status')->default('active')->index();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
        Schema::create('technician_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['repair_job_id', 'technician_id']);
        });
        Schema::create('spare_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('part_number')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->string('supplier')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid')->index();
            $table->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('reference_number')->nullable();
            $table->timestamp('payment_date');
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('response')->nullable();
            $table->string('status')->default('published')->index();
            $table->timestamps();
            $table->unique(['customer_id', 'repair_job_id']);
        });
        Schema::create('vehicle_service_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->unsignedInteger('mileage')->nullable();
            $table->string('service_type');
            $table->text('description')->nullable();
            $table->text('parts_used')->nullable();
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('parts_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('technician_notes')->nullable();
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_mileage')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
        Schema::create('vehicle_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->date('inspection_date');
            $table->string('overall_condition');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('inspection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_inspection_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('item_name');
            $table->string('condition');
            $table->text('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('last_service_date')->nullable();
            $table->unsignedInteger('last_service_mileage')->nullable();
            $table->unsignedInteger('recommended_interval_days')->nullable();
            $table->unsignedInteger('recommended_interval_mileage')->nullable();
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_mileage')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('maintenance_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->date('reminder_date');
            $table->string('type');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
        Schema::create('emergency_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('emergency_type');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('assigned_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('message_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'shop_id']);
        });
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('emergency_requests');
        Schema::dropIfExists('maintenance_reminders');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('vehicle_inspections');
        Schema::dropIfExists('vehicle_service_records');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('technician_assignments');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('repair_job_items');
        Schema::dropIfExists('quotation_items');
        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropColumn(['customer_notes', 'technician_notes']);
        });
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('shop_services');
        Schema::dropIfExists('shop_documents');
    }
};
