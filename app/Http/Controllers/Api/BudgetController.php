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
