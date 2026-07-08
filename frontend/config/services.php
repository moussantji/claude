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
    | anime-sama_api library (https://github.com/Sky-NiniKo/anime-sama_api).
    | The Laravel app proxies requests to it. Trailer lookups (TMDB) are
    | handled by the backend, so no TMDB config is needed here.
    |
    */

    'anime_sama' => [
        'base_url' => env('ANIME_SAMA_BASE_URL', 'http://localhost:8001'),
        'api_key' => env('ANIME_SAMA_API_KEY', ''),
        'timeout' => (float) env('ANIME_SAMA_TIMEOUT', 25),

        // Persist API responses (incl. episode player/"vid" links) in the DB so
        // the site serves from the database and only hits anime-sama when data
        // is missing or stale. On upstream failure, stale data is served
        // (stale-while-error) so the app keeps working and never hammers the
        // source. Set ANIME_SAMA_STORE=false to disable and go straight through.
        'store' => filter_var(env('ANIME_SAMA_STORE', true), FILTER_VALIDATE_BOOLEAN),

        // Serve search from the ingested catalogue snapshot (no upstream call)
        // once `anime:ingest` has populated it. Falls back to a live proxied
        // search when no snapshot exists.
        'local_search' => filter_var(env('ANIME_SAMA_LOCAL_SEARCH', true), FILTER_VALIDATE_BOOLEAN),

        // Freshness window per response type, in seconds. Episode/player links
        // rotate more often, so they use a shorter TTL.
        'ttl' => [
            'search' => (int) env('ANIME_SAMA_TTL_SEARCH', 21600),        // 6h
            'catalogue' => (int) env('ANIME_SAMA_TTL_CATALOGUE', 43200),  // 12h
            'catalogues' => (int) env('ANIME_SAMA_TTL_CATALOGUES', 86400), // 24h (full snapshot)
            'episodes' => (int) env('ANIME_SAMA_TTL_EPISODES', 3600),     // 1h
            'new_episodes' => (int) env('ANIME_SAMA_TTL_NEW_EPISODES', 900), // 15m
            'planning' => (int) env('ANIME_SAMA_TTL_PLANNING', 21600),    // 6h
            'trailer' => (int) env('ANIME_SAMA_TTL_TRAILER', 2592000),    // 30d
            'default' => (int) env('ANIME_SAMA_CACHE_TTL', 21600),        // 6h
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vidmoly (upload your OWN media)
    |--------------------------------------------------------------------------
    |
    | Used by the media:upload command to upload files you own to your vidmoly
    | account and store the resulting embed links in the DB. Endpoints follow
    | the standard XFileSharing upload flow (get upload server -> POST file);
    | confirm/adjust them against your account's API panel if needed.
    |
    */

    'vidmoly' => [
        'api_key' => env('VIDMOLY_API_KEY', ''),
        'api_base' => rtrim(env('VIDMOLY_API_BASE', 'https://api.vidmoly.to'), '/'),
        'embed_base' => rtrim(env('VIDMOLY_EMBED_BASE', 'https://vidmoly.to'), '/'),
        // Uploads can be large, so allow a long timeout (seconds).
        'timeout' => (int) env('VIDMOLY_TIMEOUT', 900),
    ],

];
