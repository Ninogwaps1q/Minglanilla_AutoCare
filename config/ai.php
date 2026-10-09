<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY', env('AI_API_KEY')),
    'model' => env('AI_MODEL', 'gemini-2.0-flash'),
    'timeout' => (int) env('AI_TIMEOUT', 15),
];
