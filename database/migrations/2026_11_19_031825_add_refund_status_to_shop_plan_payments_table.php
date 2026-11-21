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
        $repairCoverage = ! Schema::hasColumn('shops', 'plan_payment_id') || ! Schema::hasColumn('shops', 'next_plan_starts_at');
        $shopColumns = Schema::getColumnListing('shops');
        Schema::table('shops', function (Blueprint $table) use ($shopColumns): void {
            if (! in_array('plan_payment_id', $shopColumns, true)) {
                $table->unsignedBigInteger('plan_payment_id')->nullable();
            }
            if (! in_array('next_plan_starts_at', $shopColumns, true)) {
                $table->timestamp('next_plan_starts_at')->nullable()->index();
            }
        });

        $subscriptionColumns = Schema::getColumnListing('shop_plan_payments');
        Schema::table('shop_plan_payments', function (Blueprint $table) use ($subscriptionColumns): void {
            if (! in_array('refund_amount', $subscriptionColumns, true)) {
                $table->decimal('refund_amount', 12, 2)->nullable();
            }
            if (! in_array('refund_reason', $subscriptionColumns, true)) {
                $table->text('refund_reason')->nullable();
            }
            if (! in_array('refund_status', $subscriptionColumns, true)) {
                $table->string('refund_status')->nullable()->index();
            }
            if (! in_array('paymongo_refund_id', $subscriptionColumns, true)) {
                $table->string('paymongo_refund_id')->nullable()->unique();
            }
            if (! in_array('refund_requested_at', $subscriptionColumns, true)) {
                $table->timestamp('refund_requested_at')->nullable();
            }
            if (! in_array('refunded_at', $subscriptionColumns, true)) {
                $table->timestamp('refunded_at')->nullable();
            }
        });
        if (! Schema::hasIndex('shop_plan_payments', 'shop_subscription_periods')) {
            Schema::table('shop_plan_payments', function (Blueprint $table): void {
                $table->index(['shop_id', 'status', 'period_starts_at'], 'shop_subscription_periods');
            });
        }
        foreach (['payments', 'shop_plan_payments'] as $name) {
            $columns = Schema::getColumnListing($name);
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                if (! in_array('paymongo_checked_at', $columns, true)) {
                    $table->timestamp('paymongo_checked_at')->nullable()->index();
                }
                if (! in_array('refund_reviewed_by', $columns, true)) {
                    $table->foreignId('refund_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! in_array('refund_reviewed_at', $columns, true)) {
                    $table->timestamp('refund_reviewed_at')->nullable();
                }
                if (! in_array('refund_review_notes', $columns, true)) {
                    $table->text('refund_review_notes')->nullable();
                }
                if (! in_array('refund_request_key', $columns, true)) {
                    $table->uuid('refund_request_key')->nullable();
                }
            });
        }
        if (Schema::getColumnType('payments', 'refund_reason') !== 'text') {
            Schema::table('payments', function (Blueprint $table): void {
                $table->text('refund_reason')->nullable()->change();
            });
        }
        if ($repairCoverage) {
            DB::table('shops')->orderBy('id')->chunkById(100, function ($shops): void {
                foreach ($shops as $shop) {
                    $periods = DB::table('shop_plan_payments')->where('shop_id', $shop->id)->where('status', 'approved')
                        ->where(fn ($query) => $query->whereNull('refund_status')->orWhere('refund_status', '!=', 'succeeded'));
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
    }

    /**
     * Preserve repaired data; the original billing migration owns schema rollback.
     */
    public function down(): void {}
};
