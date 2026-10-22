<?php

namespace App\Services\Messaging;

use Illuminate\Database\QueryException;

class MessageStorage
{
    public static function isUnavailable(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'message_threads')
            && (in_array((int) ($exception->errorInfo[1] ?? 0), [1146, 1932], true)
                || str_contains($message, 'no such table: message_threads'));
    }
}
