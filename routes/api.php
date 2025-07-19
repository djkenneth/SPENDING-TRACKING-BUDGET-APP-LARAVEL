<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes (no authentication required)
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);
    });
});

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    // Profile Management
    Route::get('profile', [UserController::class, 'profile']);
    Route::put('profile', [UserController::class, 'updateProfile']);
    Route::put('password', [UserController::class, 'updatePassword']);

    // Avatar Management
    Route::post('avatar', [UserController::class, 'uploadAvatar']);
    Route::delete('avatar', [UserController::class, 'deleteAvatar']);

    // Preferences Management
    Route::get('preferences', [UserController::class, 'getPreferences']);
    Route::put('preferences', [UserController::class, 'updatePreferences']);

    // Settings Management
    Route::get('settings', [UserController::class, 'getSettings']);
    Route::put('settings', [UserController::class, 'updateSettings']);

    // Dashboard & Statistics
    Route::get('dashboard-stats', [UserController::class, 'getDashboardStats']);
    Route::get('activity-summary', [UserController::class, 'getActivitySummary']);
    Route::get('account-summary', [UserController::class, 'getAccountSummary']);

    // Notification Settings
    Route::get('notification-settings', [UserController::class, 'getNotificationSettings']);
    Route::put('notification-settings', [UserController::class, 'updateNotificationSettings']);

    // Security Management
    Route::get('security-info', [UserController::class, 'getSecurityInfo']);
    Route::get('active-sessions', [UserController::class, 'getActiveSessions']);
    Route::delete('sessions/{token_id}', [UserController::class, 'revokeToken']);

    // Data Management
    Route::get('export-data', [UserController::class, 'exportData']);
    Route::delete('delete-account', [UserController::class, 'deleteAccount']);
});

/*
|--------------------------------------------------------------------------
| Other API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
