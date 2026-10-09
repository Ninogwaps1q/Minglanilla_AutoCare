<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function respond(array $messages): string;
}
