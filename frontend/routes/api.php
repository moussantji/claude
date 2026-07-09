<?php

use App\Http\Controllers\AdminController;
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

// --- Public catalogue browsing (served from MySQL) ---
Route::prefix('anime')->group(function () {
    Route::get('/search', [AnimeController::class, 'search']);
    Route::get('/all-catalogues', [AnimeController::class, 'allCatalogues']);
    Route::get('/new-episodes', [AnimeController::class, 'newEpisodes']);
    Route::get('/filters', [AnimeController::class, 'filters']);
    Route::get('/catalogue', [AnimeController::class, 'catalogue']);
    Route::get('/episodes', [AnimeController::class, 'episodes']);
    Route::get('/trailer', [AnimeController::class, 'trailer']);
    Route::get('/planning', [AnimeController::class, 'planning']);
    Route::post('/request', [AnimeController::class, 'contentRequest']);
});

// --- Protected endpoints (require a valid Sanctum token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    Route::apiResource('favorites', FavoriteController::class)
        ->only(['index', 'store', 'destroy']);

    Route::apiResource('history', WatchHistoryController::class)
        ->parameters(['history' => 'watchHistory'])
        ->only(['index', 'store', 'destroy']);
});

// --- Admin endpoints (require a valid token AND is_admin) ---
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/animes', [AdminController::class, 'animes']);
    Route::get('/animes/{anime}', [AdminController::class, 'showAnime']);
    Route::put('/animes/{anime}', [AdminController::class, 'updateAnime']);
    Route::delete('/animes/{anime}', [AdminController::class, 'destroyAnime']);
    Route::post('/animes', [AdminController::class, 'storeAnime']);
    Route::post('/animes/{anime}/seasons', [AdminController::class, 'storeSeason']);
    Route::post('/seasons/{season}/episodes', [AdminController::class, 'storeEpisode']);

    Route::get('/seasons/{season}/episodes', [AdminController::class, 'seasonEpisodes']);
    Route::put('/seasons/{season}', [AdminController::class, 'updateSeason']);
    Route::delete('/seasons/{season}', [AdminController::class, 'destroySeason']);

    Route::put('/episodes/{episode}', [AdminController::class, 'updateEpisode']);
    Route::delete('/episodes/{episode}', [AdminController::class, 'destroyEpisode']);
    Route::get('/episodes/{episode}/links', [AdminController::class, 'episodeLinks']);
    Route::post('/episodes/{episode}/links', [AdminController::class, 'storeLink']);

    Route::put('/links/{link}', [AdminController::class, 'updateLink']);
    Route::delete('/links/{link}', [AdminController::class, 'destroyLink']);

    // Planning management
    Route::get('/planning', [AdminController::class, 'planningIndex']);
    Route::post('/planning', [AdminController::class, 'storePlanning']);
    Route::put('/planning/{planning}', [AdminController::class, 'updatePlanning']);
    Route::delete('/planning/{planning}', [AdminController::class, 'destroyPlanning']);

    // Content requests management
    Route::get('/content-requests', [AdminController::class, 'contentRequests']);
    Route::put('/content-requests/{contentRequest}', [AdminController::class, 'updateContentRequest']);
    Route::delete('/content-requests/{contentRequest}', [AdminController::class, 'destroyContentRequest']);
});
