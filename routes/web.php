<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Route (exclude from middleware)
Route::get('/documentation', function () {
    return redirect('/api/documentation');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/users', function () {
    return Inertia::render('Users');
})->name('users');
