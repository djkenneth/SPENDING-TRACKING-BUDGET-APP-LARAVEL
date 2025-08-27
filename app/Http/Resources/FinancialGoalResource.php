<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class FinancialGoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $remainingAmount = max(0, $this->target_amount - $this->current_amount);
        $progressPercentage = $this->target_amount > 0
            ? ($this->current_amount / $this->target_amount) * 100
            : 0;

        $daysRemaining = now()->diffInDays($this->target_date, false);
        $monthsRemaining = now()->diffInMonths($this->target_date, false);

        $requiredMonthlyContribution = $monthsRemaining > 0
            ? round($remainingAmount / $monthsRemaining, 2)
            : $remainingAmount;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'description' => $this->description,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'remaining_amount' => $remainingAmount,
            'target_date' => $this->target_date->format('Y-m-d'),
            'priority' => $this->priority,
            'status' => $this->status,
            'color' => $this->color,
            'icon' => $this->icon,
            'monthly_target' => $this->monthly_target ? (float) $this->monthly_target : null,
            'required_monthly_contribution' => $requiredMonthlyContribution,
            'milestone_settings' => $this->milestone_settings,
            'current_milestone' => $this->getCurrentMilestone(),
            'next_milestone' => $this->getNextMilestone(),
            'is_on_track' => $this->isOnTrack(),
            'projected_completion_date' => $this->getProjectedCompletionDate(),
            'progress_percentage' => round($progressPercentage, 2),
            'days_remaining' => max(0, $daysRemaining),
            'is_overdue' => $daysRemaining < 0,
            'is_completed' => $this->status === 'completed',
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'latest_contribution' => $this->when($this->relationLoaded('contributions'), function () {
                $latest = $this->contributions->sortByDesc('date')->first();
                return $latest ? new GoalContributionResource($latest) : null;
            }),
            'contributions_summary' => $this->when($this->relationLoaded('contributions'), function () {
                return $this->getContributionsSummary();
            }),
            'contributions_count' => $this->whenCounted('contributions'),
            'contributions' => GoalContributionResource::collection($this->whenLoaded('contributions')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get human-readable priority label
     */
    private function getPriorityLabel(): string
    {
        $labels = [
            'high' => 'High Priority',
            'medium' => 'Medium Priority',
            'low' => 'Low Priority',
        ];

        return $labels[$this->priority] ?? ucfirst($this->priority);
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'active' => 'Active',
            'completed' => 'Completed',
            'paused' => 'Paused',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get current achieved milestone
     */
    private function getCurrentMilestone(): ?int
    {
        if (!$this->milestone_settings || !isset($this->milestone_settings['milestones'])) {
            return null;
        }

        $progressPercentage = $this->target_amount > 0
            ? ($this->current_amount / $this->target_amount) * 100
            : 0;

        $milestones = $this->milestone_settings['milestones'];
        sort($milestones);

        $currentMilestone = null;
        foreach ($milestones as $milestone) {
            if ($progressPercentage >= $milestone) {
                $currentMilestone = $milestone;
            } else {
                break;
            }
        }

        return $currentMilestone;
    }

    /**
     * Get next milestone to achieve
     */
    private function getNextMilestone(): ?int
    {
        if (!$this->milestone_settings || !isset($this->milestone_settings['milestones'])) {
            return null;
        }

        $progressPercentage = $this->target_amount > 0
            ? ($this->current_amount / $this->target_amount) * 100
            : 0;

        $milestones = $this->milestone_settings['milestones'];
        sort($milestones);

        foreach ($milestones as $milestone) {
            if ($progressPercentage < $milestone) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * Check if goal is on track
     */
    private function isOnTrack(): bool
    {
        if ($this->status !== 'active' || $this->current_amount >= $this->target_amount) {
            return true;
        }

        $totalDays = now()->startOfDay()->diffInDays($this->created_at->startOfDay());
        $remainingDays = now()->startOfDay()->diffInDays($this->target_date->startOfDay(), false);

        if ($totalDays <= 0 || $remainingDays < 0) {
            return false;
        }

        $expectedProgress = ($totalDays / ($totalDays + $remainingDays)) * $this->target_amount;

        return $this->current_amount >= ($expectedProgress * 0.9); // Allow 10% tolerance
    }

    /**
     * Get projected completion date based on current progress
     */
    private function getProjectedCompletionDate(): ?string
    {
        if ($this->status === 'completed' || $this->current_amount >= $this->target_amount) {
            return $this->target_date->format('Y-m-d');
        }

        if (!$this->relationLoaded('contributions') || $this->contributions->isEmpty()) {
            return null;
        }

        // Calculate average monthly contribution from last 3 months
        $threeMonthsAgo = now()->subMonths(3);
        $recentContributions = $this->contributions
            ->where('date', '>=', $threeMonthsAgo)
            ->sum('amount');

        $monthsWithContributions = $this->contributions
            ->where('date', '>=', $threeMonthsAgo)
            ->groupBy(function ($contribution) {
                return Carbon::parse($contribution->date)->format('Y-m');
            })
            ->count();

        if ($monthsWithContributions <= 0) {
            return null;
        }

        $averageMonthlyContribution = $recentContributions / $monthsWithContributions;

        if ($averageMonthlyContribution <= 0) {
            return null;
        }

        $remainingAmount = $this->target_amount - $this->current_amount;
        $monthsToComplete = ceil($remainingAmount / $averageMonthlyContribution);

        return now()->addMonths($monthsToComplete)->format('Y-m-d');
    }

    /**
     * Get contributions summary statistics
     */
    private function getContributionsSummary(): array
    {
        $contributions = $this->contributions;

        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $thisMonthContributions = $contributions
            ->where('date', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthContributions = $contributions
            ->where('date', '>=', $lastMonth)
            ->where('date', '<', $thisMonth)
            ->sum('amount');

        return [
            'total_contributions' => $contributions->count(),
            'total_amount' => round($contributions->sum('amount'), 2),
            'average_contribution' => $contributions->count() > 0
                ? round($contributions->sum('amount') / $contributions->count(), 2)
                : 0,
            'largest_contribution' => round($contributions->max('amount') ?? 0, 2),
            'this_month_total' => round($thisMonthContributions, 2),
            'last_month_total' => round($lastMonthContributions, 2),
        ];
    }
}
