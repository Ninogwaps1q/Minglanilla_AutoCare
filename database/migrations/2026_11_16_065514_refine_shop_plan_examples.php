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
        $examples = [
            'basic' => [
                'old' => ['Everything in Free', 'Priority placement above Free listings', 'Shop operations workspace'],
                'new' => ['Everything in Free', 'Priority in default shop results', 'Basic badge on shop listings'],
            ],
            'premium' => [
                'old' => ['Everything in Basic', 'Top placement in the directory', 'Premium badge on shop listings'],
                'new' => ['Everything in Basic', 'Top of default shop results', 'Premium badge on shop listings'],
            ],
        ];

        foreach ($examples as $slug => $example) {
            $plan = DB::table('shop_plans')->where('slug', $slug)->first();
            if ($plan && json_decode($plan->features, true) === $example['old']) {
                DB::table('shop_plans')->where('slug', $slug)->update(['features' => json_encode($example['new'])]);
            }
        }

        if (Schema::hasColumn('shop_plans', 'trial_days')) {
            Schema::table('shop_plans', function (Blueprint $table) {
                $table->dropColumn('trial_days');
            });
        }
    }

    /**
     * Existing admin-edited plan details cannot be safely restored on rollback.
     */
    public function down(): void {}
};
