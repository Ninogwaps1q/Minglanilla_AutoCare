<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    public function respond(array $messages): string
    {
        $apiKey = config('ai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('ai.timeout', 15))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('ai.model'),
                    'input' => $messages,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        $text = data_get($response->json(), 'output.0.content.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('AI Assistant is temporarily unavailable.');
        }

        return $text;
    }
}
