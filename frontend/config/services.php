<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | Anime-Sama FastAPI Backend
    |--------------------------------------------------------------------------
    |
    | Configuration for the Python FastAPI service that wraps the
    | anime-sama_api library. The Laravel app proxies requests to it.
    |
    */

    'anime_sama' => [
        'base_url' => env('ANIME_SAMA_BASE_URL', 'http://localhost:8001'),
        'api_key' => env('ANIME_SAMA_API_KEY', ''),
        'timeout' => (float) env('ANIME_SAMA_TIMEOUT', 25),
        'cache_ttl' => (int) env('ANIME_SAMA_CACHE_TTL', 300),
    ],

];
