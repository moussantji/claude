<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MovieApiController;
use Illuminate\Support\Facades\Route;

// Public: obtain a Sanctum token.
Route::post('/login', [AuthController::class, 'login']);

// Protected: everything else requires a valid Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (\Illuminate\Http\Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/search', [MovieApiController::class, 'search']);
    Route::get('/browse', [MovieApiController::class, 'browse']);
    Route::get('/movie-files', [MovieApiController::class, 'movieFiles']);
    Route::get('/download-links', [MovieApiController::class, 'downloadLinks']);
});
