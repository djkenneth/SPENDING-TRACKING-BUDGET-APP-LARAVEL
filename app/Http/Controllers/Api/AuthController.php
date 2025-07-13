<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'currency' => $request->currency ?? 'PHP',
            'timezone' => $request->timezone ?? 'Asia/Manila',
            'language' => $request->language ?? 'en',
            'preferences' => $request->preferences ?? [],
        ]);

        // Create default categories for the user
        $this->createDefaultCategories($user);

        // Create default account for the user
        $this->createDefaultAccount($user);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Revoke old tokens (optional - for single session)
        // $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Delete current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Delete all tokens
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user())
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Delete all tokens after password reset
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

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
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Verify email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified'
            ]);
        }

        // In a real application, you'd verify the token here
        // For now, we'll just mark as verified
        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);
    }

    /**
     * Resend email verification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified'
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent'
        ]);
    }

    /**
     * Create default categories for new user
     */
    private function createDefaultCategories(User $user): void
    {
        $defaultCategories = [
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'color' => '#4CAF50', 'icon' => 'work'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#2196F3', 'icon' => 'computer'],
            ['name' => 'Investment', 'type' => 'income', 'color' => '#FF9800', 'icon' => 'trending_up'],
            ['name' => 'Other Income', 'type' => 'income', 'color' => '#9C27B0', 'icon' => 'account_balance_wallet'],

            // Expense categories
            ['name' => 'Food & Dining', 'type' => 'expense', 'color' => '#F44336', 'icon' => 'restaurant'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#607D8B', 'icon' => 'directions_car'],
            ['name' => 'Utilities', 'type' => 'expense', 'color' => '#795548', 'icon' => 'electrical_services'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#E91E63', 'icon' => 'movie'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#9C27B0', 'icon' => 'shopping_cart'],
            ['name' => 'Healthcare', 'type' => 'expense', 'color' => '#009688', 'icon' => 'local_hospital'],
            ['name' => 'Education', 'type' => 'expense', 'color' => '#3F51B5', 'icon' => 'school'],
            ['name' => 'Bills', 'type' => 'expense', 'color' => '#FF5722', 'icon' => 'receipt'],
            ['name' => 'Other Expense', 'type' => 'expense', 'color' => '#757575', 'icon' => 'category'],

            // Transfer category
            ['name' => 'Transfer', 'type' => 'transfer', 'color' => '#00BCD4', 'icon' => 'swap_horiz'],
        ];

        foreach ($defaultCategories as $category) {
            $user->categories()->create($category);
        }
    }

    /**
     * Create default account for new user
     */
    private function createDefaultAccount(User $user): void
    {
        $user->accounts()->create([
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0.00,
            'currency' => $user->currency,
            'color' => '#4CAF50',
            'icon' => 'account_balance_wallet',
            'description' => 'Default cash account',
            'is_active' => true,
            'include_in_net_worth' => true,
        ]);
    }
}
