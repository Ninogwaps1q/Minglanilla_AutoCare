<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WebPushDelivery
{
    public function __construct(private WebPushCrypto $crypto) {}

    public function configured(): bool
    {
        return function_exists('openssl_pkey_derive')
            && filled(config('services.web_push.public_key'))
            && filled(config('services.web_push.private_key'))
            && filled(config('services.web_push.subject'));
    }

    public function audience(string $endpoint): ?string
    {
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ($parts['port'] ?? 443) !== 443) {
            return null;
        }

        if ($host !== 'fcm.googleapis.com' && ! str($host)->endsWith(['.push.services.mozilla.com', '.push.apple.com', '.notify.windows.com', '.wns.windows.com'])) {
            return null;
        }

        return 'https://'.$host;
    }

    public function send(User $user, array $message): void
    {
        if (! $this->configured() || ! $user->hasConsentFor('push')) {
            return;
        }

        $key = $this->crypto->privateKey(config('services.web_push.private_key'));
        if (! hash_equals(config('services.web_push.public_key'), $this->crypto->encode($this->crypto->publicPoint($key)))) {
            throw new RuntimeException('VAPID key pair does not match.');
        }
        $subject = config('services.web_push.subject');
        if (! str($subject)->startsWith(['https://', 'mailto:'])) {
            throw new RuntimeException('Set a VAPID contact URL or email.');
        }

        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $failed = false;
        foreach (DB::table('push_subscriptions')->where('user_id', $user->id)->lazyById() as $subscription) {
            try {
                $endpoint = decrypt($subscription->endpoint);
                $audience = $this->audience($endpoint);
                if ($audience === null) {
                    continue;
                }
                $response = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->withHeaders([
                    'Authorization' => $this->crypto->authorization($audience, $subject, $key),
                    'Content-Encoding' => 'aes128gcm',
                    'TTL' => '3600',
                ])->withBody($this->crypto->encrypt($payload, $subscription->public_key, $subscription->auth_secret), 'application/octet-stream')->post($endpoint);

                if (in_array($response->status(), [404, 410], true)) {
                    DB::table('push_subscriptions')->where('id', $subscription->id)->where('user_id', $user->id)->delete();
                } elseif (! $response->successful()) {
                    $failed = true;
                    Log::warning('web_push.provider_failed', ['subscription_id' => $subscription->id, 'status' => $response->status()]);
                }
            } catch (Throwable $exception) {
                $failed = true;
                Log::warning('web_push.delivery_failed', ['subscription_id' => $subscription->id, 'exception_type' => $exception::class]);
            }
        }
        if ($failed) {
            throw new RuntimeException('A browser push delivery failed.');
        }
    }
}
