<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const CACHE_KEY = 'operations.queue_heartbeat';

    public int $uniqueFor = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now()->timestamp, now()->addMinutes(10));
    }
}
