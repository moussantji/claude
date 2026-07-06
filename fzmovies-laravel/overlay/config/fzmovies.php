<?php

return [

    /*
    |--------------------------------------------------------------------------
    | fzmovies source website
    |--------------------------------------------------------------------------
    |
    | Base URL of the site being scraped. Mirrors: fzmovies.live, fzmovies.host.
    |
    */

    'base_url' => env('FZMOVIES_BASE_URL', 'https://fzmovies.live'),

    // Referer sent with every request (matches the reference SDK).
    'referer' => env('FZMOVIES_REFERER', 'https://fzmovies.net/'),

    // HTTP timeouts (seconds).
    'timeout' => (int) env('FZMOVIES_TIMEOUT', 20),
    'connect_timeout' => (int) env('FZMOVIES_CONNECT_TIMEOUT', 10),

    // Category -> catID mapping used by the browse filters.
    'category_ids' => [
        'Bollywood' => 1,
        'Hollywood' => 2,
    ],
];
