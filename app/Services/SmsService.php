<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsService
{
    public function configured(): bool
    {
        return match (config('services.sms.driver')) {
            'demo' => true,
            'semaphore' => filled(config('services.semaphore.api_key')),
            default => false,
        };
    }

    public function phone(?string $phone): ?string
    {
        $digits = preg_replace('/[\s()-]+/', '', $phone ?? '');

        return preg_match('/^(?:0|\+?63)(9\d{9})$/D', $digits, $matches) ? '63'.$matches[1] : null;
    }

    /** @return array{accepted: bool, response: array, cost: ?float} */
    public function send(string $phone, string $message): array
    {
        return match (config('services.sms.driver')) {
            'demo' => ['accepted' => false, 'response' => ['provider' => 'demo', 'status' => 'simulated'], 'cost' => 0.0],
            'semaphore' => $this->sendWithSemaphore($phone, $message),
            default => ['accepted' => false, 'response' => ['status' => 'disabled'], 'cost' => null],
        };
    }

    /** @return array{accepted: bool, response: array, cost: ?float} */
    private function sendWithSemaphore(string $phone, string $message): array
    {
        $attributes = ['apikey' => config('services.semaphore.api_key'), 'number' => $phone, 'message' => $message];
        if (config('services.semaphore.sender_name')) {
            $attributes['sendername'] = config('services.semaphore.sender_name');
        }

        $result = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(10)
            ->retry(2, fn (int $attempt, RequestException $failure): int => 1000 * (int) ($failure->response->header('Retry-After') ?: '1'),
                function (Throwable $failure): bool {
                    if (! $failure instanceof RequestException || $failure->response->status() !== 429 || $failure->response->json('0.message_id')) {
                        return false;
                    }
                    $seconds = $failure->response->header('Retry-After');

                    return $seconds === '' || (ctype_digit($seconds) && (int) $seconds <= 5);
                }, throw: false)
            ->post('https://api.semaphore.co/api/v4/messages', $attributes);
        $id = $result->json('0.message_id');
        $status = strtolower((string) $result->json('0.status'));
        $cost = $result->json('0.cost');
        $id = is_numeric($id) ? (string) $id : null;
        $status = preg_match('/^[a-z_]{1,24}$/D', $status) ? $status : null;

        return [
            'accepted' => $result->successful() && $id !== null && ! in_array($status, ['failed', 'error', 'rejected', 'refunded'], true),
            'response' => ['http_status' => $result->status(), 'message_id' => $id, 'status' => $status],
            'cost' => is_numeric($cost) && (float) $cost >= 0 && (float) $cost < 1_000_000 ? (float) $cost : null,
        ];
    }
}
