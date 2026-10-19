<?php

namespace App\Console\Commands;

use App\Models\EmergencyRequest;
use App\Services\EmergencyDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReassignUnansweredEmergencyRequests extends Command
{
    protected $signature = 'app:reassign-unanswered-emergency-requests';

    protected $description = 'Reassign roadside requests that have not been accepted within 15 minutes.';

    public function handle(EmergencyDispatchService $dispatchService): int
    {
        $reassigned = 0;
        $cutoff = now()->subMinutes(15);

        EmergencyRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('assigned_shop_id')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($dispatchService, $cutoff, &$reassigned): void {
                foreach ($requests as $request) {
                    DB::transaction(function () use ($request, $dispatchService, $cutoff, &$reassigned): void {
                        $lockedRequest = EmergencyRequest::query()->lockForUpdate()->findOrFail($request->id);
                        if ($lockedRequest->status !== 'pending' || $lockedRequest->assigned_shop_id === null || $lockedRequest->updated_at->greaterThan($cutoff)) {
                            return;
                        }

                        $dispatchService->reassign($lockedRequest, 'timed_out');
                        $reassigned++;
                    });
                }
            });

        $this->info("Reviewed {$reassigned} unanswered roadside request(s).");

        return self::SUCCESS;
    }
}
