<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Movie API (Python / FastAPI) base URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the FastAPI microservice that wraps the fzmovies-api SDK.
    | In docker-compose this resolves to the "movie-api" service name.
    |
    */

    'base_url' => env('MOVIE_API_URL', 'http://localhost:8000'),

    // HTTP client timeout in seconds (scraping can be slow).
    'timeout' => (int) env('MOVIE_API_TIMEOUT', 45),
];
