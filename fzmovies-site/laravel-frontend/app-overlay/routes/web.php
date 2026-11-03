<?php

use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');
Route::get('/browse', [MovieController::class, 'browse'])->name('movies.browse');
Route::get('/movie', [MovieController::class, 'show'])->name('movies.show');
Route::get('/download', [MovieController::class, 'download'])->name('movies.download');
