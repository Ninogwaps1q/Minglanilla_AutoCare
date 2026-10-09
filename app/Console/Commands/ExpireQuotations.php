<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'app:expire-quotations';

    protected $description = 'Expire sent quotations whose validity date has passed.';

    public function handle(): int
    {
        $expired = 0;

        Quotation::query()
            ->where('status', 'sent')
            ->whereDate('valid_until', '<', today())
            ->with('customer:id')
            ->orderBy('id')
            ->chunkById(100, function ($quotations) use (&$expired): void {
                foreach ($quotations as $quotation) {
                    $updated = Quotation::query()
                        ->whereKey($quotation->id)
                        ->where('status', 'sent')
                        ->update(['status' => 'expired']);

                    if ($updated === 0) {
                        continue;
                    }

                    $quotation->customer->notifications()->create([
                        'type' => 'quotation_expired',
                        'title' => 'Quotation expired',
                        'message' => "Quotation {$quotation->quotation_number} has expired.",
                        'data' => ['quotation_id' => $quotation->id],
                    ]);
                    $expired++;
                }
            });

        $this->info("Expired {$expired} quotation(s).");

        return self::SUCCESS;
    }
}
