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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'hablame' => [
        'api_key' => env('HABLAME_API_KEY'),
        'api_url' => env('HABLAME_API_URL', 'https://www.hablame.co/api'),
        'from' => env('HABLAME_FROM', 'SIGMA'),
        'sandbox_mode' => env('HABLAME_SANDBOX_MODE', false),
    ],

    'registraduria' => [
        'url' => env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757'),
        'live_enabled' => env('REGISTRADURIA_LIVE_ENABLED', true),
        'probe_url' => env('REGISTRADURIA_PROBE_URL', 'https://wsp.registraduria.gov.co/censo/consultar/'),
    ],

    'infovotantes' => [
        'url' => env('INFOVOTANTES_SERVICE_URL', env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757')),
        'live_enabled' => env('INFOVOTANTES_LIVE_ENABLED', true),
        'probe_url' => env('INFOVOTANTES_PROBE_URL', 'https://eleccionescolombia.registraduria.gov.co/identificacion'),
    ],

];
