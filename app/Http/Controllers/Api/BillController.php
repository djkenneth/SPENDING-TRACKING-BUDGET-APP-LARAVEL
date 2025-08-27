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
     *
     * @OA\Get(
     *     path="/api/bills",
     *     summary="Get list of bills",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"active", "paid", "overdue", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="frequency",
     *         in="query",
     *         description="Filter by frequency",
     *         @OA\Schema(type="string", enum={"monthly", "weekly", "quarterly", "annually"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"name", "amount", "due_date", "status"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bills retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BillResource")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/bills",
     *     summary="Create a new bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "amount", "due_date", "frequency", "category_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="frequency", type="string", enum={"monthly", "weekly", "quarterly", "annually"}),
     *             @OA\Property(property="reminder_days", type="integer", default=3),
     *             @OA\Property(property="is_recurring", type="boolean", default=true),
     *             @OA\Property(property="color", type="string"),
     *             @OA\Property(property="icon", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bill created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillResource")
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/bills/{id}",
     *     summary="Get a specific bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bill retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Bill not found")
     * )
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
     *
     * @OA\Put(
     *     path="/api/bills/{id}",
     *     summary="Update a bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/UpdateBillRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bill updated successfully"
     *     )
     * )
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
     *
     * @OA\Delete(
     *     path="/api/bills/{id}",
     *     summary="Delete a bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bill deleted successfully"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/bills/{id}/pay",
     *     summary="Mark a bill as paid",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "payment_date"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="payment_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string"),
     *             @OA\Property(property="create_transaction", type="boolean", default=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bill marked as paid successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/bills/status/upcoming",
     *     summary="Get upcoming bills",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to look ahead",
     *         @OA\Schema(type="integer", default=7)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upcoming bills retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/bills/status/overdue",
     *     summary="Get overdue bills",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Overdue bills retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/bills/{id}/payment-history",
     *     summary="Get payment history for a bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for history",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for history",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limit number of results",
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_payments", type="integer"),
     *                 @OA\Property(property="total_amount_paid", type="number")
     *             )
     *         )
     *     )
     * )
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
     *
     * @OA\Get(
     *     path="/api/bills/analytics/statistics",
     *     summary="Get bill statistics",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Statistics period",
     *         @OA\Schema(type="string", enum={"month", "quarter", "year"})
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bill statistics retrieved successfully"
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/bills/{id}/duplicate",
     *     summary="Duplicate a bill",
     *     tags={"Bills"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bill ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="due_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bill duplicated successfully"
     *     )
     * )
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
