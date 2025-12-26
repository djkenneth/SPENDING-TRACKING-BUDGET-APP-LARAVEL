<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest routes (redirect if authenticated)
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('users', UserController::class);
    Route::post('users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::get('/', function () {
    return view('welcome');
});

// API Documentation Route (exclude from middleware)
Route::get('/documentation', function () {
    return redirect('/api/documentation');
});

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->name('dashboard');

// Route::get('/users', function () {
//     return Inertia::render('Users');
// })->name('users');
