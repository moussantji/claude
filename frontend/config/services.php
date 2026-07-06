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
    | Anime-Sama scraper
    |--------------------------------------------------------------------------
    |
    | Configuration for the native PHP scraper (App\Services\AnimeSama\Scraper)
    | that reads the public anime-sama website directly.
    |
    */

    'anime_sama' => [
        // Base URL of the anime-sama site (trailing slash added automatically).
        'site_url' => env('ANIME_SAMA_SITE_URL', 'https://anime-sama.fr/'),
        // Provider used by /anime/site/resolve to auto-detect the current domain.
        'provider_url' => env('ANIME_SAMA_PROVIDER_URL', 'https://anime-sama.pw/'),
        // Default language preference order for the "best" player resolution.
        'prefer_languages' => env('ANIME_SAMA_PREFER_LANGUAGES', 'VF,VOSTFR'),
        // User-Agent sent to anime-sama.
        'user_agent' => env('ANIME_SAMA_USER_AGENT', 'Mozilla/5.0 (compatible; anime-sama-laravel/1.0)'),
        // Outgoing HTTP timeout in seconds.
        'timeout' => (float) env('ANIME_SAMA_TIMEOUT', 20),
        // Response cache TTL in seconds (0 disables caching).
        'cache_ttl' => (int) env('ANIME_SAMA_CACHE_TTL', 300),
    ],

];
