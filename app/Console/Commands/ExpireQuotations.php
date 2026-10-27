<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use App\Models\RepairJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
                    $expired += DB::transaction(function () use ($quotation): int {
                        $current = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
                        if ($current->status !== 'sent' || ! $current->valid_until?->isBefore(today())) {
                            return 0;
                        }
                        $current->update(['status' => 'expired']);
                        if ($current->is_supplemental && $current->repair_job_id) {
                            $job = RepairJob::query()->lockForUpdate()->findOrFail($current->repair_job_id);
                            if ($job->status === 'waiting_approval') {
                                $nextStatus = $current->previous_job_status ?: 'approved';
                                $job->update(['status' => $nextStatus]);
                                $job->statusHistories()->create([
                                    'from_status' => 'waiting_approval', 'to_status' => $nextStatus,
                                    'changed_by' => null, 'changed_at' => now(),
                                    'note' => 'Revised estimate '.$current->quotation_number.' expired before customer approval',
                                ]);
                            }
                        }
                        $current->customer->notifications()->create([
                            'type' => 'quotation_expired',
                            'title' => 'Quotation expired',
                            'message' => "Quotation {$current->quotation_number} has expired.",
                            'data' => ['quotation_id' => $current->id],
                        ]);

                        return 1;
                    });
                }
            });

        $this->info("Expired {$expired} quotation(s).");

        return self::SUCCESS;
    }
}
