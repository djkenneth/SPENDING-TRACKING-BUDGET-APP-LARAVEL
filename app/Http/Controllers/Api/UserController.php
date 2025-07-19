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

    /**
     * Export user data
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
     * Get user notifications settings
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
     * Update user notifications settings
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
     * Get user security information
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
     * Revoke specific token/session
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
     * Get user's active sessions
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
