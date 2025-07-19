<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\AccountTransactionResource;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * Get all user accounts
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:cash,bank,credit_card,investment,ewallet'],
            'is_active' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $query = $request->user()->accounts();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => AccountResource::collection($accounts),
            'meta' => [
                'total' => $accounts->count(),
                'total_balance' => $accounts->sum('balance'),
                'net_worth' => $accounts->where('include_in_net_worth', true)->sum('balance'),
                'currency' => $request->user()->currency,
                'currency_symbol' => $request->user()->getCurrencySymbol(),
            ]
        ]);
    }

    /**
     * Create a new account
     */
    public function store(CreateAccountRequest $request): JsonResponse
    {
        $account = $request->user()->accounts()->create($request->validated());

        // Create initial balance history record
        $this->accountService->recordBalanceHistory($account, $account->balance, 'initial', $account->balance);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully',
            'data' => new AccountResource($account)
        ], 201);
    }

    /**
     * Get specific account
     */
    public function show(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $accountData = new AccountResource($account);
        $accountStats = $this->accountService->getAccountStatistics($account);

        return response()->json([
            'success' => true,
            'data' => array_merge($accountData->toArray($request), [
                'statistics' => $accountStats
            ])
        ]);
    }

    /**
     * Update account
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $oldBalance = $account->balance;
        $account->update($request->validated());

        // Record balance change if balance was updated
        if ($oldBalance != $account->balance) {
            $this->accountService->recordBalanceHistory(
                $account,
                $account->balance,
                'adjustment',
                $account->balance - $oldBalance
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully',
            'data' => new AccountResource($account->fresh())
        ]);
    }

    /**
     * Delete account
     */
    public function destroy(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        // Check if account can be deleted
        $canDelete = $this->accountService->canDeleteAccount($account);
        if (!$canDelete['can_delete']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account',
                'errors' => $canDelete['reasons']
            ], 400);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Get account transactions
     */
    public function transactions(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $query = $account->transactions()->with(['category', 'transferAccount']);

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = $request->input('per_page', 20);
        $transactions = $query->latest('date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AccountTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ]);
    }

    /**
     * Get account balance history
     */
    public function balanceHistory(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $period = $request->input('period', 'month');
        $history = $this->accountService->getBalanceHistory($account, $period, $request->start_date, $request->end_date);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Transfer money between accounts
     */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Ensure both accounts belong to user
        $fromAccount = $user->accounts()->find($request->from_account_id);
        $toAccount = $user->accounts()->find($request->to_account_id);

        if (!$fromAccount || !$toAccount) {
            return response()->json([
                'success' => false,
                'message' => 'One or both accounts not found'
            ], 404);
        }

        // Check if from account has sufficient balance (for non-credit accounts)
        if ($fromAccount->type !== 'credit_card' && $fromAccount->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance in source account'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $result = $this->accountService->transferMoney(
                $fromAccount,
                $toAccount,
                $request->amount,
                $request->description,
                $request->date,
                $request->notes
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => [
                    'transfer_id' => $result['transfer_id'],
                    'from_transaction' => new AccountTransactionResource($result['from_transaction']),
                    'to_transaction' => new AccountTransactionResource($result['to_transaction']),
                    'from_account_balance' => $fromAccount->fresh()->balance,
                    'to_account_balance' => $toAccount->fresh()->balance,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transfer failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust account balance
     */
    public function adjustBalance(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $request->validate([
            'balance' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $oldBalance = $account->balance;
        $newBalance = $request->balance;
        $adjustment = $newBalance - $oldBalance;

        try {
            DB::beginTransaction();

            // Update account balance
            $account->update(['balance' => $newBalance]);

            // Record balance history
            $this->accountService->recordBalanceHistory($account, $newBalance, 'adjustment', $adjustment);

            // Create adjustment transaction for tracking
            $category = $account->user->categories()->where('name', 'Balance Adjustment')->first();
            if (!$category) {
                $category = $account->user->categories()->create([
                    'name' => 'Balance Adjustment',
                    'type' => $adjustment > 0 ? 'income' : 'expense',
                    'color' => '#757575',
                    'icon' => 'tune',
                ]);
            }

            $transaction = $account->transactions()->create([
                'user_id' => $account->user_id,
                'category_id' => $category->id,
                'description' => 'Balance Adjustment: ' . $request->reason,
                'amount' => abs($adjustment),
                'type' => $adjustment > 0 ? 'income' : 'expense',
                'date' => now()->format('Y-m-d'),
                'notes' => "Balance adjusted from {$account->user->getCurrencySymbol()}" . number_format($oldBalance, 2) .
                          " to {$account->user->getCurrencySymbol()}" . number_format($newBalance, 2),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account balance adjusted successfully',
                'data' => [
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'adjustment' => $adjustment,
                    'account' => new AccountResource($account->fresh()),
                    'transaction' => new AccountTransactionResource($transaction),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Balance adjustment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account types with their configurations
     */
    public function getAccountTypes(): JsonResponse
    {
        $accountTypes = [
            'cash' => [
                'name' => 'Cash',
                'description' => 'Physical cash and petty cash',
                'icon' => 'account_balance_wallet',
                'color' => '#4CAF50',
                'supports_credit_limit' => false,
                'default_include_in_net_worth' => true,
            ],
            'bank' => [
                'name' => 'Bank Account',
                'description' => 'Checking and savings accounts',
                'icon' => 'account_balance',
                'color' => '#2196F3',
                'supports_credit_limit' => false,
                'default_include_in_net_worth' => true,
            ],
            'credit_card' => [
                'name' => 'Credit Card',
                'description' => 'Credit card accounts',
                'icon' => 'credit_card',
                'color' => '#F44336',
                'supports_credit_limit' => true,
                'default_include_in_net_worth' => true,
            ],
            'investment' => [
                'name' => 'Investment',
                'description' => 'Investment and brokerage accounts',
                'icon' => 'trending_up',
                'color' => '#FF9800',
                'supports_credit_limit' => false,
                'default_include_in_net_worth' => true,
            ],
            'ewallet' => [
                'name' => 'E-Wallet',
                'description' => 'Digital wallets and payment apps',
                'icon' => 'account_balance_wallet',
                'color' => '#9C27B0',
                'supports_credit_limit' => false,
                'default_include_in_net_worth' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $accountTypes
        ]);
    }

    /**
     * Get account summary statistics
     */
    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = $user->accounts()->where('is_active', true)->get();

        $summary = [
            'total_accounts' => $accounts->count(),
            'total_balance' => $accounts->sum('balance'),
            'net_worth' => $accounts->where('include_in_net_worth', true)->sum('balance'),
            'accounts_by_type' => $accounts->groupBy('type')->map(function ($typeAccounts, $type) {
                return [
                    'count' => $typeAccounts->count(),
                    'total_balance' => $typeAccounts->sum('balance'),
                    'average_balance' => $typeAccounts->avg('balance'),
                ];
            }),
            'currency' => $user->currency,
            'currency_symbol' => $user->getCurrencySymbol(),
        ];

        // Calculate credit utilization for credit cards
        $creditCards = $accounts->where('type', 'credit_card');
        if ($creditCards->count() > 0) {
            $totalCreditUsed = $creditCards->sum(function ($account) {
                return abs($account->balance);
            });
            $totalCreditLimit = $creditCards->sum('credit_limit');
            $creditUtilization = $totalCreditLimit > 0 ? ($totalCreditUsed / $totalCreditLimit) * 100 : 0;

            $summary['credit_utilization'] = [
                'total_credit_used' => $totalCreditUsed,
                'total_credit_limit' => $totalCreditLimit,
                'utilization_percentage' => round($creditUtilization, 2),
                'available_credit' => $totalCreditLimit - $totalCreditUsed,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Bulk update accounts
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*.id' => ['required', 'integer', 'exists:accounts,id'],
            'accounts.*.name' => ['sometimes', 'string', 'max:255'],
            'accounts.*.color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'accounts.*.icon' => ['sometimes', 'string', 'max:50'],
            'accounts.*.is_active' => ['sometimes', 'boolean'],
            'accounts.*.include_in_net_worth' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $updatedAccounts = [];

        try {
            DB::beginTransaction();

            foreach ($request->accounts as $accountData) {
                $account = $user->accounts()->find($accountData['id']);

                if (!$account) {
                    continue;
                }

                $account->update(array_filter($accountData, function($key) {
                    return $key !== 'id';
                }, ARRAY_FILTER_USE_KEY));

                $updatedAccounts[] = new AccountResource($account->fresh());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Accounts updated successfully',
                'data' => $updatedAccounts
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
