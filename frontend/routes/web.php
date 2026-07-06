<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'description' => 'Anime-Sama Laravel + Sanctum API gateway.',
        'docs' => 'See routes/api.php for available endpoints.',
        'health' => url('/up'),
    ]);
});
