<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'docs' => 'API CRM Manden Baoubab. Voir /api/*',
    ]);
});
