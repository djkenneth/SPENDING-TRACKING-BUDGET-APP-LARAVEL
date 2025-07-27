<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Create a new budget
     */
    public function createBudget(array $data): Budget
    {
        $budget = Budget::create([
            'user_id' => auth()->id(),
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'period' => $data['period'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'spent' => 0,
            'is_active' => $data['is_active'] ?? true,
            'alert_threshold' => $data['alert_threshold'] ?? 80.00,
            'alert_enabled' => $data['alert_enabled'] ?? true,
            'rollover_settings' => $data['rollover_settings'] ?? null,
        ]);

        // Calculate initial spent amount based on existing transactions
        $this->recalculateBudgetSpent($budget);

        return $budget;
    }

    /**
     * Update budget
     */
    public function updateBudget(Budget $budget, array $data): Budget
    {
        $budget->update([
            'category_id' => $data['category_id'] ?? $budget->category_id,
            'name' => $data['name'] ?? $budget->name,
            'amount' => $data['amount'] ?? $budget->amount,
            'period' => $data['period'] ?? $budget->period,
            'start_date' => $data['start_date'] ?? $budget->start_date,
            'end_date' => $data['end_date'] ?? $budget->end_date,
            'is_active' => $data['is_active'] ?? $budget->is_active,
            'alert_threshold' => $data['alert_threshold'] ?? $budget->alert_threshold,
            'alert_enabled' => $data['alert_enabled'] ?? $budget->alert_enabled,
            'rollover_settings' => $data['rollover_settings'] ?? $budget->rollover_settings,
        ]);

        // Recalculate spent if date range or category changed
        if (isset($data['start_date']) || isset($data['end_date']) || isset($data['category_id'])) {
            $this->recalculateBudgetSpent($budget);
        }

        return $budget;
    }

    /**
     * Delete budget
     */
    public function deleteBudget(Budget $budget): bool
    {
        return $budget->delete();
    }

    /**
     * Get budget statistics for user
     */
    public function getBudgetStatistics(User $user): array
    {
        $currentMonth = Carbon::now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $activeBudgets = $user->budgets()->where('is_active', true)->get();
        $currentMonthBudgets = $activeBudgets->filter(function ($budget) use ($startOfMonth, $endOfMonth) {
            return $budget->start_date <= $endOfMonth && $budget->end_date >= $startOfMonth;
        });

        $totalBudgeted = $currentMonthBudgets->sum('amount');
        $totalSpent = $currentMonthBudgets->sum(function ($budget) use ($startOfMonth, $endOfMonth) {
            return $this->calculateCurrentSpent($budget, $startOfMonth, $endOfMonth);
        });

        $overBudgetCount = $currentMonthBudgets->filter(function ($budget) use ($startOfMonth, $endOfMonth) {
            $currentSpent = $this->calculateCurrentSpent($budget, $startOfMonth, $endOfMonth);
            return $currentSpent > $budget->amount;
        })->count();

        $nearLimitCount = $currentMonthBudgets->filter(function ($budget) use ($startOfMonth, $endOfMonth) {
            $currentSpent = $this->calculateCurrentSpent($budget, $startOfMonth, $endOfMonth);
            $percentage = $budget->amount > 0 ? ($currentSpent / $budget->amount) * 100 : 0;
            return $percentage >= $budget->alert_threshold && $percentage < 100;
        })->count();

        return [
            'total_budgets' => $activeBudgets->count(),
            'current_month_budgets' => $currentMonthBudgets->count(),
            'total_budgeted' => $totalBudgeted,
            'total_spent' => $totalSpent,
            'remaining' => $totalBudgeted - $totalSpent,
            'percentage_used' => $totalBudgeted > 0 ? ($totalSpent / $totalBudgeted) * 100 : 0,
            'over_budget_count' => $overBudgetCount,
            'near_limit_count' => $nearLimitCount,
            'on_track_count' => $currentMonthBudgets->count() - $overBudgetCount - $nearLimitCount,
        ];
    }

    /**
     * Get budget analysis
     */
    public function getBudgetAnalysis(Budget $budget): array
    {
        $currentSpent = $this->calculateCurrentSpent($budget, $budget->start_date, $budget->end_date);
        $remaining = $budget->amount - $currentSpent;
        $percentageUsed = $budget->amount > 0 ? ($currentSpent / $budget->amount) * 100 : 0;

        $status = $this->getBudgetStatus($budget, $currentSpent);
        $trend = $this->getBudgetTrend($budget);
        $projection = $this->getBudgetProjection($budget);

        return [
            'current_spent' => $currentSpent,
            'remaining' => $remaining,
            'percentage_used' => round($percentageUsed, 2),
            'status' => $status,
            'trend' => $trend,
            'projection' => $projection,
            'days_remaining' => $this->getDaysRemaining($budget),
            'daily_average' => $this->getDailyAverage($budget),
            'recommended_daily_spend' => $this->getRecommendedDailySpend($budget, $remaining),
        ];
    }

    /**
     * Get detailed budget analysis
     */
    public function getDetailedBudgetAnalysis(Budget $budget, string $period = 'current', ?string $startDate = null, ?string $endDate = null): array
    {
        $analysis = $this->getBudgetAnalysis($budget);

        // Add transaction breakdown
        $transactions = $this->getBudgetTransactions($budget, $startDate, $endDate);
        $dailySpending = $this->getDailySpending($budget, $startDate, $endDate);
        $weeklySpending = $this->getWeeklySpending($budget, $startDate, $endDate);

        return array_merge($analysis, [
            'transactions' => $transactions,
            'daily_spending' => $dailySpending,
            'weekly_spending' => $weeklySpending,
            'top_transactions' => $transactions->sortByDesc('amount')->take(10)->values(),
            'spending_patterns' => $this->getSpendingPatterns($budget),
        ]);
    }

    /**
     * Reset budget for new period
     */
    public function resetBudget(Budget $budget, string $startDate, string $endDate, bool $carryOverUnused = false, bool $resetSpent = true): Budget
    {
        $newAmount = $budget->amount;

        if ($carryOverUnused) {
            $currentSpent = $this->calculateCurrentSpent($budget, $budget->start_date, $budget->end_date);
            $unused = max(0, $budget->amount - $currentSpent);
            $newAmount += $unused;
        }

        $budget->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $newAmount,
            'spent' => $resetSpent ? 0 : $budget->spent,
        ]);

        if ($resetSpent) {
            $this->recalculateBudgetSpent($budget);
        }

        return $budget;
    }

    /**
     * Calculate current spent amount for budget in given period
     */
    public function calculateCurrentSpent(Budget $budget, string $startDate, string $endDate): float
    {
        return Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Recalculate budget spent amount
     */
    public function recalculateBudgetSpent(Budget $budget): void
    {
        $spent = $this->calculateCurrentSpent($budget, $budget->start_date, $budget->end_date);
        $budget->update(['spent' => $spent]);
    }

    /**
     * Get budget status
     */
    private function getBudgetStatus(Budget $budget, float $currentSpent): string
    {
        $percentage = $budget->amount > 0 ? ($currentSpent / $budget->amount) * 100 : 0;

        if ($percentage >= 100) {
            return 'over_budget';
        } elseif ($percentage >= $budget->alert_threshold) {
            return 'near_limit';
        } elseif ($percentage >= 50) {
            return 'on_track';
        } else {
            return 'under_budget';
        }
    }

    /**
     * Get budget trend
     */
    private function getBudgetTrend(Budget $budget): array
    {
        $daysInPeriod = Carbon::parse($budget->start_date)->diffInDays(Carbon::parse($budget->end_date)) + 1;
        $daysPassed = Carbon::parse($budget->start_date)->diffInDays(Carbon::now()) + 1;
        $daysPassed = min($daysPassed, $daysInPeriod);

        $expectedSpentByNow = ($budget->amount / $daysInPeriod) * $daysPassed;
        $actualSpent = $this->calculateCurrentSpent($budget, $budget->start_date, Carbon::now()->format('Y-m-d'));

        $trendPercentage = $expectedSpentByNow > 0 ? (($actualSpent - $expectedSpentByNow) / $expectedSpentByNow) * 100 : 0;

        return [
            'expected_by_now' => $expectedSpentByNow,
            'actual_spent' => $actualSpent,
            'difference' => $actualSpent - $expectedSpentByNow,
            'trend_percentage' => round($trendPercentage, 2),
            'trend_status' => $trendPercentage > 10 ? 'overspending' : ($trendPercentage < -10 ? 'underspending' : 'on_track'),
        ];
    }

    /**
     * Get budget projection
     */
    private function getBudgetProjection(Budget $budget): array
    {
        $daysInPeriod = Carbon::parse($budget->start_date)->diffInDays(Carbon::parse($budget->end_date)) + 1;
        $daysPassed = max(1, Carbon::parse($budget->start_date)->diffInDays(Carbon::now()) + 1);
        $daysRemaining = max(0, Carbon::parse(Carbon::now())->diffInDays(Carbon::parse($budget->end_date)));

        $currentSpent = $this->calculateCurrentSpent($budget, $budget->start_date, Carbon::now()->format('Y-m-d'));
        $dailyAverage = $daysPassed > 0 ? $currentSpent / $daysPassed : 0;
        $projectedTotal = $currentSpent + ($dailyAverage * $daysRemaining);

        return [
            'projected_total' => round($projectedTotal, 2),
            'projected_over_under' => round($projectedTotal - $budget->amount, 2),
            'daily_average' => round($dailyAverage, 2),
            'days_remaining' => $daysRemaining,
        ];
    }

    /**
     * Get days remaining in budget period
     */
    private function getDaysRemaining(Budget $budget): int
    {
        $endDate = Carbon::parse($budget->end_date);
        $now = Carbon::now();

        return max(0, $now->diffInDays($endDate, false));
    }

    /**
     * Get daily average spending
     */
    private function getDailyAverage(Budget $budget): float
    {
        $daysInPeriod = Carbon::parse($budget->start_date)->diffInDays(Carbon::parse($budget->end_date)) + 1;
        $daysPassed = max(1, Carbon::parse($budget->start_date)->diffInDays(Carbon::now()) + 1);

        $currentSpent = $this->calculateCurrentSpent($budget, $budget->start_date, Carbon::now()->format('Y-m-d'));

        return $daysPassed > 0 ? $currentSpent / $daysPassed : 0;
    }

    /**
     * Get recommended daily spend
     */
    private function getRecommendedDailySpend(Budget $budget, float $remaining): float
    {
        $daysRemaining = $this->getDaysRemaining($budget);

        return $daysRemaining > 0 ? $remaining / $daysRemaining : 0;
    }

    /**
     * Get budget transactions
     */
    private function getBudgetTransactions(Budget $budget, ?string $startDate = null, ?string $endDate = null): \Illuminate\Support\Collection
    {
        $start = $startDate ?? $budget->start_date;
        $end = $endDate ?? $budget->end_date;

        return Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date', 'desc')
            ->get(['id', 'description', 'amount', 'date', 'notes']);
    }

    /**
     * Get daily spending breakdown
     */
    private function getDailySpending(Budget $budget, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ?? $budget->start_date;
        $end = $endDate ?? $budget->end_date;

        return Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$start, $end])
            ->select(DB::raw('DATE(date) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get weekly spending breakdown
     */
    private function getWeeklySpending(Budget $budget, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ?? $budget->start_date;
        $end = $endDate ?? $budget->end_date;

        return Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$start, $end])
            ->select(
                DB::raw('YEAR(date) as year'),
                DB::raw('WEEK(date) as week'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('WEEK(date)'))
            ->orderBy('year')
            ->orderBy('week')
            ->get()
            ->toArray();
    }

    /**
     * Get spending patterns
     */
    private function getSpendingPatterns(Budget $budget): array
    {
        $transactions = $this->getBudgetTransactions($budget);

        return [
            'most_expensive_day' => $this->getMostExpensiveDay($transactions),
            'average_transaction_amount' => $transactions->avg('amount'),
            'transaction_frequency' => $this->getTransactionFrequency($transactions),
            'spending_by_day_of_week' => $this->getSpendingByDayOfWeek($transactions),
        ];
    }

    /**
     * Get most expensive day
     */
    private function getMostExpensiveDay(\Illuminate\Support\Collection $transactions): ?array
    {
        $dailyTotals = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->date)->format('Y-m-d');
        })->map(function ($dayTransactions) {
            return $dayTransactions->sum('amount');
        });

        if ($dailyTotals->isEmpty()) {
            return null;
        }

        $maxDay = $dailyTotals->keys()->first();
        $maxAmount = $dailyTotals->first();

        foreach ($dailyTotals as $day => $amount) {
            if ($amount > $maxAmount) {
                $maxDay = $day;
                $maxAmount = $amount;
            }
        }

        return [
            'date' => $maxDay,
            'amount' => $maxAmount,
        ];
    }

    /**
     * Get transaction frequency
     */
    private function getTransactionFrequency(\Illuminate\Support\Collection $transactions): array
    {
        $totalDays = Carbon::parse($transactions->min('date'))->diffInDays(Carbon::parse($transactions->max('date'))) + 1;
        $transactionDays = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->date)->format('Y-m-d');
        })->count();

        return [
            'total_transactions' => $transactions->count(),
            'total_days' => $totalDays,
            'active_days' => $transactionDays,
            'transactions_per_day' => $totalDays > 0 ? $transactions->count() / $totalDays : 0,
            'days_with_transactions_percentage' => $totalDays > 0 ? ($transactionDays / $totalDays) * 100 : 0,
        ];
    }

    /**
     * Get spending by day of week
     */
    private function getSpendingByDayOfWeek(\Illuminate\Support\Collection $transactions): array
    {
        return $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->date)->dayOfWeek;
        })->map(function ($dayTransactions, $dayOfWeek) {
            return [
                'day_name' => Carbon::now()->startOfWeek()->addDays($dayOfWeek)->format('l'),
                'total_amount' => $dayTransactions->sum('amount'),
                'transaction_count' => $dayTransactions->count(),
                'average_amount' => $dayTransactions->avg('amount'),
            ];
        })->values()->toArray();
    }
}
