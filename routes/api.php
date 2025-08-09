<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\FinancialGoalController;
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
    // Utility Endpoints
    Route::get('/types', [AccountController::class, 'getAccountTypes']); // GET /api/accounts/types
    Route::get('/summary', [AccountController::class, 'getSummary']); // GET /api/accounts/summary
    Route::put('/bulk/update', [AccountController::class, 'bulkUpdate']); // PUT /api/accounts/bulk/update

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
    Route::get('/{account}/performance-metrics', [AccountController::class, 'getPerformanceMetrics']); // GET /api/accounts/{id}/performance-metrics
    Route::post('/{account}/sync-balance', [AccountController::class, 'syncBalance']); // POST /api/accounts/{id}/sync-balance
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
| Category Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('categories')->group(function () {
    // Basic CRUD Operations
    Route::get('/', [CategoryController::class, 'index']); // GET /api/categories
    Route::post('/', [CategoryController::class, 'store']); // POST /api/categories
    Route::get('/{category}', [CategoryController::class, 'show']); // GET /api/categories/{id}
    Route::put('/{category}', [CategoryController::class, 'update']); // PUT /api/categories/{id}
    Route::delete('/{category}', [CategoryController::class, 'destroy']); // DELETE /api/categories/{id}

    // Category Transactions
    Route::get('/{category}/transactions', [CategoryController::class, 'transactions']); // GET /api/categories/{id}/transactions

    // Analytics and Reports
    Route::get('/analytics/spending-analysis', [CategoryController::class, 'spendingAnalysis']); // GET /api/categories/analytics/spending-analysis
    Route::get('/analytics/trends', [CategoryController::class, 'trends']); // GET /api/categories/analytics/trends

    // Bulk Operations
    Route::put('/bulk/update', [CategoryController::class, 'bulkUpdate']); // PUT /api/categories/bulk/update
    Route::put('/bulk/reorder', [CategoryController::class, 'reorder']); // PUT /api/categories/bulk/reorder

    // Category Management
    Route::post('/merge', [CategoryController::class, 'merge']); // POST /api/categories/merge

    // Utility Endpoints
    Route::get('/meta/icons-and-colors', [CategoryController::class, 'getIconsAndColors']); // GET /api/categories/meta/icons-and-colors
    Route::get('/meta/defaults', [CategoryController::class, 'getDefaults']); // GET /api/categories/meta/defaults
    Route::post('/meta/create-defaults', [CategoryController::class, 'createDefaults']); // POST /api/categories/meta/create-defaults
});

/*
|--------------------------------------------------------------------------
| Budget Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('budgets')->group(function () {
    // Core CRUD Operations
    Route::get('/', [BudgetController::class, 'index']); // GET /api/budgets
    Route::post('/', [BudgetController::class, 'store']); // POST /api/budgets
    Route::get('/{budget}', [BudgetController::class, 'show']); // GET /api/budgets/{id}
    Route::put('/{budget}', [BudgetController::class, 'update']); // PUT /api/budgets/{id}
    Route::delete('/{budget}', [BudgetController::class, 'destroy']); // DELETE /api/budgets/{id}

    // Special Budget Endpoints
    Route::get('/current/month', [BudgetController::class, 'current']); // GET /api/budgets/current/month
    Route::get('/{budget}/analysis', [BudgetController::class, 'analysis']); // GET /api/budgets/{id}/analysis
    Route::post('/{budget}/reset', [BudgetController::class, 'reset']); // POST /api/budgets/{id}/reset
});

/*
|--------------------------------------------------------------------------
| Financial Goals Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
    // Core CRUD Operations
    Route::get('/', [FinancialGoalController::class, 'index']); // GET /api/goals
    Route::post('/', [FinancialGoalController::class, 'store']); // POST /api/goals
    Route::get('/{goal}', [FinancialGoalController::class, 'show']); // GET /api/goals/{id}
    Route::put('/{goal}', [FinancialGoalController::class, 'update']); // PUT /api/goals/{id}
    Route::delete('/{goal}', [FinancialGoalController::class, 'destroy']); // DELETE /api/goals/{id}

    // Goal Actions
    Route::post('/{goal}/contribute', [FinancialGoalController::class, 'contribute']); // POST /api/goals/{id}/contribute
    Route::get('/{goal}/progress', [FinancialGoalController::class, 'progress']); // GET /api/goals/{id}/progress
    Route::post('/{goal}/complete', [FinancialGoalController::class, 'complete']); // POST /api/goals/{id}/complete
});

/*
|--------------------------------------------------------------------------
| Debt Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('debts')->group(function () {
    // Utility Endpoints
    Route::get('/types', [DebtController::class, 'getDebtTypes']); // GET /api/debts/types
    Route::get('/summary', [DebtController::class, 'getSummary']); // GET /api/debts/summary
    Route::post('/consolidation-options', [DebtController::class, 'getConsolidationOptions']); // POST /api/debts/consolidation-options

    // Core CRUD Operations
    Route::get('/', [DebtController::class, 'index']); // GET /api/debts
    Route::post('/', [DebtController::class, 'store']); // POST /api/debts
    Route::get('/{debt}', [DebtController::class, 'show']); // GET /api/debts/{id}
    Route::put('/{debt}', [DebtController::class, 'update']); // PUT /api/debts/{id}
    Route::delete('/{debt}', [DebtController::class, 'destroy']); // DELETE /api/debts/{id}

    // Payment Management
    Route::post('/{debt}/payment', [DebtController::class, 'recordPayment']); // POST /api/debts/{id}/payment
    Route::get('/{debt}/payment-history', [DebtController::class, 'getPaymentHistory']); // GET /api/debts/{id}/payment-history
    Route::get('/{debt}/payoff-schedule', [DebtController::class, 'getPayoffSchedule']); // GET /api/debts/{id}/payoff-schedule

    // Debt Actions
    Route::post('/{debt}/mark-paid-off', [DebtController::class, 'markAsPaidOff']); // POST /api/debts/{id}/mark-paid-off
});

/*
|--------------------------------------------------------------------------
| Other API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
