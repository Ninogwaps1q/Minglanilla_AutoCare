<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiProvider implements AiProviderInterface
{
    public function __construct(private AiServiceStatus $status) {}

    /** @param array<int, array{role: string, content: string}> $messages */
    public function respond(array $messages): string
    {
        $apiKey = config('ai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->status->record('unconfigured');

            throw new RuntimeException('The AI assistant is not configured yet. Please contact the administrator.');
        }

        $systemInstruction = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");
        $contents = collect($messages)
            ->reject(fn (array $message): bool => $message['role'] === 'system')
            ->map(fn (array $message): array => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ])->values()->all();

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => $contents,
        ];

        // Gemini 3 models default to medium reasoning, which can be slow for
        // this short customer-support chat. Keep replies quick and predictable.
        if (str_starts_with((string) config('ai.model'), 'gemini-3.')) {
            $payload['generationConfig'] = [
                'thinkingConfig' => ['thinkingLevel' => config('ai.thinking_level', 'low')],
                'maxOutputTokens' => 1024,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(3)
                ->timeout((int) config('ai.timeout', 30))
                ->retry(
                    max(1, min(3, (int) config('ai.retry_attempts', 3))),
                    fn (int $attempt): int => 300 * (2 ** ($attempt - 1)) + random_int(0, 150),
                    fn (Throwable $exception): bool => $exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 502, 503, 504], true),
                    throw: false,
                )
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('ai.model').':generateContent', $payload);
        } catch (ConnectionException $exception) {
            $this->status->record('connection_error');
            Log::warning('Gemini AI request could not connect.', [
                'model' => config('ai.model'),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('The AI service could not be reached. Check the server connection and try again.');
        }

        $result = $response->json();

        if (! $response->successful()) {
            $apiStatus = data_get($result, 'error.status');
            Log::warning('Gemini AI request failed.', [
                'model' => config('ai.model'),
                'http_status' => $response->status(),
                'api_status' => is_string($apiStatus) ? $apiStatus : null,
                'api_code' => data_get($result, 'error.code'),
            ]);
            $this->status->record(match ($response->status()) {
                400 => 'invalid_request',
                401, 403 => 'configuration_error',
                404 => 'model_unavailable',
                429 => 'rate_limited',
                502, 503, 504 => 'provider_busy',
                default => 'provider_error',
            }, $response->status());

            $message = match ($response->status()) {
                400 => 'The AI could not process that message. Please rephrase it and try again.',
                401, 403 => 'The AI service configuration needs attention. Please contact the administrator.',
                404 => 'The configured AI model is unavailable. Please contact the administrator.',
                429 => 'The AI assistant is busy right now. Please wait a moment and try again.',
                default => 'The AI service is temporarily unavailable. Please try again shortly.',
            };

            throw new RuntimeException($message);
        }

        $parts = data_get($result, 'candidates.0.content.parts', []);
        $text = collect($parts)
            ->filter(fn (mixed $part): bool => is_array($part)
                && ($part['thought'] ?? false) !== true
                && is_string($part['text'] ?? null)
                && trim($part['text']) !== '')
            ->pluck('text')
            ->implode(PHP_EOL);

        if (trim($text) === '') {
            $this->status->record('no_reply', $response->status());
            Log::warning('Gemini returned no customer-facing text.', [
                'model' => config('ai.model'),
                'finish_reason' => data_get($result, 'candidates.0.finishReason'),
                'block_reason' => data_get($result, 'promptFeedback.blockReason'),
            ]);

            throw new RuntimeException('The AI could not prepare a reply. Please rephrase your question and try again.');
        }

        $this->status->record('healthy', $response->status());

        return $text;
    }
}
