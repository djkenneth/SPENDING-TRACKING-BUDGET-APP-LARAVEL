<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Requests\Account\TransferRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\AccountTransactionResource;
use App\Http\Resources\AccountSummaryResource;
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
     * Get all accounts
     *
     * @OA\Get(
     *     path="/api/accounts",
     *     operationId="getAccounts",
     *     tags={"Accounts"},
     *     summary="Get all accounts",
     *     description="Get all user accounts with optional filtering",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by account type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"cash","bank","credit_card","investment","ewallet"})
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="include_inactive",
     *         in="query",
     *         description="Include inactive accounts",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Accounts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Account")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer", example=5),
     *                 @OA\Property(property="total_balance", type="number", example=50000),
     *                 @OA\Property(property="net_worth", type="number", example=45000),
     *                 @OA\Property(property="currency", type="string", example="PHP"),
     *                 @OA\Property(property="currency_symbol", type="string", example="₱")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     * Create account
     *
     * @OA\Post(
     *     path="/api/accounts",
     *     operationId="createAccount",
     *     tags={"Accounts"},
     *     summary="Create new account",
     *     description="Create a new financial account",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","type","balance"},
     *             @OA\Property(property="name", type="string", example="Main Bank Account"),
     *             @OA\Property(property="type", type="string", enum={"cash","bank","credit_card","investment","ewallet"}, example="bank"),
     *             @OA\Property(property="balance", type="number", example=1000.50),
     *             @OA\Property(property="currency", type="string", example="PHP"),
     *             @OA\Property(property="color", type="string", example="#2196F3"),
     *             @OA\Property(property="icon", type="string", example="account_balance"),
     *             @OA\Property(property="description", type="string", example="My primary savings account"),
     *             @OA\Property(property="account_number", type="string", example="1234567890"),
     *             @OA\Property(property="institution", type="string", example="BDO"),
     *             @OA\Property(property="credit_limit", type="number", example=50000, description="For credit cards only"),
     *             @OA\Property(property="include_in_net_worth", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Account created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Account")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateAccountRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $account = $request->user()->accounts()->create($request->validated());

            // Create initial balance history record
            $this->accountService->recordBalanceHistory($account, $account->balance, 'initial', $account->balance);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => new AccountResource($account)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Account creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account details
     *
     * @OA\Get(
     *     path="/api/accounts/{account}",
     *     operationId="getAccount",
     *     tags={"Accounts"},
     *     summary="Get account details",
     *     description="Get specific account details with statistics",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", allOf={
     *                 @OA\Schema(ref="#/components/schemas/Account"),
     *                 @OA\Schema(
     *                     @OA\Property(property="statistics", type="object",
     *                         @OA\Property(property="total_income", type="number", example=10000),
     *                         @OA\Property(property="total_expenses", type="number", example=8000),
     *                         @OA\Property(property="transaction_count", type="integer", example=150),
     *                         @OA\Property(property="average_transaction", type="number", example=133.33)
     *                     )
     *                 )
     *             })
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found")
     * )
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
     *
     * @OA\Put(
     *     path="/api/accounts/{account}",
     *     operationId="updateAccount",
     *     tags={"Accounts"},
     *     summary="Update account",
     *     description="Update account information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Account Name"),
     *             @OA\Property(property="balance", type="number", example=2000),
     *             @OA\Property(property="color", type="string", example="#4CAF50"),
     *             @OA\Property(property="icon", type="string", example="account_balance_wallet"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="credit_limit", type="number", example=75000),
     *             @OA\Property(property="include_in_net_worth", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Account")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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

        try {
            DB::beginTransaction();

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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => new AccountResource($account->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Account update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete account
     *
     * @OA\Delete(
     *     path="/api/accounts/{account}",
     *     operationId="deleteAccount",
     *     tags={"Accounts"},
     *     summary="Delete account",
     *     description="Delete an account",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot delete account"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found")
     * )
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

        try {
            DB::beginTransaction();

            $account->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account transactions
     *
     * @OA\Get(
     *     path="/api/accounts/{account}/transactions",
     *     operationId="getAccountTransactions",
     *     tags={"Accounts"},
     *     summary="Get account transactions",
     *     description="Get transactions for a specific account",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", description="Transaction type", @OA\Schema(type="string", enum={"income","expense","transfer"})),
     *     @OA\Parameter(name="start_date", in="query", description="Start date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", description="End date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="category_id", in="query", description="Category ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Transaction")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found")
     * )
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
     *
     * @OA\Get(
     *     path="/api/accounts/{account}/balance-history",
     *     operationId="getAccountBalanceHistory",
     *     tags={"Accounts"},
     *     summary="Get balance history",
     *     description="Get account balance history over time",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(name="period", in="query", description="Period", @OA\Schema(type="string", enum={"week","month","quarter","year"})),
     *     @OA\Parameter(name="start_date", in="query", description="Start date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", description="End date", @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Balance history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="date", type="string", format="date"),
     *                     @OA\Property(property="balance", type="number"),
     *                     @OA\Property(property="change_amount", type="number"),
     *                     @OA\Property(property="change_type", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found")
     * )
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
     * Transfer between accounts
     *
     * @OA\Post(
     *     path="/api/accounts/transfer",
     *     operationId="transferBetweenAccounts",
     *     tags={"Accounts"},
     *     summary="Transfer between accounts",
     *     description="Transfer money between two accounts",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_account_id","to_account_id","amount"},
     *             @OA\Property(property="from_account_id", type="integer", example=1),
     *             @OA\Property(property="to_account_id", type="integer", example=2),
     *             @OA\Property(property="amount", type="number", example=500),
     *             @OA\Property(property="description", type="string", example="Transfer to savings"),
     *             @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *             @OA\Property(property="notes", type="string", example="Monthly savings transfer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transfer completed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transfer_id", type="integer"),
     *                 @OA\Property(property="from_transaction", ref="#/components/schemas/Transaction"),
     *                 @OA\Property(property="to_transaction", ref="#/components/schemas/Transaction"),
     *                 @OA\Property(property="from_account_balance", type="number"),
     *                 @OA\Property(property="to_account_balance", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
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
     *
     * @OA\Post(
     *     path="/api/accounts/{account}/adjust-balance",
     *     operationId="adjustAccountBalance",
     *     tags={"Accounts"},
     *     summary="Adjust account balance",
     *     description="Manually adjust account balance to match actual balance",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"balance","reason"},
     *             @OA\Property(property="balance", type="number", example=5000),
     *             @OA\Property(property="reason", type="string", example="Monthly reconciliation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Balance adjusted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account balance adjusted successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Account")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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

        try {
            DB::beginTransaction();

            $this->accountService->syncAccountBalance($account, $request->balance, $request->reason);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account balance adjusted successfully',
                'data' => new AccountResource($account->fresh())
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
     * Get account types
     *
     * @OA\Get(
     *     path="/api/accounts/types",
     *     operationId="getAccountTypes",
     *     tags={"Accounts"},
     *     summary="Get account types",
     *     description="Get available account types and their configurations",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Account types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\AdditionalProperties(
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="icon", type="string"),
     *                     @OA\Property(property="color", type="string"),
     *                     @OA\Property(property="supports_credit_limit", type="boolean"),
     *                     @OA\Property(property="default_include_in_net_worth", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     * Get accounts summary
     *
     * @OA\Get(
     *     path="/api/accounts/summary",
     *     operationId="getAccountsSummary",
     *     tags={"Accounts"},
     *     summary="Get accounts summary",
     *     description="Get summary statistics of all accounts",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_accounts", type="integer", example=5),
     *                 @OA\Property(property="total_balance", type="number", example=50000),
     *                 @OA\Property(property="net_worth", type="number", example=45000),
     *                 @OA\Property(property="total_assets", type="number", example=55000),
     *                 @OA\Property(property="total_liabilities", type="number", example=10000),
     *                 @OA\Property(property="accounts_by_type", type="object"),
     *                 @OA\Property(property="currency", type="string", example="PHP")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
            'data' => new AccountSummaryResource([
                'total_accounts' => $summary['total_accounts'],
                'total_balance' => $summary['total_balance'],
                'net_worth' => $summary['net_worth'],
                'accounts_by_type' => $summary['accounts_by_type'],
                'currency' => $summary['currency'],
                'currency_symbol' => $summary['currency_symbol'],
                'credit_utilization' => $summary['credit_utilization'] ?? null,
            ])
        ]);
    }

    /**
     * Get performance metrics
     *
     * @OA\Get(
     *     path="/api/accounts/{account}/performance-metrics",
     *     operationId="getAccountPerformanceMetrics",
     *     tags={"Accounts"},
     *     summary="Get performance metrics",
     *     description="Get account performance metrics over time",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="months",
     *         in="query",
     *         description="Number of months to analyze (1-24)",
     *         required=false,
     *         @OA\Schema(type="integer", default=6)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="monthly_data", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="month", type="string"),
     *                         @OA\Property(property="income", type="number"),
     *                         @OA\Property(property="expenses", type="number"),
     *                         @OA\Property(property="net", type="number"),
     *                         @OA\Property(property="transaction_count", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(property="average_income", type="number"),
     *                 @OA\Property(property="average_expenses", type="number"),
     *                 @OA\Property(property="average_net", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found")
     * )
     */
    public function getPerformanceMetrics(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $months = $request->input('months', 6);
        $metrics = $this->accountService->getAccountPerformanceMetrics($account, $months);

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Sync account balance
     *
     * @OA\Post(
     *     path="/api/accounts/{account}/sync-balance",
     *     operationId="syncAccountBalance",
     *     tags={"Accounts"},
     *     summary="Sync account balance",
     *     description="Synchronize account balance with actual balance",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="account",
     *         in="path",
     *         description="Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"actual_balance"},
     *             @OA\Property(property="actual_balance", type="number", example=10000),
     *             @OA\Property(property="reason", type="string", example="Bank statement reconciliation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Balance synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Account balance synced successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="old_balance", type="number"),
     *                 @OA\Property(property="new_balance", type="number"),
     *                 @OA\Property(property="difference", type="number"),
     *                 @OA\Property(property="account", ref="#/components/schemas/Account")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Account not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function syncBalance(Request $request, Account $account): JsonResponse
    {
        // Ensure account belongs to authenticated user
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        $request->validate([
            'actual_balance' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            $oldBalance = $account->balance;
            $reason = $request->input('reason', 'Balance sync');

            $this->accountService->syncAccountBalance($account, $request->actual_balance, $reason);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account balance synced successfully',
                'data' => [
                    'old_balance' => $oldBalance,
                    'new_balance' => $account->fresh()->balance,
                    'difference' => $account->fresh()->balance - $oldBalance,
                    'account' => new AccountResource($account->fresh()),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Balance sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update accounts
     *
     * @OA\Put(
     *     path="/api/accounts/bulk/update",
     *     operationId="bulkUpdateAccounts",
     *     tags={"Accounts"},
     *     summary="Bulk update accounts",
     *     description="Update multiple accounts at once",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"accounts"},
     *             @OA\Property(property="accounts", type="array",
     *                 @OA\Items(type="object",
     *                     required={"id"},
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Updated Name"),
     *                     @OA\Property(property="color", type="string", example="#FF0000"),
     *                     @OA\Property(property="icon", type="string", example="account_balance"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="include_in_net_worth", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Accounts updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Accounts updated successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Account"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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

                $account->update(array_filter($accountData, function ($key) {
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
