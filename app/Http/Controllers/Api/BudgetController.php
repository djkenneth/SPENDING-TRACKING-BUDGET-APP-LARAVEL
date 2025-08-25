<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\CreateBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Get all user budgets with filtering
     *
     * @OA\Get(
     *     path="/api/budgets",
     *     operationId="getBudgets",
     *     tags={"Budgets"},
     *     summary="Get all user budgets with filtering",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"weekly", "monthly", "yearly"})),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="include_inactive", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", enum={"name", "amount", "spent", "start_date", "created_at"})),
     *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Budget")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="active_count", type="integer"),
     *                 @OA\Property(property="inactive_count", type="integer"),
     *                 @OA\Property(property="total_budgeted", type="number"),
     *                 @OA\Property(property="total_spent", type="number"),
     *                 @OA\Property(property="by_period", type="object"),
     *                 @OA\Property(property="statistics", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'period' => ['nullable', 'string', 'in:weekly,monthly,yearly'],
            'is_active' => ['nullable', 'boolean'],
            'include_inactive' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_by' => ['nullable', 'string', 'in:name,amount,spent,start_date,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = $request->user()->budgets()->with(['category']);

        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'start_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $budgets = $query->get();

        // Calculate budget statistics
        $statistics = $this->budgetService->getBudgetStatistics($request->user());

        return response()->json([
            'success' => true,
            'data' => BudgetResource::collection($budgets),
            'meta' => [
                'total' => $budgets->count(),
                'active_count' => $budgets->where('is_active', true)->count(),
                'inactive_count' => $budgets->where('is_active', false)->count(),
                'total_budgeted' => $budgets->sum('amount'),
                'total_spent' => $budgets->sum('spent'),
                'by_period' => $budgets->groupBy('period')->map->count(),
                'statistics' => $statistics,
            ]
        ]);
    }

    /**
     * Create a new budget
     *
     * @OA\Post(
     *     path="/api/budgets",
     *     operationId="createBudget",
     *     tags={"Budgets"},
     *     summary="Create a new budget",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateBudgetRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Budget created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Budget")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CreateBudgetRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $budget = $this->budgetService->createBudget($request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Budget created successfully',
                'data' => new BudgetResource($budget->load('category'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Budget creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific budget with analysis
     *
     * @OA\Get(
     *     path="/api/budgets/{id}",
     *     operationId="getBudget",
     *     tags={"Budgets"},
     *     summary="Get specific budget with analysis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", allOf={
     *                 @OA\Schema(ref="#/components/schemas/Budget"),
     *                 @OA\Schema(
     *                     @OA\Property(property="analysis", type="object")
     *                 )
     *             })
     *         )
     *     ),
     *     @OA\Response(response=404, description="Budget not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, Budget $budget): JsonResponse
    {
        // Ensure budget belongs to authenticated user
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        $budget->load('category');
        $budgetData = new BudgetResource($budget);
        $budgetAnalysis = $this->budgetService->getBudgetAnalysis($budget);

        return response()->json([
            'success' => true,
            'data' => array_merge($budgetData->toArray($request), [
                'analysis' => $budgetAnalysis
            ])
        ]);
    }

    /**
     * Update budget
     *
     * @OA\Put(
     *     path="/api/budgets/{id}",
     *     operationId="updateBudget",
     *     tags={"Budgets"},
     *     summary="Update budget",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateBudgetRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Budget updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Budget")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Budget not found"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        // Ensure budget belongs to authenticated user
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updatedBudget = $this->budgetService->updateBudget($budget, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Budget updated successfully',
                'data' => new BudgetResource($updatedBudget->fresh('category'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Budget update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete budget
     *
     * @OA\Delete(
     *     path="/api/budgets/{id}",
     *     operationId="deleteBudget",
     *     tags={"Budgets"},
     *     summary="Delete budget",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Budget deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Budget not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        // Ensure budget belongs to authenticated user
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $this->budgetService->deleteBudget($budget);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Budget deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Budget deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current month budgets
     *
     * @OA\Get(
     *     path="/api/budgets/current/month",
     *     operationId="getCurrentMonthBudgets",
     *     tags={"Budgets"},
     *     summary="Get current month budgets with spending",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Current month budgets",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Budget")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_budgeted", type="number"),
     *                 @OA\Property(property="total_spent", type="number"),
     *                 @OA\Property(property="remaining", type="number"),
     *                 @OA\Property(property="percentage_used", type="number"),
     *                 @OA\Property(property="budgets_count", type="integer"),
     *                 @OA\Property(property="over_budget_count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $currentDate = Carbon::now();
        $startOfMonth = $currentDate->copy()->startOfMonth();
        $endOfMonth = $currentDate->copy()->endOfMonth();

        $budgets = $request->user()->budgets()
            ->with(['category'])
            ->where('is_active', true)
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                    // Budget period overlaps with current month
                    $q->where('start_date', '<=', $endOfMonth)
                        ->where('end_date', '>=', $startOfMonth);
                });
            })
            ->orderBy('category_id')
            ->get();

        // Calculate spending for current month
        $budgetsWithSpending = $budgets->map(function ($budget) use ($startOfMonth, $endOfMonth) {
            $currentSpent = $this->budgetService->calculateCurrentSpent($budget, $startOfMonth, $endOfMonth);
            $budget->current_spent = $currentSpent;
            return $budget;
        });

        $totalBudgeted = $budgets->sum('amount');
        $totalSpent = $budgetsWithSpending->sum('current_spent');

        return response()->json([
            'success' => true,
            'data' => BudgetResource::collection($budgetsWithSpending),
            'meta' => [
                'period' => [
                    'start_date' => $startOfMonth->toDateString(),
                    'end_date' => $endOfMonth->toDateString(),
                    'name' => $currentDate->format('F Y')
                ],
                'total_budgeted' => $totalBudgeted,
                'total_spent' => $totalSpent,
                'remaining' => $totalBudgeted - $totalSpent,
                'percentage_used' => $totalBudgeted > 0 ? ($totalSpent / $totalBudgeted) * 100 : 0,
                'budgets_count' => $budgets->count(),
                'over_budget_count' => $budgetsWithSpending->filter(function ($budget) {
                    return $budget->current_spent > $budget->amount;
                })->count(),
            ]
        ]);
    }

    /**
     * Get budget analysis
     *
     * @OA\Get(
     *     path="/api/budgets/{id}/analysis",
     *     operationId="getBudgetAnalysis",
     *     tags={"Budgets"},
     *     summary="Get budget analysis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"current", "previous", "comparison"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Budget analysis data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Budget not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function analysis(Request $request, Budget $budget): JsonResponse
    {
        // Ensure budget belongs to authenticated user
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        $request->validate([
            'period' => ['nullable', 'string', 'in:current,previous,comparison'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $analysis = $this->budgetService->getDetailedBudgetAnalysis(
            $budget,
            $request->input('period', 'current'),
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'data' => $analysis
        ]);
    }

    /**
     * Reset budget for new period
     *
     * @OA\Post(
     *     path="/api/budgets/{id}/reset",
     *     operationId="resetBudget",
     *     tags={"Budgets"},
     *     summary="Reset budget for new period",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="carry_over_unused", type="boolean"),
     *             @OA\Property(property="reset_spent", type="boolean", default=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Budget reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Budget")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Budget not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function reset(Request $request, Budget $budget): JsonResponse
    {
        // Ensure budget belongs to authenticated user
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'carry_over_unused' => ['nullable', 'boolean'],
            'reset_spent' => ['nullable', 'boolean', 'default:true'],
        ]);

        try {
            DB::beginTransaction();

            $resetBudget = $this->budgetService->resetBudget(
                $budget,
                $request->start_date,
                $request->end_date,
                $request->boolean('carry_over_unused', false),
                $request->boolean('reset_spent', true)
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Budget reset successfully',
                'data' => new BudgetResource($resetBudget->fresh('category'))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Budget reset failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
