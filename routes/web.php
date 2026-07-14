<?php

use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TorrentController::class, 'index'])->name('torrent.index');
Route::post('/edit', [TorrentController::class, 'edit'])->name('torrent.edit');
Route::get('/result', [TorrentController::class, 'result'])->name('torrent.result');
Route::get('/download', [TorrentController::class, 'download'])->name('torrent.download');
