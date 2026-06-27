<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/pricing', [PackageController::class, 'index'])->name('packages.index');
Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');

// Personalised M3U playlist consumed by IPTV players (credential based).
Route::get('/playlist.m3u', [PlaylistController::class, 'm3u'])->name('playlist.m3u');

// Language switcher (EN / FR).
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Guest routes (auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated user routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/subscribe/{package}', [SubscriptionController::class, 'show'])->name('subscribe.show');
    Route::post('/subscribe/{package}', [SubscriptionController::class, 'store'])->name('subscribe.store');
    Route::patch('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscribe.cancel');
});

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('packages', Admin\PackageController::class)->except('show');
    Route::resource('channels', Admin\ChannelController::class)->except('show');
    Route::resource('categories', Admin\CategoryController::class)->except('show');

    Route::get('subscriptions', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::put('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'update'])->name('subscriptions.update');
    Route::delete('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/toggle-admin', [Admin\UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
});
