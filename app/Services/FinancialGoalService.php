<?php

namespace App\Services;

use App\Models\FinancialGoal;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialGoalService
{
    /**
     * Calculate average monthly contribution for a goal
     */
    public function calculateAverageMonthlyContribution(FinancialGoal $goal): float
    {
        $contributions = $goal->contributions()
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->get();

        if ($contributions->isEmpty()) {
            return 0;
        }

        return round($contributions->avg('total'), 2);
    }

    /**
     * Check if goal is on track to meet target
     */
    public function isGoalOnTrack(FinancialGoal $goal): bool
    {
        $daysElapsed = $goal->created_at->diffInDays(now());
        $totalDays = $goal->created_at->diffInDays($goal->target_date);

        if ($totalDays <= 0) {
            return false;
        }

        $expectedProgress = ($daysElapsed / $totalDays) * $goal->target_amount;

        return $goal->current_amount >= ($expectedProgress * 0.9); // 90% threshold
    }

    /**
     * Project completion date based on current contribution rate
     */
    public function projectCompletionDate(FinancialGoal $goal): ?string
    {
        if ($goal->current_amount >= $goal->target_amount) {
            return $goal->completed_at?->format('Y-m-d');
        }

        $avgMonthlyContribution = $this->calculateAverageMonthlyContribution($goal);

        if ($avgMonthlyContribution <= 0) {
            return null;
        }

        $remainingAmount = $goal->target_amount - $goal->current_amount;
        $monthsNeeded = ceil($remainingAmount / $avgMonthlyContribution);

        return now()->addMonths($monthsNeeded)->format('Y-m-d');
    }

    /**
     * Get contribution statistics for a goal
     */
    public function getContributionStatistics(FinancialGoal $goal, string $period = 'monthly'): array
    {
        $query = $goal->contributions();

        switch ($period) {
            case 'weekly':
                $startDate = now()->subWeeks(12);
                $groupBy = "YEARWEEK(date)";
                break;
            case 'yearly':
                $startDate = now()->subYears(5);
                $groupBy = "YEAR(date)";
                break;
            case 'all':
                $startDate = $goal->created_at;
                $groupBy = "DATE_FORMAT(date, '%Y-%m')";
                break;
            default: // monthly
                $startDate = now()->subMonths(12);
                $groupBy = "DATE_FORMAT(date, '%Y-%m')";
        }

        $contributions = $query
            ->where('date', '>=', $startDate)
            ->selectRaw("$groupBy as period, COUNT(*) as count, SUM(amount) as total, AVG(amount) as average, MAX(amount) as max, MIN(amount) as min")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totalContributions = $goal->contributions()->sum('amount');
        $contributionCount = $goal->contributions()->count();

        return [
            'total_contributed' => $totalContributions,
            'contribution_count' => $contributionCount,
            'average_contribution' => $contributionCount > 0 ? round($totalContributions / $contributionCount, 2) : 0,
            'largest_contribution' => $goal->contributions()->max('amount') ?? 0,
            'smallest_contribution' => $goal->contributions()->min('amount') ?? 0,
            'last_contribution_date' => $goal->contributions()->latest('date')->first()?->date->format('Y-m-d'),
            'period_data' => $contributions,
        ];
    }

    /**
     * Get progress timeline for a goal
     */
    public function getProgressTimeline(FinancialGoal $goal, string $period = 'monthly'): Collection
    {
        $contributions = $goal->contributions()->orderBy('date')->get();
        $timeline = collect();
        $runningTotal = 0;

        if ($period === 'monthly') {
            $grouped = $contributions->groupBy(function ($item) {
                return $item->date->format('Y-m');
            });
        } elseif ($period === 'weekly') {
            $grouped = $contributions->groupBy(function ($item) {
                return $item->date->format('Y-W');
            });
        } else { // yearly
            $grouped = $contributions->groupBy(function ($item) {
                return $item->date->format('Y');
            });
        }

        foreach ($grouped as $period => $periodContributions) {
            $periodTotal = $periodContributions->sum('amount');
            $runningTotal += $periodTotal;

            $timeline->push([
                'period' => $period,
                'contributions' => $periodContributions->count(),
                'amount' => round($periodTotal, 2),
                'cumulative_amount' => round($runningTotal, 2),
                'progress_percentage' => round(($runningTotal / $goal->target_amount) * 100, 2),
            ]);
        }

        return $timeline;
    }

    /**
     * Calculate goal projection based on current rate
     */
    public function calculateGoalProjection(FinancialGoal $goal): array
    {
        $avgMonthlyContribution = $this->calculateAverageMonthlyContribution($goal);
        $remainingAmount = max(0, $goal->target_amount - $goal->current_amount);
        $monthsUntilTarget = $goal->created_at->diffInMonths($goal->target_date, false);

        if ($monthsUntilTarget <= 0) {
            return [
                'status' => 'overdue',
                'projected_completion_date' => null,
                'required_monthly_contribution' => $remainingAmount,
                'current_monthly_average' => $avgMonthlyContribution,
                'on_track' => false,
            ];
        }

        $requiredMonthlyContribution = $remainingAmount / $monthsUntilTarget;
        $projectedCompletionDate = null;

        if ($avgMonthlyContribution > 0) {
            $monthsNeeded = ceil($remainingAmount / $avgMonthlyContribution);
            $projectedCompletionDate = now()->addMonths($monthsNeeded)->format('Y-m-d');
        }

        return [
            'status' => $this->isGoalOnTrack($goal) ? 'on_track' : 'behind',
            'projected_completion_date' => $projectedCompletionDate,
            'required_monthly_contribution' => round($requiredMonthlyContribution, 2),
            'current_monthly_average' => $avgMonthlyContribution,
            'months_until_target' => $monthsUntilTarget,
            'on_track' => $avgMonthlyContribution >= $requiredMonthlyContribution,
            'contribution_gap' => round(max(0, $requiredMonthlyContribution - $avgMonthlyContribution), 2),
        ];
    }

    /**
     * Get milestone status for a goal
     */
    public function getMilestoneStatus(FinancialGoal $goal): array
    {
        $milestoneSettings = $goal->milestone_settings ?? ['milestones' => [25, 50, 75, 100]];
        $milestones = $milestoneSettings['milestones'] ?? [25, 50, 75, 100];
        $currentProgress = ($goal->current_amount / $goal->target_amount) * 100;

        $milestoneStatus = [];

        foreach ($milestones as $milestone) {
            $milestoneAmount = ($milestone / 100) * $goal->target_amount;
            $achieved = $currentProgress >= $milestone;

            $milestoneStatus[] = [
                'percentage' => $milestone,
                'amount' => round($milestoneAmount, 2),
                'achieved' => $achieved,
                'achieved_date' => $achieved ? $this->getMilestoneAchievedDate($goal, $milestone) : null,
            ];
        }

        return $milestoneStatus;
    }

    /**
     * Get the date when a milestone was achieved
     */
    private function getMilestoneAchievedDate(FinancialGoal $goal, int $milestonePercentage): ?string
    {
        $milestoneAmount = ($milestonePercentage / 100) * $goal->target_amount;

        $contributions = $goal->contributions()->orderBy('date')->get();
        $runningTotal = 0;

        foreach ($contributions as $contribution) {
            $runningTotal += $contribution->amount;
            if ($runningTotal >= $milestoneAmount) {
                return $contribution->date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Check for milestone achievements and create notifications
     */
    public function checkMilestoneAchievements(FinancialGoal $goal): void
    {
        $milestoneSettings = $goal->milestone_settings ?? ['milestones' => [25, 50, 75], 'notifications_enabled' => true];

        if (!($milestoneSettings['notifications_enabled'] ?? true)) {
            return;
        }

        $milestones = $milestoneSettings['milestones'] ?? [25, 50, 75];
        $currentProgress = ($goal->current_amount / $goal->target_amount) * 100;
        $previousProgress = (($goal->current_amount - $goal->contributions()->latest()->first()->amount) / $goal->target_amount) * 100;

        foreach ($milestones as $milestone) {
            if ($previousProgress < $milestone && $currentProgress >= $milestone) {
                $this->createMilestoneNotification($goal, $milestone);
            }
        }
    }

    /**
     * Create milestone achievement notification
     */
    private function createMilestoneNotification(FinancialGoal $goal, int $milestone): void
    {
        Notification::create([
            'user_id' => $goal->user_id,
            'type' => 'goal_milestone',
            'title' => "Goal Milestone Reached! 🎯",
            'message' => "You've reached {$milestone}% of your goal '{$goal->name}'! Keep up the great work!",
            'data' => [
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'milestone' => $milestone,
                'current_amount' => $goal->current_amount,
                'target_amount' => $goal->target_amount,
            ],
            'is_read' => false,
        ]);
    }

    /**
     * Create goal completion notification
     */
    public function createGoalCompletionNotification(FinancialGoal $goal): void
    {
        Notification::create([
            'user_id' => $goal->user_id,
            'type' => 'goal_completed',
            'title' => "Goal Completed! 🎉",
            'message' => "Congratulations! You've successfully completed your goal '{$goal->name}'!",
            'data' => [
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'completed_date' => now()->format('Y-m-d'),
            ],
            'is_read' => false,
        ]);
    }

    /**
     * Get goals that need attention (behind schedule, approaching deadline, etc.)
     */
    public function getGoalsNeedingAttention(int $userId): Collection
    {
        return FinancialGoal::where('user_id', $userId)
            ->where('status', 'active')
            ->get()
            ->filter(function ($goal) {
                // Check if goal is behind schedule
                if (!$this->isGoalOnTrack($goal)) {
                    return true;
                }

                // Check if deadline is approaching (within 30 days)
                if (now()->diffInDays($goal->target_date, false) <= 30) {
                    return true;
                }

                // Check if no contributions in last 30 days
                $lastContribution = $goal->contributions()->latest('date')->first();
                if (!$lastContribution || $lastContribution->date->diffInDays(now()) > 30) {
                    return true;
                }

                return false;
            });
    }

    /**
     * Calculate total savings across all active goals
     */
    public function getTotalSavings(int $userId): float
    {
        return FinancialGoal::where('user_id', $userId)
            ->whereIn('status', ['active', 'completed'])
            ->sum('current_amount');
    }

    /**
     * Get goal recommendations based on user's financial patterns
     */
    public function getGoalRecommendations(int $userId): array
    {
        // This is a simplified version - you can expand with more sophisticated logic
        $activeGoals = FinancialGoal::where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        $recommendations = [];

        if ($activeGoals === 0) {
            $recommendations[] = [
                'type' => 'emergency_fund',
                'title' => 'Start an Emergency Fund',
                'description' => 'Build a safety net of 3-6 months of expenses',
                'suggested_amount' => 50000, // Adjust based on user's expenses
                'priority' => 'high',
            ];
        }

        if ($activeGoals < 3) {
            $recommendations[] = [
                'type' => 'vacation',
                'title' => 'Plan a Vacation Fund',
                'description' => 'Save for your next getaway',
                'suggested_amount' => 30000,
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }
}
