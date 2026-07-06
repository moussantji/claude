<?php

use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');
Route::get('/browse', [MovieController::class, 'browse'])->name('movies.browse');
Route::get('/movie', [MovieController::class, 'show'])->name('movies.show');
Route::get('/download', [MovieController::class, 'download'])->name('movies.download');

// Infinite scroll (AJAX) + embedded player + streaming proxy
Route::get('/more', [MovieController::class, 'more'])->name('movies.more');
Route::get('/watch', [MovieController::class, 'watch'])->name('movies.watch');
Route::get('/stream/{token}', [MovieController::class, 'stream'])->name('movies.stream');
