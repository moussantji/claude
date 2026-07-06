<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'home'])->name('portal.home');
Route::get('/browse', [PortalController::class, 'browse'])->name('portal.browse');
Route::get('/search', [PortalController::class, 'search'])->name('portal.search');
Route::get('/film/{id}', [PortalController::class, 'detail'])->whereNumber('id')->name('portal.detail');
Route::get('/more', [PortalController::class, 'more'])->name('portal.more');
