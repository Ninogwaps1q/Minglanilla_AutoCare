<?php

namespace App\Http\Controllers;

use App\Jobs\QueueHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class OperationsHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $check = 'database';
        try {
            DB::select('select 1');
            $check = 'queue';
            Queue::size();
            if (config('monitoring.require_queue_heartbeat') && config('queue.default') !== 'sync'
                && (int) Cache::get(QueueHeartbeat::CACHE_KEY, 0) < now()->subMinutes(5)->timestamp) {
                return response()->json(['status' => 'unavailable'], 503, ['Cache-Control' => 'no-store']);
            }
        } catch (Throwable $exception) {
            Log::channel('operations')->warning('health.check_failed', ['check' => $check, 'exception_type' => $exception::class]);

            return response()->json(['status' => 'unavailable'], 503, ['Cache-Control' => 'no-store']);
        }

        return response()->json(['status' => 'ok'], 200, ['Cache-Control' => 'no-store']);
    }
}
