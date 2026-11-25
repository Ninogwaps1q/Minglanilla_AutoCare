<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    public function phone(?string $phone): ?string
    {
        $digits = preg_replace('/[\s()-]+/', '', $phone ?? '');

        return preg_match('/^(?:0|\+?63)(9\d{9})$/D', $digits, $matches) ? '63'.$matches[1] : null;
    }

    public function send(string $phone, string $message): void
    {
        $attributes = ['apikey' => config('services.semaphore.api_key'), 'number' => $phone, 'message' => $message];
        if (config('services.semaphore.sender_name')) {
            $attributes['sendername'] = config('services.semaphore.sender_name');
        }

        $result = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(10)
            ->post('https://api.semaphore.co/api/v4/messages', $attributes);
        if (! $result->successful() || ! $result->json('0.message_id')
            || in_array(strtolower((string) $result->json('0.status')), ['failed', 'error', 'rejected'], true)) {
            throw new RuntimeException('Semaphore did not accept the message.');
        }
    }
}
