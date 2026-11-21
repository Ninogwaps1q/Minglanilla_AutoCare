<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoCheckout
{
    public function configured(): bool
    {
        return filled(config('services.paymongo.secret_key')) && filled(config('services.paymongo.webhook_secret'));
    }

    /** @return array{id: string, url: string} */
    public function create(string $reference, string $name, int $amountCentavos, string $email, string $successUrl, string $cancelUrl): array
    {
        if (! $this->configured() || $amountCentavos < 100) {
            throw new RuntimeException('PayMongo checkout is unavailable. Please contact support.');
        }

        $paymentMethods = array_map(static function (string $method): string {
            $method = trim($method);

            return $method === 'shopeepay' ? 'shopee_pay' : $method;
        }, config('services.paymongo.payment_methods', []));

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->withHeaders(['Idempotency-Key' => $reference])
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://api.paymongo.com/v2/checkout_sessions', [
                'data' => ['attributes' => [
                    'line_items' => [['name' => $name, 'amount' => $amountCentavos, 'currency' => 'PHP', 'quantity' => 1]],
                    'payment_method_types' => array_values(array_unique(array_filter($paymentMethods))),
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'reference_number' => $reference,
                    'send_email_receipt' => true,
                    'billing' => ['email' => $email],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo could not start checkout. Please try again later.', $response->status());
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.checkout_url');
        if (! is_string($id) || ! str_starts_with($id, 'cs_') || ! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.paymongo.com') {
            throw new RuntimeException('PayMongo returned an invalid checkout session.');
        }

        return ['id' => $id, 'url' => $url];
    }

    public function expire(string $sessionId): bool
    {
        if (! $this->configured() || ! preg_match('/^cs_[A-Za-z0-9_-]+$/', $sessionId)) {
            return false;
        }

        try {
            return Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.paymongo.com/v1/checkout_sessions/'.$sessionId.'/expire')
                ->successful();
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function retrieve(string $sessionId): ?array
    {
        if (! $this->configured() || ! preg_match('/^cs_[A-Za-z0-9_-]+$/', $sessionId)) {
            return null;
        }

        try {
            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')->connectTimeout(3)->timeout(10)
                ->get('https://api.paymongo.com/v1/checkout_sessions/'.$sessionId);

            return $response->successful() && $response->json('data.id') === $sessionId ? $response->json('data') : null;
        } catch (ConnectionException $exception) {
            report($exception);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function refund(string $paymentId, int $amountCentavos, string $reason, string $idempotencyKey): ?array
    {
        if (! $this->configured() || ! preg_match('/^pay_[A-Za-z0-9_-]+$/', $paymentId) || $amountCentavos < 1) {
            return null;
        }

        try {
            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])->connectTimeout(3)->timeout(10)
                ->post('https://api.paymongo.com/v1/refunds', ['data' => ['attributes' => ['payment_id' => $paymentId, 'amount' => $amountCentavos, 'reason' => $reason]]]);

            if ($response->clientError() && ! in_array($response->status(), [408, 409, 429], true)) {
                throw new RuntimeException('PayMongo declined this refund. Check the payment method, refund eligibility, and available balance in PayMongo.');
            }

            return $response->successful() ? $response->json('data') : null;
        } catch (ConnectionException $exception) {
            report($exception);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function retrieveRefund(string $refundId): ?array
    {
        if (! $this->configured() || ! preg_match('/^ref_[A-Za-z0-9_-]+$/', $refundId)) {
            return null;
        }
        try {
            $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')->connectTimeout(3)->timeout(10)
                ->get('https://api.paymongo.com/v1/refunds/'.$refundId);

            return $response->successful() && $response->json('data.id') === $refundId ? $response->json('data') : null;
        } catch (ConnectionException $exception) {
            report($exception);

            return null;
        }
    }
}
