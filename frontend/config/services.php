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
    | Vidmoly (upload your OWN media)
    |--------------------------------------------------------------------------
    |
    | Used by the media:upload command to upload files you own to your vidmoly
    | account and store the resulting embed links in the DB.
    |
    */

    'vidmoly' => [
        'api_key' => env('VIDMOLY_API_KEY', ''),
        'api_base' => rtrim(env('VIDMOLY_API_BASE', 'https://api.vidmoly.to'), '/'),
        'embed_base' => rtrim(env('VIDMOLY_EMBED_BASE', 'https://vidmoly.to'), '/'),
        'timeout' => (int) env('VIDMOLY_TIMEOUT', 900),
    ],

];
