<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiServiceStatus
{
    private const CACHE_KEY = 'ai.service_status';

    public function record(string $state, ?int $httpStatus = null): void
    {
        try {
            Cache::put(self::CACHE_KEY, [
                'state' => $state,
                'http_status' => $httpStatus,
                'model' => (string) config('ai.model'),
                'checked_at' => now()->toIso8601String(),
            ], now()->addDays(2));
        } catch (Throwable $exception) {
            Log::warning('AI status cache update failed.', ['exception' => $exception::class]);
        }
    }

    /** @return array{state: string, http_status: int|null, model: string|null, checked_at: string|null} */
    public function snapshot(): array
    {
        $unavailable = [
            'state' => 'not_checked',
            'http_status' => null,
            'model' => null,
            'checked_at' => null,
        ];

        try {
            return Cache::get(self::CACHE_KEY, $unavailable);
        } catch (Throwable $exception) {
            Log::warning('AI status cache read failed.', ['exception' => $exception::class]);

            return $unavailable;
        }
    }
}
