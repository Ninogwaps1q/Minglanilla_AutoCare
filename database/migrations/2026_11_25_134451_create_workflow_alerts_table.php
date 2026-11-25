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
        Schema::create('workflow_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('event_key', 100)->unique();
            $table->json('data');
            $table->json('channels')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('next_reminder_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('workflow_alert_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('sms_deliveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('notification_id')->nullable()->change();
            $table->foreignId('workflow_alert_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->json('provider_response')->nullable();
            $table->decimal('cost', 10, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sms_deliveries')->whereNull('notification_id')->delete();
        Schema::table('sms_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['workflow_alert_id']);
            $table->dropUnique(['workflow_alert_id']);
            $table->dropColumn(['workflow_alert_id', 'provider_response', 'cost']);
            $table->unsignedBigInteger('notification_id')->nullable(false)->change();
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropForeign(['workflow_alert_id']);
            $table->dropUnique(['workflow_alert_id']);
            $table->dropColumn('workflow_alert_id');
        });
        Schema::dropIfExists('workflow_alerts');
    }
};
