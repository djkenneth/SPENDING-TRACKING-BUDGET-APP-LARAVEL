<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = User::query();

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $users = $query->paginate($perPage);

        return Inertia::render('Users', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'currency' => ['nullable', 'string', 'max:3'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'currency' => $validated['currency'] ?? 'PHP',
            'timezone' => $validated['timezone'] ?? 'Asia/Manila',
            'language' => $validated['language'] ?? 'en',
            'preferences' => [],
        ]);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'nullable', 'confirmed', Rules\Password::defaults()],
            'currency' => ['sometimes', 'string', 'max:3'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        // Update password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Delete all user tokens
        $user->tokens()->delete();

        // Soft delete the user
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Bulk delete users
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id']
        ]);

        $currentUserId = $request->user()->id;

        // Prevent deleting current user
        $userIds = array_filter($validated['user_ids'], function($id) use ($currentUserId) {
            return $id !== $currentUserId;
        });

        if (empty($userIds)) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete your own account.');
        }

        // Delete tokens for all users
        User::whereIn('id', $userIds)->each(function($user) {
            $user->tokens()->delete();
        });

        // Soft delete users
        User::whereIn('id', $userIds)->delete();

        return redirect()->route('users.index')
            ->with('success', count($userIds) . ' user(s) deleted successfully.');
    }
}
