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
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
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

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
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
