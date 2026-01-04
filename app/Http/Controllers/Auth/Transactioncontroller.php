<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of all transactions (admin view).
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'date');
        $sortOrder = $request->input('sort_order', 'desc');

        // Filter parameters
        $type = $request->input('type');
        $accountId = $request->input('account_id');
        $categoryId = $request->input('category_id');
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Transaction::with([
            'account:id,name,type,color',
            'category:id,name,icon,color,type',
            'user:id,name,email'
        ]);

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by type
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by account
        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        // Filter by category
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by date range
        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        // Sorting
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $transactions = $query->paginate($perPage);

        // Get all accounts for filters/forms
        $accounts = Account::where('is_active', true)
            ->select('id', 'name', 'type', 'color', 'balance', 'user_id')
            ->orderBy('name')
            ->get();

        // Get all categories for filters/forms
        $categories = Category::select('id', 'name', 'type', 'icon', 'color', 'user_id')
            ->orderBy('name')
            ->get();

        // Get all users for filter
        $users = \App\Models\User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // Calculate summary statistics (all transactions)
        $summary = [
            'total_income' => Transaction::where('type', 'income')
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->sum('amount'),
            'total_expenses' => Transaction::where('type', 'expense')
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->sum('amount'),
            'total_transactions' => Transaction::query()
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->count(),
        ];

        return Inertia::render('Transactions', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
            'users' => $users,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
                'type' => $type,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Display the specified transaction (admin view).
     */
    public function show(Request $request, Transaction $transaction): Response
    {
        $transaction->load([
            'account:id,name,type,color,balance',
            'category:id,name,icon,color,type',
            'transferAccount:id,name,type,color',
            'user:id,name,email'
        ]);

        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Store a newly created transaction (admin view).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'transfer_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'string', Rule::in(['income', 'expense', 'transfer'])],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_type' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'recurring_interval' => ['nullable', 'integer', 'min:1'],
            'recurring_end_date' => ['nullable', 'date', 'after:date'],
            'is_cleared' => ['nullable', 'boolean'],
        ]);

        // Validate transfer account for transfer type
        if ($validated['type'] === 'transfer' && empty($validated['transfer_account_id'])) {
            return redirect()->back()
                ->withErrors(['transfer_account_id' => 'Transfer account is required for transfer transactions.'])
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $validated['user_id'],
                'account_id' => $validated['account_id'],
                'category_id' => $validated['category_id'],
                'transfer_account_id' => $validated['transfer_account_id'] ?? null,
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'reference_number' => $validated['reference_number'] ?? null,
                'location' => $validated['location'] ?? null,
                'is_recurring' => $validated['is_recurring'] ?? false,
                'recurring_type' => $validated['recurring_type'] ?? null,
                'recurring_interval' => $validated['recurring_interval'] ?? null,
                'recurring_end_date' => $validated['recurring_end_date'] ?? null,
                'is_cleared' => $validated['is_cleared'] ?? true,
                'cleared_at' => ($validated['is_cleared'] ?? true) ? now() : null,
            ]);

            // Update account balance
            $account = Account::find($validated['account_id']);
            if ($validated['type'] === 'income') {
                $account->increment('balance', $validated['amount']);
            } elseif ($validated['type'] === 'expense') {
                $account->decrement('balance', $validated['amount']);
            } elseif ($validated['type'] === 'transfer' && !empty($validated['transfer_account_id'])) {
                $account->decrement('balance', $validated['amount']);
                $transferAccount = Account::find($validated['transfer_account_id']);
                $transferAccount->increment('balance', $validated['amount']);
            }

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update the specified transaction (admin view).
     */
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'transfer_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'type' => ['sometimes', 'string', Rule::in(['income', 'expense', 'transfer'])],
            'date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_type' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'recurring_interval' => ['nullable', 'integer', 'min:1'],
            'recurring_end_date' => ['nullable', 'date'],
            'is_cleared' => ['nullable', 'boolean'],
        ]);

        try {
            DB::beginTransaction();

            // Reverse old transaction effect on account balance
            $oldAccount = Account::find($transaction->account_id);
            if ($transaction->type === 'income') {
                $oldAccount->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $oldAccount->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer' && $transaction->transfer_account_id) {
                $oldAccount->increment('balance', $transaction->amount);
                $oldTransferAccount = Account::find($transaction->transfer_account_id);
                $oldTransferAccount->decrement('balance', $transaction->amount);
            }

            // Update transaction
            $transaction->update($validated);

            // Apply new transaction effect on account balance
            $newAccountId = $validated['account_id'] ?? $transaction->account_id;
            $newAmount = $validated['amount'] ?? $transaction->amount;
            $newType = $validated['type'] ?? $transaction->type;
            $newTransferAccountId = $validated['transfer_account_id'] ?? $transaction->transfer_account_id;

            $newAccount = Account::find($newAccountId);
            if ($newType === 'income') {
                $newAccount->increment('balance', $newAmount);
            } elseif ($newType === 'expense') {
                $newAccount->decrement('balance', $newAmount);
            } elseif ($newType === 'transfer' && $newTransferAccountId) {
                $newAccount->decrement('balance', $newAmount);
                $newTransferAccount = Account::find($newTransferAccountId);
                $newTransferAccount->increment('balance', $newAmount);
            }

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified transaction (admin view).
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        try {
            DB::beginTransaction();

            // Reverse transaction effect on account balance
            $account = Account::find($transaction->account_id);
            if ($transaction->type === 'income') {
                $account->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $account->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer' && $transaction->transfer_account_id) {
                $account->increment('balance', $transaction->amount);
                $transferAccount = Account::find($transaction->transfer_account_id);
                $transferAccount->decrement('balance', $transaction->amount);
            }

            // Soft delete the transaction
            $transaction->delete();

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Transaction deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete transaction: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete transactions (admin view).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id']
        ]);

        try {
            DB::beginTransaction();

            $transactions = Transaction::whereIn('id', $validated['transaction_ids'])->get();

            foreach ($transactions as $transaction) {
                // Reverse transaction effect on account balance
                $account = Account::find($transaction->account_id);
                if ($transaction->type === 'income') {
                    $account->decrement('balance', $transaction->amount);
                } elseif ($transaction->type === 'expense') {
                    $account->increment('balance', $transaction->amount);
                } elseif ($transaction->type === 'transfer' && $transaction->transfer_account_id) {
                    $account->increment('balance', $transaction->amount);
                    $transferAccount = Account::find($transaction->transfer_account_id);
                    $transferAccount->decrement('balance', $transaction->amount);
                }

                $transaction->delete();
            }

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', count($validated['transaction_ids']) . ' transaction(s) deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete transactions: ' . $e->getMessage());
        }
    }
}
