<?php

namespace App\Services\AI;

final class AiResponseFormatter
{
    public static function format(string $response): string
    {
        $formatted = str_replace(["\r\n", "\r"], "\n", trim($response));
        $formatted = preg_replace('/^[ \t]*```[^\n]*$/mu', '', $formatted) ?? $formatted;
        $formatted = preg_replace('/^[ \t]{0,3}#{1,6}[ \t]*/mu', '', $formatted) ?? $formatted;
        $formatted = preg_replace('/^[ \t]*[*+-][ \t]+/mu', '• ', $formatted) ?? $formatted;
        $formatted = str_replace(['**', '__', '`', '*'], '', $formatted);
        $formatted = preg_replace('/[ \t]+\n/u', "\n", $formatted) ?? $formatted;
        $formatted = preg_replace('/\n{3,}/u', "\n\n", $formatted) ?? $formatted;

        return trim($formatted);
    }
}
