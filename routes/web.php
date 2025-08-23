<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Route (exclude from middleware)
Route::get('/documentation', function () {
    return redirect('/api/documentation');
});
