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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('barangay_id')->references('id')->on('barangays')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(['customer_id', 'status', 'appointment_date'], 'appointments_customer_status_date_index');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->index(['customer_id', 'status', 'created_at'], 'quotations_customer_status_created_index');
            $table->index(['shop_id', 'status', 'created_at'], 'quotations_shop_status_created_index');
        });

        Schema::table('maintenance_reminders', function (Blueprint $table): void {
            $table->index(['customer_id', 'status', 'reminder_date'], 'reminders_customer_status_date_index');
        });

        Schema::table('emergency_requests', function (Blueprint $table): void {
            $table->index(['customer_id', 'status', 'created_at'], 'emergencies_customer_status_created_index');
            $table->index(['assigned_shop_id', 'status', 'created_at'], 'emergencies_shop_status_created_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_read_created_index');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['thread_id', 'created_at'], 'messages_thread_created_index');
        });

        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'id'], 'ai_messages_conversation_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->index('conversation_id', 'ai_messages_conversation_fk_support_index');
            $table->dropIndex('ai_messages_conversation_id_index');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->index('thread_id', 'messages_thread_fk_support_index');
            $table->dropIndex('messages_thread_created_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('user_id', 'notifications_user_fk_support_index');
            $table->dropIndex('notifications_user_read_created_index');
        });

        Schema::table('emergency_requests', function (Blueprint $table): void {
            $table->index('customer_id', 'emergencies_customer_fk_support_index');
            $table->index('assigned_shop_id', 'emergencies_shop_fk_support_index');
            $table->dropIndex('emergencies_customer_status_created_index');
            $table->dropIndex('emergencies_shop_status_created_index');
        });

        Schema::table('maintenance_reminders', function (Blueprint $table): void {
            $table->index('customer_id', 'reminders_customer_fk_support_index');
            $table->dropIndex('reminders_customer_status_date_index');
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->index('customer_id', 'quotations_customer_fk_support_index');
            $table->index('shop_id', 'quotations_shop_fk_support_index');
            $table->dropIndex('quotations_customer_status_created_index');
            $table->dropIndex('quotations_shop_status_created_index');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index('customer_id', 'appointments_customer_fk_support_index');
            $table->dropIndex('appointments_customer_status_date_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['barangay_id']);
        });
    }
};
