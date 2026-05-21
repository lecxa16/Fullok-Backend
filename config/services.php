<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        'timeout' => env('ANTHROPIC_TIMEOUT', 25), // segundos
    ],

    'facturapi' => [
        // sk_test_XXXX (sandbox) o sk_user_XXXX (producción)
        'key' => env('FACTURAPI_KEY'),
    ],

];
