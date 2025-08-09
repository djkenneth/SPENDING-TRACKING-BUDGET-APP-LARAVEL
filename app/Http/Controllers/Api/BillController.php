<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bill\CreateBillRequest;
use App\Http\Requests\Bill\UpdateBillRequest;
use App\Http\Requests\Bill\MarkAsPaidRequest;
use App\Http\Resources\BillResource;
use App\Http\Resources\BillCollection;
use App\Models\Bill;
use App\Services\BillService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillController extends Controller
{
    protected BillService $billService;

    public function __construct(BillService $billService)
    {
        $this->billService = $billService;
    }

    /**
     * Get all user bills with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'string', 'in:active,paid,overdue,cancelled'],
            'frequency' => ['nullable', 'string', 'in:weekly,bi-weekly,monthly,quarterly,semi-annually,annually'],
            'is_recurring' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:name,amount,due_date,status,frequency'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $query = $request->user()->bills()->with(['category']);

            // Apply filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('frequency')) {
                $query->where('frequency', $request->input('frequency'));
            }

            if ($request->has('is_recurring')) {
                $query->where('is_recurring', $request->boolean('is_recurring'));
            }

            // Date range filter
            if ($request->has('start_date')) {
                $query->where('due_date', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->where('due_date', '<=', $request->input('end_date'));
            }

            // Search
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('notes', 'like', "%{$searchTerm}%");
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'due_date');
            $sortOrder = $request->input('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Apply pagination
            $perPage = $request->input('per_page', 15);
            $bills = $query->paginate($perPage);

            // Update overdue statuses
            $this->billService->updateOverdueStatuses($request->user());

            return response()->json([
                'success' => true,
                'data' => BillResource::collection($bills),
                'meta' => [
                    'current_page' => $bills->currentPage(),
                    'from' => $bills->firstItem(),
                    'last_page' => $bills->lastPage(),
                    'per_page' => $bills->perPage(),
                    'to' => $bills->lastItem(),
                    'total' => $bills->total(),
                ],
                'summary' => $this->billService->getBillsSummary($request->user()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bills',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new bill
     */
    public function store(CreateBillRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $bill = $this->billService->createBill(
                $request->user(),
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill created successfully',
                'data' => new BillResource($bill->load('category'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bill
     */
    public function show(Request $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        try {
            $bill->load('category');

            return response()->json([
                'success' => true,
                'data' => new BillResource($bill)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a bill
     */
    public function update(UpdateBillRequest $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $bill = $this->billService->updateBill(
                $bill,
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill updated successfully',
                'data' => new BillResource($bill->load('category'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bill
     */
    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $this->billService->deleteBill($bill);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a bill as paid
     */
    public function markAsPaid(MarkAsPaidRequest $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $bill = $this->billService->markBillAsPaid(
                $bill,
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill marked as paid',
                'data' => new BillResource($bill->load('category'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark bill as paid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming bills
     */
    public function getUpcomingBills(Request $request): JsonResponse
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $days = $request->input('days', 30);
            $limit = $request->input('limit', 10);

            $upcomingBills = $this->billService->getUpcomingBills(
                $request->user(),
                $days,
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => BillResource::collection($upcomingBills),
                'meta' => [
                    'days_ahead' => $days,
                    'count' => $upcomingBills->count(),
                    'total_amount' => $upcomingBills->sum('amount'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming bills',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue bills
     */
    public function getOverdueBills(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $limit = $request->input('limit', null);

            $overdueBills = $this->billService->getOverdueBills(
                $request->user(),
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => BillResource::collection($overdueBills),
                'meta' => [
                    'count' => $overdueBills->count(),
                    'total_amount' => $overdueBills->sum('amount'),
                    'oldest_overdue_days' => $this->billService->getOldestOverdueDays($overdueBills),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overdue bills',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bill payment history
     */
    public function getPaymentHistory(Request $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $paymentHistory = $this->billService->getPaymentHistory(
                $bill,
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('limit')
            );

            return response()->json([
                'success' => true,
                'data' => $paymentHistory,
                'meta' => [
                    'total_payments' => count($paymentHistory),
                    'total_amount_paid' => collect($paymentHistory)->sum('amount'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bill statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $statistics = $this->billService->getBillStatistics(
                $request->user(),
                $request->input('period', 'month'),
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bill statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a bill for creating similar ones
     */
    public function duplicate(Request $request, Bill $bill): JsonResponse
    {
        // Ensure the bill belongs to the authenticated user
        if ($bill->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found'
            ], 404);
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        try {
            DB::beginTransaction();

            $duplicatedBill = $this->billService->duplicateBill(
                $bill,
                $request->input('name'),
                $request->input('due_date')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bill duplicated successfully',
                'data' => new BillResource($duplicatedBill->load('category'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
