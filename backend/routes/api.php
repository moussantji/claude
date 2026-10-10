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
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API - CRM Manden Baoubab
|--------------------------------------------------------------------------
| Authentification via Laravel Sanctum en mode SPA (cookie de session).
| Le front recupere d'abord le cookie CSRF via GET /sanctum/csrf-cookie.
*/

// --- Public (avec limite anti-spam/anti-bruteforce) ---
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:20,1'); // formulaire de contact
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1');      // checkout e-commerce

// --- Routes protegees (session Sanctum requise) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Le parametre d'URL est nomme "id" pour correspondre aux methodes du controleur generique.
    Route::apiResource('leads', LeadController::class)->parameters(['leads' => 'id']);
    Route::apiResource('clients', ClientController::class)->parameters(['clients' => 'id']);
    Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'id']);
    Route::apiResource('products', ProductController::class)->parameters(['products' => 'id']);
    Route::apiResource('posts', PostController::class)->parameters(['posts' => 'id']);
    Route::apiResource('users', UserController::class)->parameters(['users' => 'id']);

    // Commandes : creation publique ci-dessus, gestion reservee aux admins
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::match(['put', 'patch'], '/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Messages : creation publique ci-dessus, gestion reservee aux admins
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
