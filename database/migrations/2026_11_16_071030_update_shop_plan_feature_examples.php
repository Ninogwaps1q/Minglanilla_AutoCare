<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $examples = [
            'free' => [
                'old' => ['Shop name and profile', 'Services in the directory', 'Map location after approval'],
                'new' => ['Shop name and services on the map', 'Public shop profile', 'Customer contact details'],
            ],
            'basic' => [
                'old' => ['Everything in Free', 'Priority in default shop results', 'Basic badge on shop listings'],
                'new' => ['Everything in Free', 'Online bookings and repair management', 'Priority in default shop results'],
            ],
            'premium' => [
                'old' => ['Everything in Basic', 'Top of default shop results', 'Premium badge on shop listings'],
                'new' => ['Everything in Basic', 'Reports, inventory, and team tools', 'Top results and Premium badge'],
            ],
        ];

        foreach ($examples as $slug => $example) {
            $plan = DB::table('shop_plans')->where('slug', $slug)->first();
            if ($plan && json_decode($plan->features, true) === $example['old']) {
                DB::table('shop_plans')->where('slug', $slug)->update(['features' => json_encode($example['new'])]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // User-edited plan copy is intentionally preserved.
    }
};
