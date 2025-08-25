<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdatePreferencesRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Get user profile
     *
     * @OA\Get(
     *     path="/api/user/profile",
     *     operationId="getUserProfile",
     *     tags={"Users"},
     *     summary="Get user profile",
     *     description="Get current authenticated user profile",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user())
        ]);
    }

    /**
     * Update user profile
     *
     * @OA\Put(
     *     path="/api/user/profile",
     *     operationId="updateUserProfile",
     *     tags={"Users"},
     *     summary="Update user profile",
     *     description="Update authenticated user profile information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+639123456789"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="currency", type="string", example="PHP"),
     *             @OA\Property(property="timezone", type="string", example="Asia/Manila"),
     *             @OA\Property(property="language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh())
        ]);
    }

    /**
     * Update user password
     *
     * @OA\Put(
     *     path="/api/user/password",
     *     operationId="updateUserPassword",
     *     tags={"Users"},
     *     summary="Update user password",
     *     description="Change authenticated user's password",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="currentPass123"),
     *             @OA\Property(property="password", type="string", format="password", example="newPass123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newPass123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete all tokens except current one
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Upload user avatar
     *
     * @OA\Post(
     *     path="/api/user/avatar",
     *     operationId="uploadAvatar",
     *     tags={"Users"},
     *     summary="Upload avatar",
     *     description="Upload or update user avatar image",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"avatar"},
     *                 @OA\Property(property="avatar", type="string", format="binary", description="Avatar image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avatar uploaded successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="avatar_url", type="string", example="https://example.com/storage/avatars/user1.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function uploadAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar' => $avatarPath
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => Storage::disk('public')->url($avatarPath)
            ]
        ]);
    }

    /**
     * Delete user avatar
     *
     * @OA\Delete(
     *     path="/api/user/avatar",
     *     operationId="deleteAvatar",
     *     tags={"Users"},
     *     summary="Delete avatar",
     *     description="Remove user avatar image",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Avatar deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avatar deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar deleted successfully'
        ]);
    }

    /**
     * Get user preferences
     *
     * @OA\Get(
     *     path="/api/user/preferences",
     *     operationId="getUserPreferences",
     *     tags={"Users"},
     *     summary="Get user preferences",
     *     description="Get user preferences and settings",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Preferences retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="currency", type="string", example="PHP"),
     *                 @OA\Property(property="timezone", type="string", example="Asia/Manila"),
     *                 @OA\Property(property="language", type="string", example="en"),
     *                 @OA\Property(property="preferences", type="object"),
     *                 @OA\Property(property="currency_symbol", type="string", example="₱")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $user->currency,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'preferences' => $user->preferences,
                'currency_symbol' => $user->getCurrencySymbol(),
            ]
        ]);
    }

    /**
     * Update user preferences
     *
     * @OA\Put(
     *     path="/api/user/preferences",
     *     operationId="updateUserPreferences",
     *     tags={"Users"},
     *     summary="Update user preferences",
     *     description="Update user preferences and settings",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="timezone", type="string", example="America/New_York"),
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="preferences", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preferences updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Preferences updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => [
                'currency' => $user->currency,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'preferences' => $user->preferences,
                'currency_symbol' => $user->getCurrencySymbol(),
            ]
        ]);
    }

    /**
     * Get user settings
     *
     * @OA\Get(
     *     path="/api/user/settings",
     *     operationId="getUserSettings",
     *     tags={"Users"},
     *     summary="Get user settings",
     *     description="Get all user settings",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->settings()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update user settings
     *
     * @OA\Put(
     *     path="/api/user/settings",
     *     operationId="updateUserSettings",
     *     tags={"Users"},
     *     summary="Update user settings",
     *     description="Update user settings",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"settings"},
     *             @OA\Property(property="settings", type="object",
     *                 @OA\AdditionalProperties(type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required', 'string'],
        ]);

        $user = $request->user();

        foreach ($request->settings as $key => $value) {
            $user->setSetting($key, $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }

    /**
     * Get user dashboard statistics
     *
     * @OA\Get(
     *     path="/api/user/dashboard-stats",
     *     operationId="getDashboardStats",
     *     tags={"Users"},
     *     summary="Get dashboard statistics",
     *     description="Get user dashboard statistics and metrics",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard stats retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="net_worth", type="number", example=50000),
     *                 @OA\Property(property="total_accounts", type="integer", example=5),
     *                 @OA\Property(property="total_categories", type="integer", example=15),
     *                 @OA\Property(property="current_month_income", type="number", example=5000),
     *                 @OA\Property(property="current_month_expenses", type="number", example=3000),
     *                 @OA\Property(property="total_transactions", type="integer", example=250),
     *                 @OA\Property(property="active_budgets", type="integer", example=8),
     *                 @OA\Property(property="active_goals", type="integer", example=3),
     *                 @OA\Property(property="active_debts", type="integer", example=2),
     *                 @OA\Property(property="upcoming_bills", type="integer", example=4),
     *                 @OA\Property(property="unread_notifications", type="integer", example=2),
     *                 @OA\Property(property="currency_symbol", type="string", example="₱")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $stats = [
            'net_worth' => $user->net_worth,
            'total_accounts' => $user->accounts()->where('is_active', true)->count(),
            'total_categories' => $user->categories()->where('is_active', true)->count(),
            'current_month_income' => $user->getTotalIncome($startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')),
            'current_month_expenses' => $user->getTotalExpenses($startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')),
            'total_transactions' => $user->transactions()->count(),
            'active_budgets' => $user->budgets()->where('is_active', true)->count(),
            'active_goals' => $user->financialGoals()->where('status', 'active')->count(),
            'active_debts' => $user->debts()->where('status', 'active')->count(),
            'upcoming_bills' => $user->bills()
                ->where('status', 'active')
                ->where('due_date', '>=', now())
                ->where('due_date', '<=', now()->addDays(7))
                ->count(),
            'unread_notifications' => $user->getUnreadNotificationsCount(),
            'currency_symbol' => $user->getCurrencySymbol(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get user activity summary
     *
     * @OA\Get(
     *     path="/api/user/activity-summary",
     *     operationId="getActivitySummary",
     *     tags={"Users"},
     *     summary="Get activity summary",
     *     description="Get user activity summary for specified period",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to include (default: 30)",
     *         required=false,
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transactions_count", type="integer", example=45),
     *                 @OA\Property(property="recent_transactions", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="description", type="string", example="Groceries"),
     *                         @OA\Property(property="amount", type="number", example=150.50),
     *                         @OA\Property(property="type", type="string", example="expense"),
     *                         @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                         @OA\Property(property="account", type="string", example="Cash"),
     *                         @OA\Property(property="category", type="string", example="Food & Dining")
     *                     )
     *                 ),
     *                 @OA\Property(property="budget_utilization", type="number", example=75.5),
     *                 @OA\Property(property="goals_progress", type="number", example=60.0)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getActivitySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 30);

        $startDate = now()->subDays($days);
        $endDate = now();

        $activity = [
            'transactions_count' => $user->transactions()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'recent_transactions' => $user->transactions()
                ->with(['account', 'category'])
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'description' => $transaction->description,
                        'amount' => $transaction->amount,
                        'type' => $transaction->type,
                        'date' => $transaction->date,
                        'account' => $transaction->account->name,
                        'category' => $transaction->category->name,
                        'category_color' => $transaction->category->color,
                        'category_icon' => $transaction->category->icon,
                    ];
                }),
            'budget_utilization' => $user->getCurrentBudgetUtilization(),
            'goals_progress' => $user->getActiveGoalsProgress(),
        ];

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
    }

    /**
     * Get user account summary
     *
     * @OA\Get(
     *     path="/api/user/account-summary",
     *     operationId="getAccountSummary",
     *     tags={"Users"},
     *     summary="Get account summary",
     *     description="Get summary of all user accounts",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Account summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_balance", type="number", example=25000),
     *                 @OA\Property(property="accounts_by_type", type="object"),
     *                 @OA\Property(property="accounts", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Main Bank"),
     *                         @OA\Property(property="type", type="string", example="bank"),
     *                         @OA\Property(property="balance", type="number", example=15000),
     *                         @OA\Property(property="currency", type="string", example="PHP"),
     *                         @OA\Property(property="color", type="string", example="#2196F3"),
     *                         @OA\Property(property="icon", type="string", example="account_balance"),
     *                         @OA\Property(property="include_in_net_worth", type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getAccountSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = $user->accounts()
            ->where('is_active', true)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'balance' => $account->balance,
                    'currency' => $account->currency,
                    'color' => $account->color,
                    'icon' => $account->icon,
                    'include_in_net_worth' => $account->include_in_net_worth,
                ];
            });

        $summary = [
            'total_balance' => $accounts->sum('balance'),
            'accounts_by_type' => $accounts->groupBy('type')->map(function ($typeAccounts) {
                return [
                    'count' => $typeAccounts->count(),
                    'total_balance' => $typeAccounts->sum('balance'),
                ];
            }),
            'accounts' => $accounts,
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    // Temporary comment
    // @OA\Property(property="financial_goals", type="array", @OA\Items(ref="#/components/schemas/FinancialGoal")),
    // @OA\Property(property="debts", type="array", @OA\Items(ref="#/components/schemas/Debt")),
    // @OA\Property(property="bills", type="array", @OA\Items(ref="#/components/schemas/Bill")),

    /**
     * Export user data
     *
     * @OA\Get(
     *     path="/api/user/export-data",
     *     operationId="exportUserData",
     *     tags={"Users"},
     *     summary="Export user data",
     *     description="Export all user data in JSON format",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data exported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data exported successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="accounts", type="array", @OA\Items(ref="#/components/schemas/Account")),
     *                 @OA\Property(property="categories", type="array", @OA\Items(ref="#/components/schemas/Category")),
     *                 @OA\Property(property="transactions", type="array", @OA\Items(ref="#/components/schemas/Transaction")),
     *                 @OA\Property(property="budgets", type="array", @OA\Items(ref="#/components/schemas/Budget")),
     *                 @OA\Property(property="settings", type="object"),
     *                 @OA\Property(property="exported_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => new UserResource($user),
            'accounts' => $user->accounts,
            'categories' => $user->categories,
            'transactions' => $user->transactions()->with(['account', 'category'])->get(),
            'budgets' => $user->budgets()->with('category')->get(),
            'financial_goals' => $user->financialGoals,
            'debts' => $user->debts,
            'bills' => $user->bills()->with('category')->get(),
            'settings' => $user->settings()->pluck('value', 'key'),
            'exported_at' => now()->toISOString(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Data exported successfully',
            'data' => $data
        ]);
    }

    /**
     * Delete user account
     *
     * @OA\Delete(
     *     path="/api/user/delete-account",
     *     operationId="deleteUserAccount",
     *     tags={"Users"},
     *     summary="Delete user account",
     *     description="Permanently delete user account and all associated data",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","confirmation"},
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="confirmation", type="string", example="DELETE", description="Must be 'DELETE' to confirm")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:DELETE'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        // Delete user avatar
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Delete all user tokens
        $user->tokens()->delete();

        // Soft delete the user (this will cascade to related records due to foreign key constraints)
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Get notification settings
     *
     * @OA\Get(
     *     path="/api/user/notification-settings",
     *     operationId="getNotificationSettings",
     *     tags={"Users"},
     *     summary="Get notification settings",
     *     description="Get user notification preferences",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="budget_alerts", type="boolean", example=true),
     *                 @OA\Property(property="bill_reminders", type="boolean", example=true),
     *                 @OA\Property(property="goal_milestones", type="boolean", example=true),
     *                 @OA\Property(property="low_balance_alerts", type="boolean", example=true),
     *                 @OA\Property(property="transaction_notifications", type="boolean", example=false),
     *                 @OA\Property(property="email_notifications", type="boolean", example=true),
     *                 @OA\Property(property="push_notifications", type="boolean", example=true),
     *                 @OA\Property(property="sms_notifications", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getNotificationSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $settings = [
            'budget_alerts' => $user->getSetting('budget_alerts', true),
            'bill_reminders' => $user->getSetting('bill_reminders', true),
            'goal_milestones' => $user->getSetting('goal_milestones', true),
            'low_balance_alerts' => $user->getSetting('low_balance_alerts', true),
            'transaction_notifications' => $user->getSetting('transaction_notifications', false),
            'email_notifications' => $user->getSetting('email_notifications', true),
            'push_notifications' => $user->getSetting('push_notifications', true),
            'sms_notifications' => $user->getSetting('sms_notifications', false),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update notification settings
     *
     * @OA\Put(
     *     path="/api/user/notification-settings",
     *     operationId="updateNotificationSettings",
     *     tags={"Users"},
     *     summary="Update notification settings",
     *     description="Update user notification preferences",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="budget_alerts", type="boolean", example=true),
     *             @OA\Property(property="bill_reminders", type="boolean", example=true),
     *             @OA\Property(property="goal_milestones", type="boolean", example=true),
     *             @OA\Property(property="low_balance_alerts", type="boolean", example=true),
     *             @OA\Property(property="transaction_notifications", type="boolean", example=false),
     *             @OA\Property(property="email_notifications", type="boolean", example=true),
     *             @OA\Property(property="push_notifications", type="boolean", example=true),
     *             @OA\Property(property="sms_notifications", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification settings updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $request->validate([
            'budget_alerts' => ['nullable', 'boolean'],
            'bill_reminders' => ['nullable', 'boolean'],
            'goal_milestones' => ['nullable', 'boolean'],
            'low_balance_alerts' => ['nullable', 'boolean'],
            'transaction_notifications' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        foreach ($request->validated() as $key => $value) {
            if ($value !== null) {
                $user->setSetting($key, $value);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully'
        ]);
    }

    /**
     * Get security information
     *
     * @OA\Get(
     *     path="/api/user/security-info",
     *     operationId="getSecurityInfo",
     *     tags={"Users"},
     *     summary="Get security information",
     *     description="Get user account security information",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Security info retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="email_verified", type="boolean", example=true),
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="last_login_ip", type="string", nullable=true),
     *                 @OA\Property(property="active_sessions", type="integer", example=3),
     *                 @OA\Property(property="two_factor_enabled", type="boolean", example=false),
     *                 @OA\Property(property="backup_codes_generated", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSecurityInfo(Request $request): JsonResponse
    {
        $user = $request->user();

        $info = [
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
            'active_sessions' => $user->tokens()->count(),
            'two_factor_enabled' => false, // Implement if needed
            'backup_codes_generated' => false, // Implement if needed
        ];

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    /**
     * Revoke session token
     *
     * @OA\Delete(
     *     path="/api/user/sessions/{token_id}",
     *     operationId="revokeToken",
     *     tags={"Users"},
     *     summary="Revoke session",
     *     description="Revoke a specific session/token",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="token_id",
     *         in="path",
     *         description="Token ID to revoke",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session revoked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Session revoked successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot revoke current session"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->validate([
            'token_id' => ['required', 'integer', 'exists:personal_access_tokens,id'],
        ]);

        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;
        $tokenId = $request->token_id;

        if ($tokenId == $currentTokenId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke current session'
            ], 400);
        }

        $user->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session revoked successfully'
        ]);
    }

    /**
     * Get active sessions
     *
     * @OA\Get(
     *     path="/api/user/active-sessions",
     *     operationId="getActiveSessions",
     *     tags={"Users"},
     *     summary="Get active sessions",
     *     description="Get all active user sessions/tokens",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Active sessions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="auth_token"),
     *                     @OA\Property(property="last_used_at", type="string", format="date-time"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="is_current", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getActiveSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $sessions = $user->tokens()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }
}
