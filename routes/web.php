<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// All torrent routes protected by auth
Route::middleware(['auth'])->group(function () {

    // Dashboard / main page
    Route::get('/dashboard', [TorrentController::class, 'dashboard'])->name('dashboard');

    // Torrent editor
    Route::get('/edit', [TorrentController::class, 'index'])->name('torrent.index');
    Route::post('/edit', [TorrentController::class, 'edit'])->name('torrent.edit');

    // Split + auto-randomize
    Route::get('/split', [TorrentController::class, 'splitForm'])->name('torrent.split');
    Route::post('/split', [TorrentController::class, 'split'])->name('torrent.split.post');

    // Generate random torrent
    Route::get('/generate', [TorrentController::class, 'generateForm'])->name('torrent.generate');
    Route::post('/generate', [TorrentController::class, 'generate'])->name('torrent.generate.post');

    // Results & download
    Route::get('/result', [TorrentController::class, 'result'])->name('torrent.result');
    Route::get('/download', [TorrentController::class, 'download'])->name('torrent.download');

    // Magnet link resolution
    Route::get('/magnet', [TorrentController::class, 'magnetForm'])->name('torrent.magnet');
    Route::post('/magnet', [TorrentController::class, 'magnet'])->name('torrent.magnet.post');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
