<?php

use App\Http\Controllers\AnimeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\WatchHistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Stateless JSON API. Authentication uses Laravel Sanctum personal access
| tokens: clients send `Authorization: Bearer <token>`.
|
*/

// --- Public authentication endpoints ---
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// --- Public catalogue browsing (scraped natively from anime-sama) ---
Route::prefix('anime')->group(function () {
    Route::get('/search', [AnimeController::class, 'search']);
    Route::get('/new-episodes', [AnimeController::class, 'newEpisodes']);
    Route::get('/catalogue', [AnimeController::class, 'catalogue']);
    Route::get('/episodes', [AnimeController::class, 'episodes']);
    Route::get('/trailer', [AnimeController::class, 'trailer']);
    Route::get('/site', [AnimeController::class, 'site']);
    Route::post('/site/resolve', [AnimeController::class, 'resolveSite']);
});

// --- Protected endpoints (require a valid Sanctum token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

    Route::apiResource('favorites', FavoriteController::class)
        ->only(['index', 'store', 'destroy']);

    Route::apiResource('history', WatchHistoryController::class)
        ->parameters(['history' => 'watchHistory'])
        ->only(['index', 'store', 'destroy']);
});
