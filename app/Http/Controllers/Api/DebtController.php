<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Debt\CreateDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Http\Requests\Debt\RecordPaymentRequest;
use App\Http\Resources\DebtResource;
use App\Http\Resources\DebtPaymentResource;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DebtController extends Controller
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Get all user debts with filtering and pagination
     *
     * @OA\Get(
     *     path="/api/debts",
     *     summary="Get list of debts",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by debt type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"active", "paid_off", "closed"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"name", "current_balance", "interest_rate", "minimum_payment", "due_date"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Debts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DebtResource")),
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
            'type' => ['nullable', 'string', 'in:credit_card,personal_loan,mortgage,auto_loan,student_loan'],
            'status' => ['nullable', 'string', 'in:active,paid_off,closed'],
            'sort_by' => ['nullable', 'string', 'in:name,current_balance,interest_rate,due_date,created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        try {
            $user = $request->user();
            $query = $user->debts();

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Apply pagination
            $perPage = $request->input('per_page', 15);
            $debts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => DebtResource::collection($debts),
                'meta' => [
                    'current_page' => $debts->currentPage(),
                    'from' => $debts->firstItem(),
                    'last_page' => $debts->lastPage(),
                    'per_page' => $debts->perPage(),
                    'to' => $debts->lastItem(),
                    'total' => $debts->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve debts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new debt
     *
     * @OA\Post(
     *     path="/api/debts",
     *     summary="Create a new debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type", "original_balance", "current_balance", "interest_rate", "minimum_payment", "due_date", "payment_frequency"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="type", type="string", enum={"credit_card", "personal_loan", "mortgage", "auto_loan", "student_loan"}),
     *             @OA\Property(property="original_balance", type="number", format="float"),
     *             @OA\Property(property="current_balance", type="number", format="float"),
     *             @OA\Property(property="interest_rate", type="number", format="float"),
     *             @OA\Property(property="minimum_payment", type="number", format="float"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="payment_frequency", type="string", enum={"monthly", "weekly", "bi-weekly"}),
     *             @OA\Property(property="status", type="string", enum={"active", "paid_off", "closed"}, default="active"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Debt created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/DebtResource")
     *         )
     *     )
     * )
     */
    public function store(CreateDebtRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $debt = $this->debtService->createDebt(
                $request->user(),
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Debt created successfully',
                'data' => new DebtResource($debt)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create debt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific debt
     *
     * @OA\Get(
     *     path="/api/debts/{id}",
     *     summary="Get a specific debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Debt retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", ref="#/components/schemas/DebtResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Debt not found")
     * )
     */
    public function show(Request $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        try {
            $debt->load('payments.transaction');

            return response()->json([
                'success' => true,
                'data' => new DebtResource($debt)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve debt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a debt
     *
     * @OA\Put(
     *     path="/api/debts/{id}",
     *     summary="Update a debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(ref="#/components/schemas/UpdateDebtRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Debt updated successfully"
     *     )
     * )
     */
    public function update(UpdateDebtRequest $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $debt = $this->debtService->updateDebt($debt, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Debt updated successfully',
                'data' => new DebtResource($debt)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update debt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a debt
     *
     * @OA\Delete(
     *     path="/api/debts/{id}",
     *     summary="Delete a debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Debt deleted successfully"
     *     )
     * )
     */
    public function destroy(Request $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $this->debtService->deleteDebt($debt);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Debt deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete debt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record a debt payment
     *
     * @OA\Post(
     *     path="/api/debts/{id}/payment",
     *     summary="Record a debt payment",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "payment_date"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="payment_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment recorded successfully"
     *     )
     * )
     */
    public function recordPayment(RecordPaymentRequest $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $payment = $this->debtService->recordPayment(
                $debt,
                $request->validated()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => new DebtPaymentResource($payment)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get debt payoff schedule
     *
     * @OA\Get(
     *     path="/api/debts/{id}/payoff-schedule",
     *     summary="Get payoff schedule for a debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="extra_payment",
     *         in="query",
     *         description="Extra payment amount",
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payoff schedule retrieved successfully"
     *     )
     * )
     */
    public function getPayoffSchedule(Request $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        $request->validate([
            'extra_payment' => ['nullable', 'numeric', 'min:0'],
            'strategy' => ['nullable', 'string', 'in:avalanche,snowball,minimum'],
        ]);

        try {
            $schedule = $this->debtService->calculatePayoffSchedule(
                $debt,
                $request->input('extra_payment', 0),
                $request->input('strategy', 'minimum')
            );

            return response()->json([
                'success' => true,
                'data' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payoff schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get debt summary statistics
     *
     * @OA\Get(
     *     path="/api/debts/summary",
     *     summary="Get debts summary statistics",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Debts summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_debt", type="number"),
     *                 @OA\Property(property="monthly_payments", type="number"),
     *                 @OA\Property(property="average_interest_rate", type="number"),
     *                 @OA\Property(property="debts_by_type", type="object"),
     *                 @OA\Property(property="active_debts_count", type="integer"),
     *                 @OA\Property(property="paid_off_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $debts = $user->debts()->where('status', 'active')->get();

            $summary = [
                'total_debts' => $debts->count(),
                'total_original_balance' => $debts->sum('original_balance'),
                'total_current_balance' => $debts->sum('current_balance'),
                'total_paid_off' => $debts->sum('original_balance') - $debts->sum('current_balance'),
                'average_interest_rate' => $debts->avg('interest_rate'),
                'total_minimum_payment' => $debts->sum('minimum_payment'),
                'debts_by_type' => $debts->groupBy('type')->map(function ($typeDebts, $type) {
                    return [
                        'count' => $typeDebts->count(),
                        'total_balance' => $typeDebts->sum('current_balance'),
                        'average_interest_rate' => round($typeDebts->avg('interest_rate'), 2),
                        'total_minimum_payment' => $typeDebts->sum('minimum_payment'),
                    ];
                }),
                'highest_interest_debt' => $debts->sortByDesc('interest_rate')->first(),
                'largest_balance_debt' => $debts->sortByDesc('current_balance')->first(),
                'next_due_debt' => $debts->sortBy('due_date')->first(),
                'progress_percentage' => $debts->sum('original_balance') > 0
                    ? round((($debts->sum('original_balance') - $debts->sum('current_balance')) / $debts->sum('original_balance')) * 100, 2)
                    : 0,
            ];

            // Calculate estimated payoff date with minimum payments
            $estimatedPayoff = $this->debtService->calculateTotalPayoffTime($debts);
            $summary['estimated_payoff_date'] = $estimatedPayoff['date'] ?? null;
            $summary['estimated_total_interest'] = $estimatedPayoff['total_interest'] ?? 0;

            // Get recent payment history
            $recentPayments = DebtPayment::whereIn('debt_id', $debts->pluck('id'))
                ->orderBy('payment_date', 'desc')
                ->limit(10)
                ->get();

            $summary['recent_payments'] = DebtPaymentResource::collection($recentPayments);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get debt summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for a debt
     *
     * @OA\Get(
     *     path="/api/debts/{id}/payment-history",
     *     summary="Get payment history for a debt",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment history retrieved successfully"
     *     )
     * )
     */
    public function getPaymentHistory(Request $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $query = $debt->payments()->with('transaction');

            // Apply date filters
            if ($request->has('start_date')) {
                $query->where('payment_date', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->where('payment_date', '<=', $request->input('end_date'));
            }

            // Order by payment date
            $query->orderBy('payment_date', 'desc');

            // Apply pagination
            $perPage = $request->input('per_page', 15);
            $payments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => DebtPaymentResource::collection($payments),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'from' => $payments->firstItem(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'to' => $payments->lastItem(),
                    'total' => $payments->total(),
                ],
                'summary' => [
                    'total_paid' => $debt->payments()->sum('amount'),
                    'total_principal_paid' => $debt->payments()->sum('principal'),
                    'total_interest_paid' => $debt->payments()->sum('interest'),
                    'payment_count' => $debt->payments()->count(),
                    'average_payment' => round($debt->payments()->avg('amount'), 2),
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
     * Mark debt as paid off
     *
     * @OA\Post(
     *     path="/api/debts/{id}/mark-paid-off",
     *     summary="Mark a debt as paid off",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Debt ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Debt marked as paid off successfully"
     *     )
     * )
     */
    public function markAsPaidOff(Request $request, Debt $debt): JsonResponse
    {
        // Ensure the debt belongs to the authenticated user
        if ($debt->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Debt not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $debt->update([
                'status' => 'paid_off',
                'current_balance' => 0
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Debt marked as paid off',
                'data' => new DebtResource($debt)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark debt as paid off',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get debt types with their configurations
     *
     * @OA\Get(
     *     path="/api/debts/types",
     *     summary="Get available debt types",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Debt types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="string", enum={"credit_card", "personal_loan", "mortgage", "auto_loan", "student_loan", "other"})
     *             )
     *         )
     *     )
     * )
     */
    public function getDebtTypes(): JsonResponse
    {
        $debtTypes = [
            'credit_card' => [
                'name' => 'Credit Card',
                'description' => 'Credit card debt',
                'icon' => 'credit_card',
                'color' => '#F44336',
                'typical_interest_rate' => '15-25%',
                'payment_frequency' => 'monthly',
            ],
            'personal_loan' => [
                'name' => 'Personal Loan',
                'description' => 'Personal or unsecured loan',
                'icon' => 'account_balance',
                'color' => '#FF9800',
                'typical_interest_rate' => '6-36%',
                'payment_frequency' => 'monthly',
            ],
            'mortgage' => [
                'name' => 'Mortgage',
                'description' => 'Home mortgage or real estate loan',
                'icon' => 'home',
                'color' => '#4CAF50',
                'typical_interest_rate' => '3-7%',
                'payment_frequency' => 'monthly',
            ],
            'auto_loan' => [
                'name' => 'Auto Loan',
                'description' => 'Car or vehicle loan',
                'icon' => 'directions_car',
                'color' => '#2196F3',
                'typical_interest_rate' => '3-10%',
                'payment_frequency' => 'monthly',
            ],
            'student_loan' => [
                'name' => 'Student Loan',
                'description' => 'Education or student loan',
                'icon' => 'school',
                'color' => '#9C27B0',
                'typical_interest_rate' => '3-8%',
                'payment_frequency' => 'monthly',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $debtTypes
        ]);
    }

    /**
     * Calculate debt consolidation options
     *
     * @OA\Post(
     *     path="/api/debts/consolidation-options",
     *     summary="Get debt consolidation options",
     *     tags={"Debts"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="debt_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="consolidation_rate", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Consolidation options calculated successfully"
     *     )
     * )
     */
    public function getConsolidationOptions(Request $request): JsonResponse
    {
        $request->validate([
            'debt_ids' => ['required', 'array', 'min:2'],
            'debt_ids.*' => ['required', 'integer', 'exists:debts,id'],
            'new_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'loan_term_months' => ['nullable', 'integer', 'min:1', 'max:360'],
        ]);

        try {
            $user = $request->user();
            $debts = $user->debts()->whereIn('id', $request->input('debt_ids'))->get();

            if ($debts->count() !== count($request->input('debt_ids'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more debts not found'
                ], 404);
            }

            $consolidationOptions = $this->debtService->calculateConsolidationOptions(
                $debts,
                $request->input('new_interest_rate'),
                $request->input('loan_term_months')
            );

            return response()->json([
                'success' => true,
                'data' => $consolidationOptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate consolidation options',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
