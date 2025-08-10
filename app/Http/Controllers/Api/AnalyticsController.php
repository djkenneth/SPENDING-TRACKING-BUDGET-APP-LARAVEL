<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dashboard summary
     * GET /api/analytics/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:day,week,month,quarter,year'],
            'date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'month');
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::now();

        $summary = $this->analyticsService->getDashboardSummary($user, $period, $date);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get income vs expenses data
     * GET /api/analytics/income-vs-expenses
     */
    public function incomeVsExpenses(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'group_by' => ['nullable', 'string', 'in:day,week,month,year'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $groupBy = $request->input('group_by', 'month');
        $accountIds = $request->input('account_ids', []);

        $data = $this->analyticsService->getIncomeVsExpenses(
            $user,
            $period,
            $startDate,
            $endDate,
            $groupBy,
            $accountIds
        );

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get spending trends
     * GET /api/analytics/spending-trends
     */
    public function spendingTrends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:3months,6months,year,2years'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ]);

        $user = $request->user();
        $period = $request->input('period', '6months');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $categoryIds = $request->input('category_ids', []);
        $accountIds = $request->input('account_ids', []);

        $trends = $this->analyticsService->getSpendingTrends(
            $user,
            $period,
            $startDate,
            $endDate,
            $categoryIds,
            $accountIds
        );

        return response()->json([
            'success' => true,
            'data' => $trends
        ]);
    }

    /**
     * Get category breakdown
     * GET /api/analytics/category-breakdown
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'in:income,expense,all'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $type = $request->input('type', 'expense');
        $accountIds = $request->input('account_ids', []);
        $limit = $request->input('limit', 10);

        $breakdown = $this->analyticsService->getCategoryBreakdown(
            $user,
            $period,
            $startDate,
            $endDate,
            $type,
            $accountIds,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $breakdown
        ]);
    }

    /**
     * Get monthly summary
     * GET /api/analytics/monthly-summary
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ]);

        $user = $request->user();
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        $accountIds = $request->input('account_ids', []);

        $summary = $this->analyticsService->getMonthlySummary(
            $user,
            $year,
            $month,
            $accountIds
        );

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get yearly summary
     * GET /api/analytics/yearly-summary
     */
    public function yearlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'compare_previous' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $year = $request->input('year', Carbon::now()->year);
        $accountIds = $request->input('account_ids', []);
        $comparePrevious = $request->input('compare_previous', false);

        $summary = $this->analyticsService->getYearlySummary(
            $user,
            $year,
            $accountIds,
            $comparePrevious
        );

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get net worth tracking
     * GET /api/analytics/net-worth
     */
    public function netWorth(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:3months,6months,year,2years,all'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'include_debts' => ['nullable', 'boolean'],
            'include_goals' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'year');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $includeDebts = $request->input('include_debts', true);
        $includeGoals = $request->input('include_goals', false);

        $netWorth = $this->analyticsService->getNetWorthTracking(
            $user,
            $period,
            $startDate,
            $endDate,
            $includeDebts,
            $includeGoals
        );

        return response()->json([
            'success' => true,
            'data' => $netWorth
        ]);
    }

    /**
     * Get cash flow analysis
     * GET /api/analytics/cash-flow
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:week,month,quarter,year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'group_by' => ['nullable', 'string', 'in:day,week,month'],
            'include_transfers' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountIds = $request->input('account_ids', []);
        $groupBy = $request->input('group_by', 'week');
        $includeTransfers = $request->input('include_transfers', false);

        $cashFlow = $this->analyticsService->getCashFlowAnalysis(
            $user,
            $period,
            $startDate,
            $endDate,
            $accountIds,
            $groupBy,
            $includeTransfers
        );

        return response()->json([
            'success' => true,
            'data' => $cashFlow
        ]);
    }

    /**
     * Get budget performance
     * GET /api/analytics/budget-performance
     */
    public function budgetPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'in:current,month,quarter,year'],
            'budget_ids' => ['nullable', 'array'],
            'budget_ids.*' => ['integer', 'exists:budgets,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $user = $request->user();
        $period = $request->input('period', 'current');
        $budgetIds = $request->input('budget_ids', []);
        $categoryIds = $request->input('category_ids', []);

        $performance = $this->analyticsService->getBudgetPerformance(
            $user,
            $period,
            $budgetIds,
            $categoryIds
        );

        return response()->json([
            'success' => true,
            'data' => $performance
        ]);
    }

    /**
     * Get goals progress summary
     * GET /api/analytics/goal-progress
     */
    public function goalProgress(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:active,completed,cancelled,all'],
            'goal_ids' => ['nullable', 'array'],
            'goal_ids.*' => ['integer', 'exists:financial_goals,id'],
            'include_history' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $status = $request->input('status', 'active');
        $goalIds = $request->input('goal_ids', []);
        $includeHistory = $request->input('include_history', false);

        $progress = $this->analyticsService->getGoalProgress(
            $user,
            $status,
            $goalIds,
            $includeHistory
        );

        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }

    /**
     * Get custom report
     * POST /api/analytics/custom-report
     */
    public function customReport(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => ['required', 'string', 'in:transactions,summary,comparison,forecast'],
            'metrics' => ['required', 'array'],
            'metrics.*' => ['string', 'in:income,expense,balance,savings,category_spending,account_balance'],
            'filters' => ['nullable', 'array'],
            'group_by' => ['nullable', 'string', 'in:day,week,month,quarter,year,category,account'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'format' => ['nullable', 'string', 'in:json,csv,pdf'],
        ]);

        $user = $request->user();
        $reportData = $this->analyticsService->generateCustomReport(
            $user,
            $request->all()
        );

        $format = $request->input('format', 'json');

        if ($format === 'json') {
            return response()->json([
                'success' => true,
                'data' => $reportData
            ]);
        }

        // Handle CSV or PDF export
        return $this->exportReport($reportData, $format);
    }

    /**
     * Get expense predictions
     * GET /api/analytics/predictions
     */
    public function predictions(Request $request): JsonResponse
    {
        $request->validate([
            'months_ahead' => ['nullable', 'integer', 'min:1', 'max:12'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'confidence_level' => ['nullable', 'numeric', 'min:0.5', 'max:0.99'],
        ]);

        $user = $request->user();
        $monthsAhead = $request->input('months_ahead', 3);
        $categoryIds = $request->input('category_ids', []);
        $confidenceLevel = $request->input('confidence_level', 0.95);

        $predictions = $this->analyticsService->getExpensePredictions(
            $user,
            $monthsAhead,
            $categoryIds,
            $confidenceLevel
        );

        return response()->json([
            'success' => true,
            'data' => $predictions
        ]);
    }

    /**
     * Get financial health score
     * GET /api/analytics/health-score
     */
    public function healthScore(Request $request): JsonResponse
    {
        $user = $request->user();

        $healthScore = $this->analyticsService->calculateHealthScore($user);

        return response()->json([
            'success' => true,
            'data' => $healthScore
        ]);
    }

    /**
     * Get insights and recommendations
     * GET /api/analytics/insights
     */
    public function insights(Request $request): JsonResponse
    {
        $request->validate([
            'focus_areas' => ['nullable', 'array'],
            'focus_areas.*' => ['string', 'in:spending,saving,budgeting,debt,investments'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $request->user();
        $focusAreas = $request->input('focus_areas', ['spending', 'saving', 'budgeting']);
        $limit = $request->input('limit', 10);

        $insights = $this->analyticsService->getInsights(
            $user,
            $focusAreas,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $insights
        ]);
    }

    /**
     * Export report in different formats
     */
    protected function exportReport($data, $format)
    {
        // Implementation for CSV/PDF export would go here
        // This is a placeholder for the export functionality

        if ($format === 'csv') {
            $csv = $this->analyticsService->convertToCSV($data);

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="report.csv"');
        }

        if ($format === 'pdf') {
            $pdf = $this->analyticsService->convertToPDF($data);

            return response($pdf, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="report.pdf"');
        }

        return response()->json([
            'success' => false,
            'message' => 'Unsupported export format'
        ], 400);
    }
}
