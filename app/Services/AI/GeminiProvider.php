<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProviderInterface
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function respond(array $messages): string
    {
        $apiKey = config('ai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
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

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(3)
                ->timeout((int) config('ai.timeout', 15))
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('ai.model').':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $contents,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! $response->successful() || ! is_string($text) || $text === '') {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        return $text;
    }
}
