<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\SeriesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/pricing', [PackageController::class, 'index'])->name('packages.index');
Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
Route::get('/series/{series}', [SeriesController::class, 'show'])->name('series.show');
Route::get('/resellers', [ResellerController::class, 'index'])->name('resellers');

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

    // Google OAuth
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated user routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Orders / checkout
    Route::get('/order/{package}', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/order/{package}', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Orders fulfilment
    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}/edit', [Admin\OrderController::class, 'edit'])->name('orders.edit');
    Route::put('orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

    Route::resource('payment-methods', Admin\PaymentMethodController::class)->except('show');

    Route::resource('movies', Admin\MovieController::class)->except('show');
    Route::resource('series', Admin\SeriesController::class)->except('show');
    Route::post('series/{series}/episodes', [Admin\EpisodeController::class, 'store'])->name('series.episodes.store');
    Route::delete('series/{series}/episodes/{episode}', [Admin\EpisodeController::class, 'destroy'])->name('series.episodes.destroy');

    Route::resource('packages', Admin\PackageController::class)->except('show');
    Route::resource('channels', Admin\ChannelController::class)->except('show');
    Route::resource('categories', Admin\CategoryController::class)->except('show');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/toggle-admin', [Admin\UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
});
