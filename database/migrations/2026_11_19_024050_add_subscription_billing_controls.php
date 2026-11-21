<?php

use Carbon\Carbon;
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
        Schema::table('shops', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_payment_id')->nullable();
            $table->timestamp('next_plan_starts_at')->nullable()->index();
        });
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_status')->nullable()->index();
            $table->string('paymongo_refund_id')->nullable()->unique();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->index(['shop_id', 'status', 'period_starts_at'], 'shop_subscription_periods');
        });
        foreach (['payments', 'shop_plan_payments'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('paymongo_checked_at')->nullable()->index();
                $table->foreignId('refund_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('refund_reviewed_at')->nullable();
                $table->text('refund_review_notes')->nullable();
                $table->uuid('refund_request_key')->nullable();
            });
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->text('refund_reason')->nullable()->change();
        });

        DB::table('shops')->orderBy('id')->chunkById(100, function ($shops): void {
            foreach ($shops as $shop) {
                $periods = DB::table('shop_plan_payments')->where('shop_id', $shop->id)->where('status', 'approved');
                $current = (clone $periods)->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now())->latest('period_starts_at')->first();
                $next = (clone $periods)->where('period_starts_at', '>', now())->oldest('period_starts_at')->first();
                $updates = ['next_plan_starts_at' => $next?->period_starts_at];
                if ($current) {
                    $updates += ['plan_payment_id' => $current->id, 'plan_slug' => $current->plan_slug, 'plan_expires_at' => $current->period_ends_at];
                } elseif ($next && $shop->trial_started_at && ! $shop->trial_revoked_at && Carbon::parse($shop->trial_started_at)->addDays(30)->isFuture()) {
                    $updates += ['plan_slug' => 'free', 'plan_expires_at' => null];
                }
                DB::table('shops')->where('id', $shop->id)->update($updates);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['payments', 'shop_plan_payments'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['refund_reviewed_by']);
                $table->dropIndex(['paymongo_checked_at']);
                $table->dropColumn(['paymongo_checked_at', 'refund_reviewed_by', 'refund_reviewed_at', 'refund_review_notes', 'refund_request_key']);
            });
        }
        Schema::table('shop_plan_payments', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_refund_id']);
            $table->dropIndex(['refund_status']);
            $table->dropIndex('shop_subscription_periods');
            $table->dropColumn(['refund_amount', 'refund_reason', 'refund_status', 'paymongo_refund_id', 'refund_requested_at', 'refunded_at']);
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['next_plan_starts_at']);
            $table->dropColumn(['plan_payment_id', 'next_plan_starts_at']);
        });
    }
};
