<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\CreateTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Requests\Transaction\BulkCreateTransactionRequest;
use App\Http\Requests\Transaction\BulkDeleteTransactionRequest;
use App\Http\Requests\Transaction\ImportTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Get all user transactions with filtering and pagination
     *
     * @OA\Get(
     *     path="/api/transactions",
     *     operationId="getTransactions",
     *     tags={"Transactions"},
     *     summary="Get all user transactions with filtering and pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="account_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense", "transfer"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="min_amount", in="query", required=false, @OA\Schema(type="number", minimum=0)),
     *     @OA\Parameter(name="max_amount", in="query", required=false, @OA\Schema(type="number", minimum=0)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=255)),
     *     @OA\Parameter(name="tags[]", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="is_cleared", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_recurring", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", enum={"date", "amount", "description", "created_at"})),
     *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TransactionResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="summary", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'start_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'date_to' => ['nullable', 'date'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'search' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_cleared' => ['nullable', 'boolean'],
            'is_recurring' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:date,amount,description,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = $request->user()->transactions()->with(['account', 'category', 'transferAccount']);

        // Apply filters
        $this->applyTransactionFilters($query, $request);

        // Apply sorting
        $sortBy = $request->input('sort_by', 'date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->input('per_page', 20);
        $transactions = $query->paginate($perPage);

        // Calculate summary statistics
        $summaryQuery = clone $query;
        $summaryStats = $this->calculateTransactionSummary($summaryQuery);

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'summary' => $summaryStats,
            ]
        ]);
    }

    /**
     * Create a new transaction
     *
     * @OA\Post(
     *     path="/api/transactions",
     *     operationId="createTransaction",
     *     tags={"Transactions"},
     *     summary="Create a new transaction",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTransactionRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transaction created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/TransactionResource")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CreateTransactionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $transaction = $this->transactionService->createTransaction($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => new TransactionResource($transaction->load(['account', 'category', 'transferAccount']))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transaction creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific transaction
     *
     * @OA\Get(
     *     path="/api/transactions/{id}",
     *     operationId="getTransaction",
     *     tags={"Transactions"},
     *     summary="Get specific transaction details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", ref="#/components/schemas/TransactionResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Transaction not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure transaction belongs to authenticated user
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $transaction->load(['account', 'category', 'transferAccount']);

        return response()->json([
            'success' => true,
            'data' => new TransactionResource($transaction)
        ]);
    }

    /**
     * Update transaction
     *
     * @OA\Put(
     *     path="/api/transactions/{id}",
     *     operationId="updateTransaction",
     *     tags={"Transactions"},
     *     summary="Update transaction",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTransactionRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/TransactionResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Transaction not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {

        // Ensure transaction belongs to authenticated user
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => new TransactionResource($updatedTransaction->load(['account', 'category', 'transferAccount']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transaction update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete transaction
     *
     * @OA\Delete(
     *     path="/api/transactions/{id}",
     *     operationId="deleteTransaction",
     *     tags={"Transactions"},
     *     summary="Delete transaction",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Transaction not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure transaction belongs to authenticated user
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $this->transactionService->deleteTransaction($transaction);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transaction deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create transactions
     *
     * @OA\Post(
     *     path="/api/transactions/bulk",
     *     operationId="bulkCreateTransactions",
     *     tags={"Transactions"},
     *     summary="Bulk create transactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="transactions", type="array",
     *                 @OA\Items(ref="#/components/schemas/CreateTransactionRequest")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transactions created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="created_count", type="integer"),
     *                 @OA\Property(property="transactions", type="array", @OA\Items(ref="#/components/schemas/TransactionResource"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function bulkCreate(BulkCreateTransactionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $transactions = $this->transactionService->bulkCreateTransactions($request->validated()['transactions']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transactions created successfully',
                'data' => [
                    'created_count' => count($transactions),
                    'transactions' => TransactionResource::collection($transactions)
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk transaction creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete transactions
     *
     * @OA\Delete(
     *     path="/api/transactions/bulk",
     *     operationId="bulkDeleteTransactions",
     *     tags={"Transactions"},
     *     summary="Bulk delete transactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="deleted_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function bulkDelete(BulkDeleteTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $transactionIds = $request->validated()['transaction_ids'];

        // Verify all transactions belong to the user
        $transactions = $user->transactions()->whereIn('id', $transactionIds)->get();

        if ($transactions->count() !== count($transactionIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some transactions were not found or do not belong to you'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $deletedCount = $this->transactionService->bulkDeleteTransactions($transactions);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transactions deleted successfully',
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk transaction deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search transactions
     *
     * @OA\Get(
     *     path="/api/transactions/search/query",
     *     operationId="searchTransactions",
     *     tags={"Transactions"},
     *     summary="Search transactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TransactionResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->transactionService->searchTransactions(
            $request->user(),
            $request->input('query'),
            $request->only(['type', 'account_id', 'category_id']),
            $request->input('limit', 20)
        );

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($results),
            'meta' => [
                'query' => $request->input('query'),
                'total_results' => $results->count(),
            ]
        ]);
    }

    /**
     * Import transactions from CSV
     *
     * @OA\Post(
     *     path="/api/transactions/import/csv",
     *     operationId="importTransactions",
     *     tags={"Transactions"},
     *     summary="Import transactions from CSV file",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="skip_duplicates", type="boolean"),
     *                 @OA\Property(property="date_format", type="string", default="Y-m-d")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="imported", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function import(ImportTransactionRequest $request): JsonResponse
    {
        try {
            $file = $request->file('csv_file');
            $mappings = $request->input('column_mappings', []);
            $options = $request->input('import_options', []);

            $result = $this->transactionService->importTransactionsFromCsv(
                $request->user(),
                $file,
                $mappings,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Transactions imported successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export transactions to CSV
     *
     * @OA\Get(
     *     path="/api/transactions/export/data",
     *     operationId="exportTransactions",
     *     tags={"Transactions"},
     *     summary="Export transactions to CSV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"csv", "xlsx"}, default="csv")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Export file",
     *         @OA\MediaType(mediaType="text/csv"),
     *         @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => ['nullable', 'string', 'in:csv,xlsx,pdf'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['nullable', 'string', 'in:income,expense,transfer'],
            'include_attachments' => ['nullable', 'boolean'],
        ]);

        try {
            $format = $request->input('format', 'csv');
            $filters = $request->only(['start_date', 'end_date', 'account_id', 'category_id', 'type']);
            $options = [
                'include_attachments' => $request->boolean('include_attachments', false),
            ];

            $exportResult = $this->transactionService->exportTransactions(
                $request->user(),
                $format,
                $filters,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'data' => [
                    'download_url' => $exportResult['download_url'],
                    'file_name' => $exportResult['file_name'],
                    'file_size' => $exportResult['file_size'],
                    'total_records' => $exportResult['total_records'],
                    'expires_at' => $exportResult['expires_at'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction statistics
     *
     * @OA\Get(
     *     path="/api/transactions/statistics/summary",
     *     operationId="getTransactionStatistics",
     *     tags={"Transactions"},
     *     summary="Get transaction statistics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"week", "month", "quarter", "year"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_income", type="number"),
     *                 @OA\Property(property="total_expenses", type="number"),
     *                 @OA\Property(property="net_income", type="number"),
     *                 @OA\Property(property="transaction_count", type="integer"),
     *                 @OA\Property(property="average_transaction", type="number"),
     *                 @OA\Property(property="largest_expense", type="number"),
     *                 @OA\Property(property="largest_income", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $period = $request->input('period', 'month');
        $filters = $request->only(['start_date', 'end_date', 'account_id', 'category_id']);

        $statistics = $this->transactionService->getTransactionStatistics(
            $request->user(),
            $period,
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Get recent transactions
     *
     * @OA\Get(
     *     path="/api/transactions/recent/list",
     *     operationId="getRecentTransactions",
     *     tags={"Transactions"},
     *     summary="Get recent transactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=10, minimum=1, maximum=50)),
     *     @OA\Response(
     *         response=200,
     *         description="Recent transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TransactionResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function recent(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $limit = $request->input('limit', 10);
        $days = $request->input('days', 30);

        $transactions = $request->user()
            ->transactions()
            ->with(['account', 'category', 'transferAccount'])
            ->where('date', '>=', now()->subDays($days))
            ->latest('date')
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'limit' => $limit,
                'days' => $days,
                'total' => $transactions->count(),
            ]
        ]);
    }

    /**
     * Apply filters to transaction query
     */
    private function applyTransactionFilters($query, Request $request): void
    {
        if ($request->filled('account_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('account_id', $request->account_id)
                    ->orWhere('transfer_account_id', $request->account_id);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('start_date') || $request->filled('date_from')) {
            $startDate = $request->input('start_date') ?? $request->input('date_from');
            $query->where('date', '>=', $startDate);
        }

        // Support both end_date and date_to (backwards compatibility)
        if ($request->filled('end_date') || $request->filled('date_to')) {
            $endDate = $request->input('end_date') ?? $request->input('date_to');
            $query->where('date', '<=', $endDate);
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tags')) {
            $tags = $request->tags;
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        if ($request->has('is_cleared')) {
            $query->where('is_cleared', $request->boolean('is_cleared'));
        }

        if ($request->has('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }
    }

    /**
     * Calculate transaction summary statistics
     */
    private function calculateTransactionSummary($query): array
    {
        // Clone the query to avoid affecting pagination
        $statsQuery = clone $query;
        $transactions = $statsQuery->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expenses = $transactions->where('type', 'expense')->sum('amount');
        $transfers = $transactions->where('type', 'transfer')->sum('amount');

        return [
            'total_transactions' => $transactions->count(),
            'total_income' => $income,
            'total_expenses' => $expenses,
            'total_transfers' => $transfers,
            'net_amount' => $income - $expenses,
            'average_transaction' => $transactions->count() > 0 ? $transactions->avg('amount') : 0,
            'transactions_by_type' => [
                'income' => $transactions->where('type', 'income')->count(),
                'expense' => $transactions->where('type', 'expense')->count(),
                'transfer' => $transactions->where('type', 'transfer')->count(),
            ],
        ];
    }
}
