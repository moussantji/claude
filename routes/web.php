<?php

use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TorrentController::class, 'index'])->name('torrent.index');
Route::post('/edit', [TorrentController::class, 'edit'])->name('torrent.edit');
Route::get('/result', [TorrentController::class, 'result'])->name('torrent.result');
Route::get('/download', [TorrentController::class, 'download'])->name('torrent.download');
Route::get('/generate', [TorrentController::class, 'generateForm'])->name('torrent.generate');
Route::post('/generate', [TorrentController::class, 'generate'])->name('torrent.generate.post');
Route::get('/split', [TorrentController::class, 'splitForm'])->name('torrent.split');
Route::post('/split', [TorrentController::class, 'split'])->name('torrent.split.post');
