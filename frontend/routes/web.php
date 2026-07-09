<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('app'));
Route::get('/admin', fn () => view('admin'));
