<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\Debt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AnalyticsService
{
    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(User $user, string $period, Carbon $date): array
    {
        $dateRange = $this->getDateRange($period, $date);

        $transactions = $user->transactions()
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expenses = $transactions->where('type', 'expense')->sum('amount');
        $savings = $income - $expenses;
        $savingsRate = $income > 0 ? ($savings / $income) * 100 : 0;

        // Get account balances
        $accounts = $user->accounts()->where('is_active', true)->get();
        $totalBalance = $accounts->sum('balance');
        $netWorth = $accounts->where('include_in_net_worth', true)->sum('balance');

        // Get active debts
        $activeDebts = $user->debts()->where('status', 'active')->get();
        $totalDebt = $activeDebts->sum('remaining_amount');
        $netWorth -= $totalDebt;

        // Get budget status
        $currentBudgets = $user->budgets()
            ->where('is_active', true)
            ->whereBetween('start_date', [$dateRange['start'], $dateRange['end']])
            ->get();

        $budgetUtilization = $this->calculateBudgetUtilization($currentBudgets, $transactions);

        // Get goal progress
        $activeGoals = $user->financialGoals()->where('status', 'active')->get();
        $goalProgress = $this->calculateGoalProgress($activeGoals);

        // Recent transactions
        $recentTransactions = $user->transactions()
            ->with(['category', 'account'])
            ->latest('date')
            ->limit(10)
            ->get();

        return [
            'period' => $period,
            'date_range' => $dateRange,
            'financial_summary' => [
                'income' => $income,
                'expenses' => $expenses,
                'savings' => $savings,
                'savings_rate' => round($savingsRate, 2),
                'total_balance' => $totalBalance,
                'net_worth' => $netWorth,
                'total_debt' => $totalDebt,
            ],
            'accounts_summary' => [
                'total_accounts' => $accounts->count(),
                'by_type' => $accounts->groupBy('type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'balance' => $group->sum('balance'),
                    ];
                }),
            ],
            'budget_status' => $budgetUtilization,
            'goals_progress' => $goalProgress,
            'recent_transactions' => $recentTransactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date,
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'color' => $transaction->category->color,
                        'icon' => $transaction->category->icon,
                    ] : null,
                    'account' => [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                    ],
                ];
            }),
        ];
    }

    /**
     * Get income vs expenses data
     */
    public function getIncomeVsExpenses(
        User $user,
        string $period,
        ?string $startDate,
        ?string $endDate,
        string $groupBy,
        array $accountIds
    ): array {
        if ($startDate && $endDate) {
            $dateRange = [
                'start' => Carbon::parse($startDate),
                'end' => Carbon::parse($endDate),
            ];
        } else {
            $dateRange = $this->getDateRange($period, Carbon::now());
        }

        $query = $user->transactions()
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->whereIn('type', ['income', 'expense']);

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $transactions = $query->get();

        // Group transactions by period
        $grouped = $this->groupTransactionsByPeriod($transactions, $groupBy);

        $data = [];
        foreach ($grouped as $period => $periodTransactions) {
            $income = $periodTransactions->where('type', 'income')->sum('amount');
            $expenses = $periodTransactions->where('type', 'expense')->sum('amount');

            $data[] = [
                'period' => $period,
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'savings_rate' => $income > 0 ? round((($income - $expenses) / $income) * 100, 2) : 0,
            ];
        }

        return [
            'date_range' => $dateRange,
            'group_by' => $groupBy,
            'data' => $data,
            'summary' => [
                'total_income' => $transactions->where('type', 'income')->sum('amount'),
                'total_expenses' => $transactions->where('type', 'expense')->sum('amount'),
                'net_income' => $transactions->where('type', 'income')->sum('amount') -
                              $transactions->where('type', 'expense')->sum('amount'),
                'average_monthly_income' => $this->calculateMonthlyAverage($transactions->where('type', 'income'), $dateRange),
                'average_monthly_expenses' => $this->calculateMonthlyAverage($transactions->where('type', 'expense'), $dateRange),
            ],
        ];
    }

    /**
     * Get spending trends
     */
    public function getSpendingTrends(
        User $user,
        string $period,
        ?string $startDate,
        ?string $endDate,
        array $categoryIds,
        array $accountIds
    ): array {
        $dateRange = $this->determineDateRange($period, $startDate, $endDate);

        $query = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']]);

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $transactions = $query->with('category')->get();

        // Group by month and category
        $trends = [];
        $monthlyData = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->date)->format('Y-m');
        });

        foreach ($monthlyData as $month => $monthTransactions) {
            $categorySpending = $monthTransactions->groupBy('category_id')->map(function ($catTransactions) {
                $category = $catTransactions->first()->category;
                return [
                    'category_id' => $category ? $category->id : null,
                    'category_name' => $category ? $category->name : 'Uncategorized',
                    'category_color' => $category ? $category->color : '#999999',
                    'amount' => $catTransactions->sum('amount'),
                    'transaction_count' => $catTransactions->count(),
                ];
            })->values();

            $trends[] = [
                'month' => $month,
                'total_spending' => $monthTransactions->sum('amount'),
                'transaction_count' => $monthTransactions->count(),
                'average_transaction' => $monthTransactions->avg('amount'),
                'categories' => $categorySpending,
            ];
        }

        // Calculate trend analysis
        $trendAnalysis = $this->analyzeTrends($trends);

        return [
            'date_range' => $dateRange,
            'trends' => $trends,
            'analysis' => $trendAnalysis,
            'top_categories' => $this->getTopCategories($transactions, 5),
        ];
    }

    /**
     * Get category breakdown
     */
    public function getCategoryBreakdown(
        User $user,
        string $period,
        ?string $startDate,
        ?string $endDate,
        string $type,
        array $accountIds,
        int $limit
    ): array {
        $dateRange = $this->determineDateRange($period, $startDate, $endDate);

        $query = $user->transactions()
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->with('category');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $transactions = $query->get();

        $breakdown = $transactions->groupBy('category_id')->map(function ($catTransactions) {
            $category = $catTransactions->first()->category;
            return [
                'category_id' => $category ? $category->id : null,
                'category_name' => $category ? $category->name : 'Uncategorized',
                'category_color' => $category ? $category->color : '#999999',
                'category_icon' => $category ? $category->icon : 'help_outline',
                'total_amount' => $catTransactions->sum('amount'),
                'transaction_count' => $catTransactions->count(),
                'average_transaction' => round($catTransactions->avg('amount'), 2),
                'percentage' => 0, // Will be calculated below
            ];
        })->sortByDesc('total_amount')->take($limit);

        $totalAmount = $breakdown->sum('total_amount');

        // Calculate percentages
        $breakdown = $breakdown->map(function ($item) use ($totalAmount) {
            $item['percentage'] = $totalAmount > 0 ? round(($item['total_amount'] / $totalAmount) * 100, 2) : 0;
            return $item;
        });

        return [
            'date_range' => $dateRange,
            'type' => $type,
            'breakdown' => $breakdown->values(),
            'summary' => [
                'total_amount' => $totalAmount,
                'total_transactions' => $transactions->count(),
                'categories_count' => $breakdown->count(),
            ],
        ];
    }

    /**
     * Get monthly summary
     */
    public function getMonthlySummary(User $user, int $year, int $month, array $accountIds): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $transactions = $query->with(['category', 'account'])->get();

        $income = $transactions->where('type', 'income');
        $expenses = $transactions->where('type', 'expense');

        // Daily breakdown
        $dailyBreakdown = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dayTransactions = $transactions->filter(function ($t) use ($date) {
                return Carbon::parse($t->date)->isSameDay($date);
            });

            $dailyBreakdown[] = [
                'date' => $date->format('Y-m-d'),
                'income' => $dayTransactions->where('type', 'income')->sum('amount'),
                'expenses' => $dayTransactions->where('type', 'expense')->sum('amount'),
                'balance' => $dayTransactions->where('type', 'income')->sum('amount') -
                           $dayTransactions->where('type', 'expense')->sum('amount'),
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'summary' => [
                'total_income' => $income->sum('amount'),
                'total_expenses' => $expenses->sum('amount'),
                'net_income' => $income->sum('amount') - $expenses->sum('amount'),
                'savings_rate' => $income->sum('amount') > 0 ?
                    round((($income->sum('amount') - $expenses->sum('amount')) / $income->sum('amount')) * 100, 2) : 0,
                'transaction_count' => $transactions->count(),
            ],
            'income_sources' => $this->getIncomeSourcesBreakdown($income),
            'expense_categories' => $this->getExpenseCategoriesBreakdown($expenses),
            'daily_breakdown' => $dailyBreakdown,
            'top_expenses' => $expenses->sortByDesc('amount')->take(10)->values()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'date' => $t->date,
                    'description' => $t->description,
                    'amount' => $t->amount,
                    'category' => $t->category ? $t->category->name : 'Uncategorized',
                ];
            }),
        ];
    }

    /**
     * Get yearly summary
     */
    public function getYearlySummary(User $user, int $year, array $accountIds, bool $comparePrevious): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        $query = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $transactions = $query->get();

        // Monthly breakdown
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $monthTransactions = $transactions->filter(function ($t) use ($monthStart, $monthEnd) {
                $date = Carbon::parse($t->date);
                return $date->between($monthStart, $monthEnd);
            });

            $monthlyData[] = [
                'month' => $month,
                'month_name' => $monthStart->format('F'),
                'income' => $monthTransactions->where('type', 'income')->sum('amount'),
                'expenses' => $monthTransactions->where('type', 'expense')->sum('amount'),
                'net' => $monthTransactions->where('type', 'income')->sum('amount') -
                        $monthTransactions->where('type', 'expense')->sum('amount'),
            ];
        }

        $summary = [
            'year' => $year,
            'total_income' => $transactions->where('type', 'income')->sum('amount'),
            'total_expenses' => $transactions->where('type', 'expense')->sum('amount'),
            'net_income' => $transactions->where('type', 'income')->sum('amount') -
                          $transactions->where('type', 'expense')->sum('amount'),
            'average_monthly_income' => $transactions->where('type', 'income')->sum('amount') / 12,
            'average_monthly_expenses' => $transactions->where('type', 'expense')->sum('amount') / 12,
            'monthly_data' => $monthlyData,
        ];

        if ($comparePrevious) {
            $previousYear = $year - 1;
            $prevStartDate = Carbon::create($previousYear, 1, 1)->startOfYear();
            $prevEndDate = $prevStartDate->copy()->endOfYear();

            $prevQuery = $user->transactions()
                ->whereBetween('date', [$prevStartDate, $prevEndDate]);

            if (!empty($accountIds)) {
                $prevQuery->whereIn('account_id', $accountIds);
            }

            $prevTransactions = $prevQuery->get();

            $summary['comparison'] = [
                'previous_year' => $previousYear,
                'income_change' => $this->calculatePercentageChange(
                    $prevTransactions->where('type', 'income')->sum('amount'),
                    $summary['total_income']
                ),
                'expense_change' => $this->calculatePercentageChange(
                    $prevTransactions->where('type', 'expense')->sum('amount'),
                    $summary['total_expenses']
                ),
                'net_change' => $this->calculatePercentageChange(
                    $prevTransactions->where('type', 'income')->sum('amount') -
                    $prevTransactions->where('type', 'expense')->sum('amount'),
                    $summary['net_income']
                ),
            ];
        }

        return $summary;
    }

    /**
     * Get net worth tracking
     */
    public function getNetWorthTracking(
        User $user,
        string $period,
        ?string $startDate,
        ?string $endDate,
        bool $includeDebts,
        bool $includeGoals
    ): array {
        $dateRange = $this->determineDateRange($period, $startDate, $endDate);

        // Get account balance history
        $accounts = $user->accounts()
            ->where('include_in_net_worth', true)
            ->where('is_active', true)
            ->get();

        $netWorthHistory = [];
        $monthlyPeriod = CarbonPeriod::create($dateRange['start'], '1 month', $dateRange['end']);

        foreach ($monthlyPeriod as $date) {
            $monthEnd = $date->copy()->endOfMonth();

            // Calculate total assets
            $totalAssets = $accounts->sum(function ($account) use ($monthEnd) {
                // This would ideally fetch historical balance, but for now using current
                return $account->balance;
            });

            $totalLiabilities = 0;
            if ($includeDebts) {
                $debts = $user->debts()
                    ->where('status', 'active')
                    ->where('created_at', '<=', $monthEnd)
                    ->get();
                $totalLiabilities = $debts->sum('remaining_amount');
            }

            $netWorth = $totalAssets - $totalLiabilities;

            $netWorthHistory[] = [
                'date' => $date->format('Y-m'),
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'net_worth' => $netWorth,
            ];
        }

        $currentNetWorth = end($netWorthHistory)['net_worth'] ?? 0;
        $previousNetWorth = $netWorthHistory[count($netWorthHistory) - 2]['net_worth'] ?? 0;
        $netWorthChange = $currentNetWorth - $previousNetWorth;
        $netWorthChangePercent = $previousNetWorth != 0 ?
            (($netWorthChange / $previousNetWorth) * 100) : 0;

        return [
            'date_range' => $dateRange,
            'current_net_worth' => $currentNetWorth,
            'net_worth_change' => $netWorthChange,
            'net_worth_change_percent' => round($netWorthChangePercent, 2),
            'history' => $netWorthHistory,
            'breakdown' => [
                'assets' => $accounts->groupBy('type')->map(function ($group) {
                    return [
                        'type' => $group->first()->type,
                        'count' => $group->count(),
                        'total' => $group->sum('balance'),
                    ];
                }),
                'liabilities' => $includeDebts ? $this->getDebtBreakdown($user) : [],
            ],
        ];
    }

    /**
     * Get cash flow analysis
     */
    public function getCashFlowAnalysis(
        User $user,
        string $period,
        ?string $startDate,
        ?string $endDate,
        array $accountIds,
        string $groupBy,
        bool $includeTransfers
    ): array {
        $dateRange = $this->determineDateRange($period, $startDate, $endDate);

        $query = $user->transactions()
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']]);

        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        if (!$includeTransfers) {
            $query->where('type', '!=', 'transfer');
        }

        $transactions = $query->get();

        $cashFlowData = $this->groupTransactionsByPeriod($transactions, $groupBy)
            ->map(function ($periodTransactions, $period) {
                $inflow = $periodTransactions->whereIn('type', ['income', 'transfer_in'])->sum('amount');
                $outflow = $periodTransactions->whereIn('type', ['expense', 'transfer_out'])->sum('amount');

                return [
                    'period' => $period,
                    'inflow' => $inflow,
                    'outflow' => $outflow,
                    'net_flow' => $inflow - $outflow,
                    'running_balance' => 0, // Will be calculated cumulatively
                ];
            });

        // Calculate running balance
        $runningBalance = 0;
        $cashFlowData = $cashFlowData->map(function ($item) use (&$runningBalance) {
            $runningBalance += $item['net_flow'];
            $item['running_balance'] = $runningBalance;
            return $item;
        });

        return [
            'date_range' => $dateRange,
            'group_by' => $groupBy,
            'data' => $cashFlowData->values(),
            'summary' => [
                'total_inflow' => $transactions->whereIn('type', ['income', 'transfer_in'])->sum('amount'),
                'total_outflow' => $transactions->whereIn('type', ['expense', 'transfer_out'])->sum('amount'),
                'net_cash_flow' => $transactions->whereIn('type', ['income', 'transfer_in'])->sum('amount') -
                                  $transactions->whereIn('type', ['expense', 'transfer_out'])->sum('amount'),
                'average_daily_flow' => $this->calculateAverageDailyFlow($transactions, $dateRange),
            ],
        ];
    }

    /**
     * Get budget performance
     */
    public function getBudgetPerformance(
        User $user,
        string $period,
        array $budgetIds,
        array $categoryIds
    ): array {
        $query = $user->budgets()->where('is_active', true);

        if (!empty($budgetIds)) {
            $query->whereIn('id', $budgetIds);
        }

        if ($period === 'current') {
            $query->where('start_date', '<=', Carbon::now())
                  ->where('end_date', '>=', Carbon::now());
        }

        $budgets = $query->with('category')->get();

        $performance = $budgets->map(function ($budget) use ($categoryIds) {
            if (!empty($categoryIds) && !in_array($budget->category_id, $categoryIds)) {
                return null;
            }

            $spent = $budget->getSpentAmount();
            $remaining = $budget->amount - $spent;
            $percentageUsed = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;

            return [
                'budget_id' => $budget->id,
                'budget_name' => $budget->name,
                'category' => $budget->category ? [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'color' => $budget->category->color,
                ] : null,
                'period' => [
                    'start' => $budget->start_date,
                    'end' => $budget->end_date,
                ],
                'budget_amount' => $budget->amount,
                'spent_amount' => $spent,
                'remaining_amount' => $remaining,
                'percentage_used' => round($percentageUsed, 2),
                'status' => $this->getBudgetStatus($percentageUsed),
                'days_remaining' => Carbon::now()->diffInDays($budget->end_date),
            ];
        })->filter()->values();

        return [
            'period' => $period,
            'performance' => $performance,
            'summary' => [
                'total_budgets' => $performance->count(),
                'on_track' => $performance->where('status', 'on_track')->count(),
                'warning' => $performance->where('status', 'warning')->count(),
                'exceeded' => $performance->where('status', 'exceeded')->count(),
                'total_budget' => $performance->sum('budget_amount'),
                'total_spent' => $performance->sum('spent_amount'),
                'overall_percentage' => $performance->sum('budget_amount') > 0 ?
                    round(($performance->sum('spent_amount') / $performance->sum('budget_amount')) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get goal progress
     */
    public function getGoalProgress(
        User $user,
        string $status,
        array $goalIds,
        bool $includeHistory
    ): array {
        $query = $user->financialGoals();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($goalIds)) {
            $query->whereIn('id', $goalIds);
        }

        $goals = $query->get();

        $progress = $goals->map(function ($goal) use ($includeHistory) {
            $progressPercentage = $goal->target_amount > 0 ?
                ($goal->current_amount / $goal->target_amount) * 100 : 0;

            $data = [
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'description' => $goal->description,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
                'remaining_amount' => $goal->target_amount - $goal->current_amount,
                'progress_percentage' => round($progressPercentage, 2),
                'target_date' => $goal->target_date,
                'days_remaining' => $goal->target_date ?
                    Carbon::now()->diffInDays($goal->target_date, false) : null,
                'status' => $goal->status,
                'monthly_contribution_needed' => $this->calculateMonthlyContributionNeeded($goal),
            ];

            if ($includeHistory) {
                $data['contribution_history'] = $goal->contributions()
                    ->orderBy('date', 'desc')
                    ->limit(12)
                    ->get()
                    ->map(function ($contribution) {
                        return [
                            'date' => $contribution->date,
                            'amount' => $contribution->amount,
                            'note' => $contribution->note,
                        ];
                    });
            }

            return $data;
        });

        return [
            'status_filter' => $status,
            'goals' => $progress,
            'summary' => [
                'total_goals' => $progress->count(),
                'total_target' => $progress->sum('target_amount'),
                'total_saved' => $progress->sum('current_amount'),
                'overall_progress' => $progress->sum('target_amount') > 0 ?
                    round(($progress->sum('current_amount') / $progress->sum('target_amount')) * 100, 2) : 0,
                'goals_on_track' => $progress->where('progress_percentage', '>=', 50)->count(),
                'goals_behind' => $progress->where('progress_percentage', '<', 50)->count(),
            ],
        ];
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport(User $user, array $parameters): array
    {
        // This would be a more complex implementation based on the report type
        // For now, returning a basic structure

        $reportType = $parameters['report_type'];
        $metrics = $parameters['metrics'];
        $filters = $parameters['filters'] ?? [];
        $groupBy = $parameters['group_by'] ?? 'month';
        $startDate = $parameters['start_date'] ?? Carbon::now()->subMonths(6)->format('Y-m-d');
        $endDate = $parameters['end_date'] ?? Carbon::now()->format('Y-m-d');

        $data = [
            'report_type' => $reportType,
            'generated_at' => Carbon::now()->toIso8601String(),
            'parameters' => $parameters,
            'data' => [],
        ];

        // Implement different report types
        switch ($reportType) {
            case 'transactions':
                $data['data'] = $this->generateTransactionReport($user, $startDate, $endDate, $filters);
                break;
            case 'summary':
                $data['data'] = $this->generateSummaryReport($user, $startDate, $endDate, $metrics);
                break;
            case 'comparison':
                $data['data'] = $this->generateComparisonReport($user, $startDate, $endDate, $groupBy);
                break;
            case 'forecast':
                $data['data'] = $this->generateForecastReport($user, $metrics);
                break;
        }

        return $data;
    }

    /**
     * Get expense predictions
     */
    public function getExpensePredictions(
        User $user,
        int $monthsAhead,
        array $categoryIds,
        float $confidenceLevel
    ): array {
        // Get historical data for prediction
        $historicalMonths = 12;
        $startDate = Carbon::now()->subMonths($historicalMonths)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $query = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        $transactions = $query->get();

        // Group by month and calculate averages
        $monthlyData = $transactions->groupBy(function ($t) {
            return Carbon::parse($t->date)->format('Y-m');
        })->map(function ($monthTransactions) {
            return $monthTransactions->sum('amount');
        });

        $average = $monthlyData->avg();
        $standardDeviation = $this->calculateStandardDeviation($monthlyData->values()->toArray());

        // Generate predictions
        $predictions = [];
        for ($i = 1; $i <= $monthsAhead; $i++) {
            $predictedMonth = Carbon::now()->addMonths($i);

            // Simple linear prediction with confidence interval
            $predicted = $average;
            $confidenceInterval = $standardDeviation * 1.96 * $confidenceLevel;

            $predictions[] = [
                'month' => $predictedMonth->format('Y-m'),
                'predicted_amount' => round($predicted, 2),
                'confidence_interval' => [
                    'lower' => round(max(0, $predicted - $confidenceInterval), 2),
                    'upper' => round($predicted + $confidenceInterval, 2),
                ],
                'confidence_level' => $confidenceLevel * 100,
            ];
        }

        return [
            'predictions' => $predictions,
            'based_on' => [
                'historical_months' => $historicalMonths,
                'average_monthly_expense' => round($average, 2),
                'standard_deviation' => round($standardDeviation, 2),
            ],
        ];
    }

    /**
     * Calculate health score
     */
    public function calculateHealthScore(User $user): array
    {
        $scores = [];

        // 1. Savings Rate Score (30 points)
        $lastMonth = Carbon::now()->subMonth();
        $income = $user->getTotalIncome($lastMonth->startOfMonth(), $lastMonth->endOfMonth());
        $expenses = $user->getTotalExpenses($lastMonth->startOfMonth(), $lastMonth->endOfMonth());
        $savingsRate = $income > 0 ? (($income - $expenses) / $income) * 100 : 0;

        $scores['savings_rate'] = [
            'value' => $savingsRate,
            'score' => min(30, $savingsRate * 1.5), // 20% savings rate = 30 points
            'max_score' => 30,
        ];

        // 2. Budget Adherence Score (20 points)
        $budgets = $user->budgets()->where('is_active', true)->get();
        $budgetAdherence = 100;
        if ($budgets->count() > 0) {
            $totalBudget = $budgets->sum('amount');
            $totalSpent = $budgets->sum(function ($budget) {
                return $budget->getSpentAmount();
            });
            $budgetAdherence = $totalBudget > 0 ? (1 - ($totalSpent / $totalBudget)) * 100 : 100;
        }

        $scores['budget_adherence'] = [
            'value' => $budgetAdherence,
            'score' => min(20, $budgetAdherence * 0.2),
            'max_score' => 20,
        ];

        // 3. Debt-to-Income Ratio Score (20 points)
        $totalDebt = $user->debts()->where('status', 'active')->sum('remaining_amount');
        $annualIncome = $income * 12;
        $debtToIncome = $annualIncome > 0 ? ($totalDebt / $annualIncome) * 100 : 0;

        $scores['debt_to_income'] = [
            'value' => $debtToIncome,
            'score' => max(0, 20 - ($debtToIncome * 0.4)), // 0% debt = 20 points, 50% debt = 0 points
            'max_score' => 20,
        ];

        // 4. Emergency Fund Score (15 points)
        $netWorth = $user->net_worth;
        $monthlyExpenses = $expenses;
        $emergencyMonths = $monthlyExpenses > 0 ? $netWorth / $monthlyExpenses : 0;

        $scores['emergency_fund'] = [
            'value' => $emergencyMonths,
            'score' => min(15, $emergencyMonths * 2.5), // 6 months = 15 points
            'max_score' => 15,
        ];

        // 5. Goal Progress Score (15 points)
        $activeGoals = $user->financialGoals()->where('status', 'active')->get();
        $goalProgress = 0;
        if ($activeGoals->count() > 0) {
            $goalProgress = $activeGoals->avg(function ($goal) {
                return $goal->target_amount > 0 ?
                    ($goal->current_amount / $goal->target_amount) * 100 : 0;
            });
        }

        $scores['goal_progress'] = [
            'value' => $goalProgress,
            'score' => min(15, $goalProgress * 0.15),
            'max_score' => 15,
        ];

        $totalScore = collect($scores)->sum('score');
        $maxScore = collect($scores)->sum('max_score');

        return [
            'overall_score' => round($totalScore),
            'max_score' => $maxScore,
            'grade' => $this->getGrade($totalScore),
            'components' => $scores,
            'recommendations' => $this->getHealthRecommendations($scores),
        ];
    }

    /**
     * Get insights
     */
    public function getInsights(User $user, array $focusAreas, int $limit): array
    {
        $insights = [];

        foreach ($focusAreas as $area) {
            switch ($area) {
                case 'spending':
                    $insights = array_merge($insights, $this->getSpendingInsights($user));
                    break;
                case 'saving':
                    $insights = array_merge($insights, $this->getSavingInsights($user));
                    break;
                case 'budgeting':
                    $insights = array_merge($insights, $this->getBudgetingInsights($user));
                    break;
                case 'debt':
                    $insights = array_merge($insights, $this->getDebtInsights($user));
                    break;
                case 'investments':
                    $insights = array_merge($insights, $this->getInvestmentInsights($user));
                    break;
            }
        }

        // Sort by priority and limit
        usort($insights, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return array_slice($insights, 0, $limit);
    }

    // Helper methods

    private function getDateRange(string $period, Carbon $date): array
    {
        switch ($period) {
            case 'day':
                return [
                    'start' => $date->copy()->startOfDay(),
                    'end' => $date->copy()->endOfDay(),
                ];
            case 'week':
                return [
                    'start' => $date->copy()->startOfWeek(),
                    'end' => $date->copy()->endOfWeek(),
                ];
            case 'month':
                return [
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth(),
                ];
            case 'quarter':
                return [
                    'start' => $date->copy()->startOfQuarter(),
                    'end' => $date->copy()->endOfQuarter(),
                ];
            case 'year':
                return [
                    'start' => $date->copy()->startOfYear(),
                    'end' => $date->copy()->endOfYear(),
                ];
            default:
                return [
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth(),
                ];
        }
    }

    private function determineDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            return [
                'start' => Carbon::parse($startDate),
                'end' => Carbon::parse($endDate),
            ];
        }

        return $this->getDateRange($period, Carbon::now());
    }

    private function groupTransactionsByPeriod(Collection $transactions, string $groupBy): Collection
    {
        return $transactions->groupBy(function ($transaction) use ($groupBy) {
            $date = Carbon::parse($transaction->date);

            return match($groupBy) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
                'month' => $date->format('Y-m'),
                'quarter' => $date->format('Y-Q'),
                'year' => $date->format('Y'),
                default => $date->format('Y-m'),
            };
        });
    }

    private function calculateBudgetUtilization($budgets, $transactions): array
    {
        // Implementation for budget utilization calculation
        return [
            'total_budget' => $budgets->sum('amount'),
            'total_spent' => 0, // Calculate from transactions
            'utilization_percentage' => 0,
        ];
    }

    private function calculateGoalProgress($goals): array
    {
        // Implementation for goal progress calculation
        return [
            'total_goals' => $goals->count(),
            'active_goals' => $goals->where('status', 'active')->count(),
            'average_progress' => 0, // Calculate average progress
        ];
    }

    private function calculateMonthlyAverage($transactions, array $dateRange): float
    {
        $months = Carbon::parse($dateRange['start'])->diffInMonths(Carbon::parse($dateRange['end'])) ?: 1;
        return $transactions->sum('amount') / $months;
    }

    private function analyzeTrends(array $trends): array
    {
        if (count($trends) < 2) {
            return [
                'trend_direction' => 'insufficient_data',
                'average_change' => 0,
                'volatility' => 0,
            ];
        }

        $changes = [];
        for ($i = 1; $i < count($trends); $i++) {
            $previousAmount = $trends[$i - 1]['total_spending'];
            $currentAmount = $trends[$i]['total_spending'];
            $change = $previousAmount > 0 ?
                (($currentAmount - $previousAmount) / $previousAmount) * 100 : 0;
            $changes[] = $change;
        }

        $averageChange = array_sum($changes) / count($changes);
        $volatility = $this->calculateStandardDeviation($changes);

        return [
            'trend_direction' => $averageChange > 0 ? 'increasing' : 'decreasing',
            'average_change' => round($averageChange, 2),
            'volatility' => round($volatility, 2),
            'months_analyzed' => count($trends),
        ];
    }

    private function getTopCategories($transactions, int $limit): array
    {
        return $transactions->groupBy('category_id')
            ->map(function ($catTransactions) {
                $category = $catTransactions->first()->category;
                return [
                    'category_id' => $category ? $category->id : null,
                    'category_name' => $category ? $category->name : 'Uncategorized',
                    'total_amount' => $catTransactions->sum('amount'),
                    'transaction_count' => $catTransactions->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->take($limit)
            ->values()
            ->toArray();
    }

    private function getIncomeSourcesBreakdown($incomeTransactions): array
    {
        return $incomeTransactions->groupBy('category_id')
            ->map(function ($transactions) {
                $category = $transactions->first()->category;
                return [
                    'source' => $category ? $category->name : 'Other',
                    'amount' => $transactions->sum('amount'),
                    'count' => $transactions->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();
    }

    private function getExpenseCategoriesBreakdown($expenseTransactions): array
    {
        return $expenseTransactions->groupBy('category_id')
            ->map(function ($transactions) {
                $category = $transactions->first()->category;
                return [
                    'category' => $category ? $category->name : 'Uncategorized',
                    'amount' => $transactions->sum('amount'),
                    'count' => $transactions->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->toArray();
    }

    private function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }
        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    private function getDebtBreakdown(User $user): array
    {
        return $user->debts()
            ->where('status', 'active')
            ->get()
            ->groupBy('type')
            ->map(function ($debts) {
                return [
                    'count' => $debts->count(),
                    'total' => $debts->sum('remaining_amount'),
                    'average' => $debts->avg('remaining_amount'),
                ];
            })
            ->toArray();
    }

    private function calculateAverageDailyFlow($transactions, array $dateRange): float
    {
        $days = Carbon::parse($dateRange['start'])->diffInDays(Carbon::parse($dateRange['end'])) ?: 1;
        $netFlow = $transactions->whereIn('type', ['income', 'transfer_in'])->sum('amount') -
                   $transactions->whereIn('type', ['expense', 'transfer_out'])->sum('amount');
        return round($netFlow / $days, 2);
    }

    private function getBudgetStatus(float $percentageUsed): string
    {
        if ($percentageUsed >= 100) {
            return 'exceeded';
        } elseif ($percentageUsed >= 80) {
            return 'warning';
        } elseif ($percentageUsed >= 50) {
            return 'normal';
        } else {
            return 'on_track';
        }
    }

    private function calculateMonthlyContributionNeeded($goal): ?float
    {
        if (!$goal->target_date || $goal->status !== 'active') {
            return null;
        }

        $remaining = $goal->target_amount - $goal->current_amount;
        $monthsRemaining = Carbon::now()->diffInMonths($goal->target_date);

        if ($monthsRemaining <= 0) {
            return null;
        }

        return round($remaining / $monthsRemaining, 2);
    }

    private function generateTransactionReport(User $user, $startDate, $endDate, array $filters): array
    {
        $query = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['category', 'account']);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }
        if (isset($filters['account_ids'])) {
            $query->whereIn('account_id', $filters['account_ids']);
        }
        if (isset($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        $transactions = $query->get();

        return [
            'transactions' => $transactions->map(function ($t) {
                return [
                    'date' => $t->date,
                    'description' => $t->description,
                    'amount' => $t->amount,
                    'type' => $t->type,
                    'category' => $t->category ? $t->category->name : null,
                    'account' => $t->account->name,
                ];
            }),
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
                'average_amount' => $transactions->avg('amount'),
            ],
        ];
    }

    private function generateSummaryReport(User $user, $startDate, $endDate, array $metrics): array
    {
        $report = [];
        $transactions = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        foreach ($metrics as $metric) {
            switch ($metric) {
                case 'income':
                    $report['income'] = $transactions->where('type', 'income')->sum('amount');
                    break;
                case 'expense':
                    $report['expense'] = $transactions->where('type', 'expense')->sum('amount');
                    break;
                case 'balance':
                    $report['balance'] = $user->accounts()->sum('balance');
                    break;
                case 'savings':
                    $income = $transactions->where('type', 'income')->sum('amount');
                    $expense = $transactions->where('type', 'expense')->sum('amount');
                    $report['savings'] = $income - $expense;
                    break;
                case 'category_spending':
                    $report['category_spending'] = $this->getExpenseCategoriesBreakdown(
                        $transactions->where('type', 'expense')
                    );
                    break;
                case 'account_balance':
                    $report['account_balance'] = $user->accounts()->get()->map(function ($account) {
                        return [
                            'account' => $account->name,
                            'balance' => $account->balance,
                        ];
                    });
                    break;
            }
        }

        return $report;
    }

    private function generateComparisonReport(User $user, $startDate, $endDate, $groupBy): array
    {
        $transactions = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $grouped = $this->groupTransactionsByPeriod($transactions, $groupBy);

        return $grouped->map(function ($periodTransactions, $period) {
            return [
                'period' => $period,
                'income' => $periodTransactions->where('type', 'income')->sum('amount'),
                'expenses' => $periodTransactions->where('type', 'expense')->sum('amount'),
                'net' => $periodTransactions->where('type', 'income')->sum('amount') -
                        $periodTransactions->where('type', 'expense')->sum('amount'),
                'transaction_count' => $periodTransactions->count(),
            ];
        })->values()->toArray();
    }

    private function generateForecastReport(User $user, array $metrics): array
    {
        // Simple forecast based on historical averages
        $historicalData = $user->transactions()
            ->where('date', '>=', Carbon::now()->subMonths(6))
            ->get();

        $forecast = [];
        foreach ($metrics as $metric) {
            $monthlyAverage = $historicalData
                ->where('type', $metric === 'income' ? 'income' : 'expense')
                ->groupBy(function ($t) {
                    return Carbon::parse($t->date)->format('Y-m');
                })
                ->map(function ($month) {
                    return $month->sum('amount');
                })
                ->avg();

            $forecast[$metric] = [
                'next_month' => round($monthlyAverage, 2),
                'next_3_months' => round($monthlyAverage * 3, 2),
                'next_6_months' => round($monthlyAverage * 6, 2),
            ];
        }

        return $forecast;
    }

    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count <= 1) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $sumOfSquares = 0;

        foreach ($values as $value) {
            $sumOfSquares += pow($value - $mean, 2);
        }

        return sqrt($sumOfSquares / ($count - 1));
    }

    private function getGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D';
        return 'F';
    }

    private function getHealthRecommendations(array $scores): array
    {
        $recommendations = [];

        if ($scores['savings_rate']['score'] < 20) {
            $recommendations[] = [
                'category' => 'savings',
                'priority' => 'high',
                'message' => 'Your savings rate is below recommended levels. Aim to save at least 20% of your income.',
                'action' => 'Review your expenses and identify areas to cut back.',
            ];
        }

        if ($scores['budget_adherence']['score'] < 15) {
            $recommendations[] = [
                'category' => 'budgeting',
                'priority' => 'high',
                'message' => 'You\'re exceeding your budget limits. This could lead to financial stress.',
                'action' => 'Review and adjust your budget categories to be more realistic.',
            ];
        }

        if ($scores['debt_to_income']['score'] < 10) {
            $recommendations[] = [
                'category' => 'debt',
                'priority' => 'critical',
                'message' => 'Your debt-to-income ratio is concerning. This limits your financial flexibility.',
                'action' => 'Focus on paying down high-interest debt first.',
            ];
        }

        if ($scores['emergency_fund']['score'] < 10) {
            $recommendations[] = [
                'category' => 'emergency_fund',
                'priority' => 'high',
                'message' => 'Your emergency fund is insufficient. Aim for 3-6 months of expenses.',
                'action' => 'Set up automatic transfers to build your emergency fund.',
            ];
        }

        if ($scores['goal_progress']['score'] < 8) {
            $recommendations[] = [
                'category' => 'goals',
                'priority' => 'medium',
                'message' => 'Your financial goals need more attention to stay on track.',
                'action' => 'Review your goals and adjust contribution amounts if needed.',
            ];
        }

        return $recommendations;
    }

    private function getSpendingInsights(User $user): array
    {
        $insights = [];

        // Analyze recent spending patterns
        $recentSpending = $user->transactions()
            ->where('type', 'expense')
            ->where('date', '>=', Carbon::now()->subMonth())
            ->get();

        $previousSpending = $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [
                Carbon::now()->subMonths(2),
                Carbon::now()->subMonth()
            ])
            ->get();

        $increase = $recentSpending->sum('amount') - $previousSpending->sum('amount');
        if ($increase > $previousSpending->sum('amount') * 0.1) {
            $insights[] = [
                'type' => 'spending_increase',
                'priority' => 8,
                'title' => 'Spending Increase Detected',
                'message' => sprintf('Your spending increased by %.1f%% this month.',
                    ($increase / $previousSpending->sum('amount')) * 100),
                'action' => 'Review your recent transactions to identify unusual expenses.',
            ];
        }

        return $insights;
    }

    private function getSavingInsights(User $user): array
    {
        $insights = [];

        $income = $user->getTotalIncome(
            Carbon::now()->startOfMonth()->format('Y-m-d'),
            Carbon::now()->format('Y-m-d')
        );
        $expenses = $user->getTotalExpenses(
            Carbon::now()->startOfMonth()->format('Y-m-d'),
            Carbon::now()->format('Y-m-d')
        );

        $savingsRate = $income > 0 ? (($income - $expenses) / $income) * 100 : 0;

        if ($savingsRate < 10) {
            $insights[] = [
                'type' => 'low_savings',
                'priority' => 9,
                'title' => 'Low Savings Rate',
                'message' => sprintf('You\'re only saving %.1f%% of your income.', $savingsRate),
                'action' => 'Try to increase your savings rate to at least 20%.',
            ];
        }

        return $insights;
    }

    private function getBudgetingInsights(User $user): array
    {
        $insights = [];

        $budgets = $user->budgets()->where('is_active', true)->get();

        foreach ($budgets as $budget) {
            $spent = $budget->getSpentAmount();
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;

            if ($percentage > 90) {
                $insights[] = [
                    'type' => 'budget_warning',
                    'priority' => 7,
                    'title' => sprintf('Budget Alert: %s', $budget->name),
                    'message' => sprintf('You\'ve used %.1f%% of your %s budget.', $percentage, $budget->name),
                    'action' => 'Consider reducing spending in this category.',
                ];
            }
        }

        return $insights;
    }

    private function getDebtInsights(User $user): array
    {
        $insights = [];

        $debts = $user->debts()->where('status', 'active')->get();

        if ($debts->count() > 0) {
            $highInterestDebts = $debts->where('interest_rate', '>', 15);

            if ($highInterestDebts->count() > 0) {
                $insights[] = [
                    'type' => 'high_interest_debt',
                    'priority' => 9,
                    'title' => 'High Interest Debt',
                    'message' => sprintf('You have %d debt(s) with interest rates above 15%%.',
                        $highInterestDebts->count()),
                    'action' => 'Prioritize paying off high-interest debts first.',
                ];
            }
        }

        return $insights;
    }

    private function getInvestmentInsights(User $user): array
    {
        $insights = [];

        $investmentAccounts = $user->accounts()
            ->where('type', 'investment')
            ->where('is_active', true)
            ->get();

        if ($investmentAccounts->count() === 0) {
            $insights[] = [
                'type' => 'no_investments',
                'priority' => 6,
                'title' => 'No Investment Accounts',
                'message' => 'You don\'t have any investment accounts set up.',
                'action' => 'Consider starting to invest for long-term wealth building.',
            ];
        }

        return $insights;
    }

    /**
     * Convert data to CSV format
     */
    public function convertToCSV(array $data): string
    {
        // Implementation would depend on the structure of the data
        // This is a simplified example
        $csv = '';

        if (isset($data['data']) && is_array($data['data'])) {
            // Create header row
            if (count($data['data']) > 0) {
                $firstRow = reset($data['data']);
                if (is_array($firstRow)) {
                    $csv .= implode(',', array_keys($firstRow)) . "\n";

                    // Add data rows
                    foreach ($data['data'] as $row) {
                        $csv .= implode(',', array_values($row)) . "\n";
                    }
                }
            }
        }

        return $csv;
    }

    /**
     * Convert data to PDF format
     */
    public function convertToPDF(array $data): string
    {
        // This would typically use a PDF library like DomPDF or TCPDF
        // For now, returning a placeholder
        return 'PDF content would be generated here';
    }
}
