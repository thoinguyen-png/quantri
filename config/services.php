<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ocr' => [
        'enabled' => env('OCR_ENABLED', false),
        'provider' => env('OCR_PROVIDER', 'disabled'),
        'service_url' => env('OCR_SERVICE_URL', 'http://127.0.0.1:8100/ocr'),
        'health_url' => env('OCR_HEALTH_URL', 'http://127.0.0.1:8100/health'),
        'timeout' => (int) env('OCR_TIMEOUT', 120),
        'paddle' => [
            'python' => env('OCR_PYTHON_BINARY', 'python'),
            'script' => env('OCR_PADDLE_SCRIPT', 'scripts/paddle_ocr_vneid.py'),
            'timeout' => env('OCR_TIMEOUT', 120),
        ],
    ],

];
