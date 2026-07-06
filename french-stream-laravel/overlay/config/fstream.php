<?php

return [

    /*
    |--------------------------------------------------------------------------
    | french-stream source website
    |--------------------------------------------------------------------------
    |
    | Base URL of the site being scraped. If a mirror changes, update it here
    | (or via the FSTREAM_BASE_URL env var). The site runs DataLife Engine
    | behind Cloudflare.
    |
    */

    'base_url' => env('FSTREAM_BASE_URL', 'https://french-stream.one'),

    // Referer sent with every request.
    'referer' => env('FSTREAM_REFERER', 'https://french-stream.one/'),

    // HTTP timeouts (seconds).
    'timeout' => (int) env('FSTREAM_TIMEOUT', 20),
    'connect_timeout' => (int) env('FSTREAM_CONNECT_TIMEOUT', 10),

    // Streaming proxy timeout (0 = unlimited).
    'stream_timeout' => (int) env('FSTREAM_STREAM_TIMEOUT', 0),

    // Cache (seconds) for scraped listings, to reduce load & speed up the UI.
    'cache_ttl' => (int) env('FSTREAM_CACHE_TTL', 600),
];
