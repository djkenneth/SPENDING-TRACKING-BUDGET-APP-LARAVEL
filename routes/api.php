<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TransactionController;
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
| Account Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('accounts')->group(function () {
    // Basic CRUD Operations
    Route::get('/', [AccountController::class, 'index']); // GET /api/accounts
    Route::post('/', [AccountController::class, 'store']); // POST /api/accounts
    Route::get('/{account}', [AccountController::class, 'show']); // GET /api/accounts/{id}
    Route::put('/{account}', [AccountController::class, 'update']); // PUT /api/accounts/{id}
    Route::delete('/{account}', [AccountController::class, 'destroy']); // DELETE /api/accounts/{id}

    // Account Transactions
    Route::get('/{account}/transactions', [AccountController::class, 'transactions']); // GET /api/accounts/{id}/transactions
    Route::get('/{account}/balance-history', [AccountController::class, 'balanceHistory']); // GET /api/accounts/{id}/balance-history

    // Account Operations
    Route::post('/{account}/adjust-balance', [AccountController::class, 'adjustBalance']); // POST /api/accounts/{id}/adjust-balance
    Route::post('/transfer', [AccountController::class, 'transfer']); // POST /api/accounts/transfer

    // Utility Endpoints
    Route::get('/meta/types', [AccountController::class, 'getAccountTypes']); // GET /api/accounts/meta/types
    Route::get('/summary/overview', [AccountController::class, 'getSummary']); // GET /api/accounts/summary/overview
    Route::put('/bulk/update', [AccountController::class, 'bulkUpdate']); // PUT /api/accounts/bulk/update
});

/*
|--------------------------------------------------------------------------
| Transaction Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('transactions')->group(function () {
    // Basic CRUD Operations
    Route::get('/', [TransactionController::class, 'index']); // GET /api/transactions
    Route::post('/', [TransactionController::class, 'store']); // POST /api/transactions
    Route::get('/{transaction}', [TransactionController::class, 'show']); // GET /api/transactions/{id}
    Route::put('/{transaction}', [TransactionController::class, 'update']); // PUT /api/transactions/{id}
    Route::delete('/{transaction}', [TransactionController::class, 'destroy']); // DELETE /api/transactions/{id}

    // Bulk Operations
    Route::post('/bulk', [TransactionController::class, 'bulkCreate']); // POST /api/transactions/bulk
    Route::delete('/bulk', [TransactionController::class, 'bulkDelete']); // DELETE /api/transactions/bulk

    // Search and Filter
    Route::get('/search/query', [TransactionController::class, 'search']); // GET /api/transactions/search/query
    Route::get('/recent/list', [TransactionController::class, 'recent']); // GET /api/transactions/recent/list

    // Import/Export
    Route::post('/import/csv', [TransactionController::class, 'import']); // POST /api/transactions/import/csv
    Route::get('/export/data', [TransactionController::class, 'export']); // GET /api/transactions/export/data

    // Statistics and Analytics
    Route::get('/statistics/summary', [TransactionController::class, 'statistics']); // GET /api/transactions/statistics/summary
});


/*
|--------------------------------------------------------------------------
| Other API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
