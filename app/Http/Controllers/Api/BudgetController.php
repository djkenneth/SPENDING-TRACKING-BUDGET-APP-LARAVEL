<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\CreateBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService
    ) {}

    /**
     * Get all budgets with filtering and statistics
     *
     * @OA\Get(
     *     path="/api/budgets",
     *     operationId="getBudgets",
     *     tags={"Budgets"},
     *     summary="Get all budgets",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"weekly", "monthly", "quarterly", "yearly"})),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="include_inactive", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(
     *         response=200,
     *         description="List of budgets",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Budget")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'period' => ['nullable', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'is_active' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'include_inactive' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name,amount,start_date,end_date,spent,created_at'],
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
            ],
            'statistics' => $statistics,
        ]);
    }

    /**
     * Store a newly created budget
     */
    public function store(CreateBudgetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $budget = Budget::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Budget created successfully',
            'data' => new BudgetResource($budget->load('category')),
        ], 201);
    }

    /**
     * Display the specified budget
     */
    public function show(Request $request, Budget $budget): JsonResponse
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BudgetResource($budget->load('category')),
        ]);
    }

    /**
     * Update the specified budget
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        $budget->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Budget updated successfully',
            'data' => new BudgetResource($budget->fresh()->load('category')),
        ]);
    }

    /**
     * Remove the specified budget
     */
    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Budget not found'
            ], 404);
        }

        $budget->delete();

        return response()->json([
            'success' => true,
            'message' => 'Budget deleted successfully',
        ]);
    }

    /**
     * Get current period budgets (monthly, quarterly, yearly)
     *
     * @OA\Get(
     *     path="/api/budgets/current/month",
     *     operationId="getCurrentBudgets",
     *     tags={"Budgets"},
     *     summary="Get current month/quarter/year budgets",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Current budgets data")
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentDate = Carbon::now();

        // Monthly budgets
        $monthlyBudgets = $user->budgets()
            ->where('period', 'monthly')
            ->where('is_active', true)
            ->with('category')
            ->get();

        // Quarterly budgets
        $quarterlyBudgets = $user->budgets()
            ->where('period', 'quarterly')
            ->where('is_active', true)
            ->with('category')
            ->get();

        // Yearly budgets
        $yearlyBudgets = $user->budgets()
            ->where('period', 'yearly')
            ->where('is_active', true)
            ->with('category')
            ->get();

        // Calculate spending for each period
        $monthStart = $currentDate->copy()->startOfMonth();
        $monthEnd = $currentDate->copy()->endOfMonth();

        $quarterStart = $currentDate->copy()->firstOfQuarter();
        $quarterEnd = $currentDate->copy()->lastOfQuarter();

        $yearStart = $currentDate->copy()->startOfYear();
        $yearEnd = $currentDate->copy()->endOfYear();

        // Calculate monthly totals
        $monthlyTotal = $monthlyBudgets->sum('amount');
        $monthlySpent = $this->budgetService->calculatePeriodSpending($user, $monthStart, $monthEnd);

        // Calculate quarterly totals
        $quarterlyTotal = $quarterlyBudgets->sum('amount');
        $quarterlySpent = $this->budgetService->calculatePeriodSpending($user, $quarterStart, $quarterEnd);

        // Calculate yearly totals
        $yearlyTotal = $yearlyBudgets->sum('amount');
        $yearlySpent = $this->budgetService->calculatePeriodSpending($user, $yearStart, $yearEnd);

        return response()->json([
            'success' => true,
            'data' => [
                'monthly' => [
                    'period' => $currentDate->format('F Y'),
                    'start_date' => $monthStart->toDateString(),
                    'end_date' => $monthEnd->toDateString(),
                    'total_budget' => $monthlyTotal,
                    'total_spent' => $monthlySpent,
                    'remaining' => $monthlyTotal - $monthlySpent,
                    'percentage_used' => $monthlyTotal > 0 ? round(($monthlySpent / $monthlyTotal) * 100, 1) : 0,
                    'budgets' => BudgetResource::collection($monthlyBudgets),
                ],
                'quarterly' => [
                    'period' => 'Q' . $currentDate->quarter . ' ' . $currentDate->year . ' (' . $quarterStart->format('M') . ' - ' . $quarterEnd->format('M') . ')',
                    'start_date' => $quarterStart->toDateString(),
                    'end_date' => $quarterEnd->toDateString(),
                    'total_budget' => $quarterlyTotal,
                    'total_spent' => $quarterlySpent,
                    'remaining' => $quarterlyTotal - $quarterlySpent,
                    'percentage_used' => $quarterlyTotal > 0 ? round(($quarterlySpent / $quarterlyTotal) * 100, 1) : 0,
                    'budgets' => BudgetResource::collection($quarterlyBudgets),
                ],
                'yearly' => [
                    'period' => (string) $currentDate->year,
                    'start_date' => $yearStart->toDateString(),
                    'end_date' => $yearEnd->toDateString(),
                    'total_budget' => $yearlyTotal,
                    'total_spent' => $yearlySpent,
                    'remaining' => $yearlyTotal - $yearlySpent,
                    'percentage_used' => $yearlyTotal > 0 ? round(($yearlySpent / $yearlyTotal) * 100, 1) : 0,
                    'budgets' => BudgetResource::collection($yearlyBudgets),
                ],
            ],
        ]);
    }

    /**
     * Get spending velocity analysis
     *
     * @OA\Get(
     *     path="/api/budgets/spending-velocity",
     *     operationId="getSpendingVelocity",
     *     tags={"Budgets"},
     *     summary="Get spending velocity analysis",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period", in="query", required=false, @OA\Schema(type="string", enum={"monthly", "quarterly", "yearly"})),
     *     @OA\Response(response=200, description="Spending velocity data")
     * )
     */
    public function spendingVelocity(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:monthly,quarterly,yearly'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'monthly');
        $currentDate = Carbon::now();

        // Get date range based on period
        switch ($period) {
            case 'quarterly':
                $startDate = $currentDate->copy()->firstOfQuarter();
                $endDate = $currentDate->copy()->lastOfQuarter();
                break;
            case 'yearly':
                $startDate = $currentDate->copy()->startOfYear();
                $endDate = $currentDate->copy()->endOfYear();
                break;
            default: // monthly
                $startDate = $currentDate->copy()->startOfMonth();
                $endDate = $currentDate->copy()->endOfMonth();
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $daysPassed = $startDate->diffInDays($currentDate) + 1;
        $daysRemaining = max(0, $currentDate->diffInDays($endDate));

        // Get total budget and spending
        $budgets = $user->budgets()->where('period', $period)->where('is_active', true)->get();
        $totalBudget = $budgets->sum('amount');
        $totalSpent = $this->budgetService->calculatePeriodSpending($user, $startDate, $currentDate);

        // Calculate daily averages
        $dailyAverage = $daysPassed > 0 ? $totalSpent / $daysPassed : 0;
        $expectedDailySpend = $totalBudget / $totalDays;
        $projectedMonthEnd = $dailyAverage * $totalDays;

        // Determine spending rate
        $spendingRate = $expectedDailySpend > 0 ? $dailyAverage / $expectedDailySpend : 0;

        if ($spendingRate > 1.2) {
            $currentRate = 'High';
        } elseif ($spendingRate >= 0.8) {
            $currentRate = 'Normal';
        } else {
            $currentRate = 'Low';
        }

        // Check if over budget
        $overBudgetAmount = max(0, $projectedMonthEnd - $totalBudget);
        $hasWarning = $overBudgetAmount > 0;

        return response()->json([
            'success' => true,
            'data' => [
                'current_rate' => $currentRate,
                'rate_value' => round($spendingRate, 2),
                'daily_average' => round($dailyAverage, 2),
                'expected_daily_spend' => round($expectedDailySpend, 2),
                'projected_month_end' => round($projectedMonthEnd, 2),
                'days_remaining' => $daysRemaining,
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'warning' => $hasWarning ? [
                    'message' => "At current rate, you'll exceed budget by $" . number_format($overBudgetAmount, 2),
                    'amount' => $overBudgetAmount,
                ] : null,
            ],
        ]);
    }

    /**
     * Apply quick budget adjustment
     *
     * @OA\Post(
     *     path="/api/budgets/quick-adjust",
     *     operationId="quickAdjustBudgets",
     *     tags={"Budgets"},
     *     summary="Apply percentage adjustment to all budgets",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="percentage", type="number", example=5),
     *             @OA\Property(property="period", type="string", enum={"monthly", "quarterly", "yearly"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Budgets adjusted successfully")
     * )
     */
    public function quickAdjust(Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => ['required', 'numeric', 'between:-50,50'],
            'period' => ['nullable', 'string', 'in:monthly,quarterly,yearly'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $user = $request->user();
        $percentage = $request->input('percentage');
        $period = $request->input('period');
        $categoryIds = $request->input('category_ids', []);

        $query = $user->budgets()->where('is_active', true);

        if ($period) {
            $query->where('period', $period);
        }

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        $budgets = $query->get();
        $adjustedCount = 0;

        foreach ($budgets as $budget) {
            $multiplier = 1 + ($percentage / 100);
            $newAmount = round($budget->amount * $multiplier, 2);
            $budget->update(['amount' => $newAmount]);
            $adjustedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully adjusted {$adjustedCount} budgets by {$percentage}%",
            'data' => [
                'adjusted_count' => $adjustedCount,
                'percentage' => $percentage,
            ],
        ]);
    }

    /**
     * Get alert configuration
     *
     * @OA\Get(
     *     path="/api/budgets/alerts/config",
     *     operationId="getBudgetAlertConfig",
     *     tags={"Budgets"},
     *     summary="Get budget alert configuration",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Alert configuration")
     * )
     */
    public function getAlertConfig(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get alert settings from user settings or use defaults
        $settings = $user->settings()->where('key', 'budget_alerts')->first();

        $defaultConfig = [
            'budget_warning' => [
                'enabled' => true,
                'threshold' => 75,
                'email_notification' => true,
                'push_notification' => true,
            ],
            'overspending_alert' => [
                'enabled' => true,
                'threshold' => 90,
                'email_notification' => true,
                'push_notification' => true,
            ],
            'budget_exceeded' => [
                'enabled' => true,
                'threshold' => 100,
                'email_notification' => true,
                'push_notification' => false,
            ],
        ];

        $config = $settings ? json_decode($settings->value, true) : $defaultConfig;

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update alert configuration
     *
     * @OA\Put(
     *     path="/api/budgets/alerts/config",
     *     operationId="updateBudgetAlertConfig",
     *     tags={"Budgets"},
     *     summary="Update budget alert configuration",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="budget_warning", type="object"),
     *             @OA\Property(property="overspending_alert", type="object"),
     *             @OA\Property(property="budget_exceeded", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Alert configuration updated")
     * )
     */
    public function updateAlertConfig(Request $request): JsonResponse
    {
        $request->validate([
            'budget_warning' => ['nullable', 'array'],
            'budget_warning.enabled' => ['nullable', 'boolean'],
            'budget_warning.threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'budget_warning.email_notification' => ['nullable', 'boolean'],
            'budget_warning.push_notification' => ['nullable', 'boolean'],
            'overspending_alert' => ['nullable', 'array'],
            'overspending_alert.enabled' => ['nullable', 'boolean'],
            'overspending_alert.threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'overspending_alert.email_notification' => ['nullable', 'boolean'],
            'overspending_alert.push_notification' => ['nullable', 'boolean'],
            'budget_exceeded' => ['nullable', 'array'],
            'budget_exceeded.enabled' => ['nullable', 'boolean'],
            'budget_exceeded.threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'budget_exceeded.email_notification' => ['nullable', 'boolean'],
            'budget_exceeded.push_notification' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $user->settings()->updateOrCreate(
            ['key' => 'budget_alerts'],
            ['value' => json_encode($request->only(['budget_warning', 'overspending_alert', 'budget_exceeded']))]
        );

        return response()->json([
            'success' => true,
            'message' => 'Alert configuration updated successfully',
            'data' => $request->only(['budget_warning', 'overspending_alert', 'budget_exceeded']),
        ]);
    }

    /**
     * Get budget vs actual comparison
     *
     * @OA\Get(
     *     path="/api/budgets/comparison",
     *     operationId="getBudgetComparison",
     *     tags={"Budgets"},
     *     summary="Get budget vs actual comparison",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Budget comparison data")
     * )
     */
    public function comparison(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:monthly,quarterly,yearly'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'monthly');
        $limit = $request->input('limit', 6);
        $currentDate = Carbon::now();

        // Get date range based on period
        switch ($period) {
            case 'quarterly':
                $startDate = $currentDate->copy()->firstOfQuarter();
                $endDate = $currentDate->copy()->lastOfQuarter();
                break;
            case 'yearly':
                $startDate = $currentDate->copy()->startOfYear();
                $endDate = $currentDate->copy()->endOfYear();
                break;
            default: // monthly
                $startDate = $currentDate->copy()->startOfMonth();
                $endDate = $currentDate->copy()->endOfMonth();
        }

        // Get budgets with spending data
        $budgets = $user->budgets()
            ->where('period', $period)
            ->where('is_active', true)
            ->with('category')
            ->take($limit)
            ->get();

        $comparisonData = $budgets->map(function ($budget) use ($startDate, $endDate) {
            $spent = $this->budgetService->calculateCurrentSpent($budget, $startDate, $endDate);

            return [
                'category' => $budget->category ? $budget->category->name : $budget->name,
                'category_icon' => $budget->category?->icon,
                'category_color' => $budget->category?->color,
                'budget' => $budget->amount,
                'spent' => $spent,
                'remaining' => $budget->amount - $spent,
                'percentage' => $budget->amount > 0 ? round(($spent / $budget->amount) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'type' => $period,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'comparison' => $comparisonData,
            ],
        ]);
    }

    /**
     * Get category breakdown for budgets
     *
     * @OA\Get(
     *     path="/api/budgets/category-breakdown",
     *     operationId="getBudgetCategoryBreakdown",
     *     tags={"Budgets"},
     *     summary="Get budget breakdown by category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Category breakdown data")
     * )
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:monthly,quarterly,yearly'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'monthly');
        $currentDate = Carbon::now();

        // Get date range
        switch ($period) {
            case 'quarterly':
                $startDate = $currentDate->copy()->firstOfQuarter();
                $endDate = $currentDate->copy()->lastOfQuarter();
                break;
            case 'yearly':
                $startDate = $currentDate->copy()->startOfYear();
                $endDate = $currentDate->copy()->endOfYear();
                break;
            default:
                $startDate = $currentDate->copy()->startOfMonth();
                $endDate = $currentDate->copy()->endOfMonth();
        }

        // Get budgets with category and transaction data
        $budgets = $user->budgets()
            ->where('period', $period)
            ->where('is_active', true)
            ->with(['category'])
            ->get();

        $breakdown = $budgets->map(function ($budget) use ($user, $startDate, $endDate) {
            // Count transactions for this category
            $transactionCount = $user->transactions()
                ->where('category_id', $budget->category_id)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'expense')
                ->count();

            $spent = $this->budgetService->calculateCurrentSpent($budget, $startDate, $endDate);
            $remaining = $budget->amount - $spent;

            return [
                'id' => $budget->id,
                'category_id' => $budget->category_id,
                'name' => $budget->category ? $budget->category->name : $budget->name,
                'icon' => $budget->category?->icon ?? 'category',
                'color' => $budget->category?->color ?? '#2196F3',
                'budget_amount' => $budget->amount,
                'spent_amount' => $spent,
                'remaining_amount' => $remaining,
                'percentage' => $budget->amount > 0 ? round(($spent / $budget->amount) * 100, 1) : 0,
                'transaction_count' => $transactionCount,
                'status' => $spent > $budget->amount ? 'over_budget' : ($spent >= $budget->amount * 0.9 ? 'near_limit' : 'on_track'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * Get budget analysis
     */
    public function analysis(Request $request, Budget $budget): JsonResponse
    {
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
            'reset_spent' => ['nullable', 'boolean'],
        ]);

        $resetBudget = $this->budgetService->resetBudget(
            $budget,
            $request->start_date,
            $request->end_date,
            $request->boolean('carry_over_unused', false),
            $request->boolean('reset_spent', true)
        );

        return response()->json([
            'success' => true,
            'message' => 'Budget reset successfully',
            'data' => new BudgetResource($resetBudget->load('category')),
        ]);
    }

    /**
     * Export budgets data
     *
     * @OA\Get(
     *     path="/api/budgets/export",
     *     operationId="exportBudgets",
     *     tags={"Budgets"},
     *     summary="Export budgets to CSV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="CSV file download")
     * )
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $budgets = $user->budgets()->with('category')->get();

        $csvContent = "Category,Period,Budget Amount,Spent,Remaining,Percentage Used,Start Date,End Date,Status\n";

        foreach ($budgets as $budget) {
            $spent = $budget->spent ?? 0;
            $remaining = $budget->amount - $spent;
            $percentage = $budget->amount > 0 ? round(($spent / $budget->amount) * 100, 1) : 0;
            $status = $budget->is_active ? 'Active' : 'Inactive';

            $csvContent .= sprintf(
                "%s,%s,%.2f,%.2f,%.2f,%.1f%%,%s,%s,%s\n",
                $budget->category ? $budget->category->name : $budget->name,
                ucfirst($budget->period),
                $budget->amount,
                $spent,
                $remaining,
                $percentage,
                $budget->start_date,
                $budget->end_date,
                $status
            );
        }

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="budgets_export_' . now()->format('Y-m-d') . '.csv"');
    }
}
