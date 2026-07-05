<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API - CRM Faso Digital
|--------------------------------------------------------------------------
| Authentification via Laravel Sanctum (jetons Bearer).
*/

// --- Public : messages du formulaire de contact ---
Route::post('/messages', [MessageController::class, 'store']);

// --- Authentification ---
Route::post('/login', [AuthController::class, 'login']);

// --- Routes protegees (jeton Sanctum requis) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Le parametre d'URL est nomme "id" pour correspondre aux methodes du controleur generique.
    Route::apiResource('leads', LeadController::class)->parameters(['leads' => 'id']);
    Route::apiResource('clients', ClientController::class)->parameters(['clients' => 'id']);
    Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'id']);
    Route::apiResource('products', ProductController::class)->parameters(['products' => 'id']);
    Route::apiResource('orders', OrderController::class)->parameters(['orders' => 'id']);
    Route::apiResource('posts', PostController::class)->parameters(['posts' => 'id']);

    // Messages : lecture/maj/suppression reservees aux admins (creation publique ci-dessus)
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    Route::match(['put', 'patch'], '/messages/{id}', [MessageController::class, 'update']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);

    // Parametres (ligne unique)
    Route::get('/settings', [SettingController::class, 'show']);
    Route::match(['put', 'patch'], '/settings', [SettingController::class, 'update']);

    // Statistiques du tableau de bord
    Route::get('/stats', [SettingController::class, 'stats']);
});
