<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BudgetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentSpent = $this->current_spent ?? $this->spent;
        $remaining = $this->amount - $currentSpent;
        $percentageUsed = $this->amount > 0 ? ($currentSpent / $this->amount) * 100 : 0;

        $status = $this->getBudgetStatus($percentageUsed);
        $daysRemaining = $this->getDaysRemaining();
        $isOverBudget = $currentSpent > $this->amount;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'spent' => (float) $currentSpent,
            'remaining' => (float) $remaining,
            'percentage_used' => round($percentageUsed, 2),
            'period' => $this->period,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'is_active' => $this->is_active,
            'alert_threshold' => (float) $this->alert_threshold,
            'alert_enabled' => $this->alert_enabled,
            'rollover_settings' => $this->rollover_settings,
            'status' => [
                'code' => $status,
                'label' => $this->getStatusLabel($status),
                'is_over_budget' => $isOverBudget,
                'is_near_limit' => $percentageUsed >= $this->alert_threshold && !$isOverBudget,
                'days_remaining' => $daysRemaining,
                'is_expired' => $daysRemaining < 0,
            ],
            'period_info' => [
                'start_date' => $this->start_date->toDateString(),
                'end_date' => $this->end_date->toDateString(),
                'start_date_formatted' => $this->start_date->format('M j, Y'),
                'end_date_formatted' => $this->end_date->format('M j, Y'),
                'duration_days' => $this->start_date->diffInDays($this->end_date) + 1,
                'days_elapsed' => max(0, $this->start_date->diffInDays(Carbon::now()) + 1),
                'days_remaining' => $daysRemaining,
                'progress_percentage' => $this->getPeriodProgressPercentage(),
            ],
            'spending_info' => [
                'daily_average' => $this->getDailyAverage(),
                'recommended_daily_spend' => $this->getRecommendedDailySpend($remaining, $daysRemaining),
                'pace_indicator' => $this->getPaceIndicator($percentageUsed),
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    /**
     * Get budget status based on percentage used
     */
    private function getBudgetStatus(float $percentageUsed): string
    {
        if ($percentageUsed >= 100) {
            return 'over_budget';
        } elseif ($percentageUsed >= $this->alert_threshold) {
            return 'near_limit';
        } elseif ($percentageUsed >= 50) {
            return 'on_track';
        } else {
            return 'under_budget';
        }
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'over_budget' => 'Over Budget',
            'near_limit' => 'Near Limit',
            'on_track' => 'On Track',
            'under_budget' => 'Under Budget',
            default => 'Unknown',
        };
    }

    /**
     * Get days remaining in budget period
     */
    private function getDaysRemaining(): int
    {
        $now = Carbon::now();
        $endDate = Carbon::parse($this->end_date);

        return $now->diffInDays($endDate, false);
    }

    /**
     * Get period progress percentage
     */
    private function getPeriodProgressPercentage(): float
    {
        $totalDays = $this->start_date->diffInDays($this->end_date) + 1;
        $daysElapsed = max(0, min($totalDays, $this->start_date->diffInDays(Carbon::now()) + 1));

        return $totalDays > 0 ? round(($daysElapsed / $totalDays) * 100, 2) : 0;
    }

    /**
     * Get daily average spending
     */
    private function getDailyAverage(): float
    {
        $daysElapsed = max(1, $this->start_date->diffInDays(Carbon::now()) + 1);
        $currentSpent = $this->current_spent ?? $this->spent;

        return round($currentSpent / $daysElapsed, 2);
    }

    /**
     * Get recommended daily spend for remaining period
     */
    private function getRecommendedDailySpend(float $remaining, int $daysRemaining): float
    {
        if ($daysRemaining <= 0) {
            return 0;
        }

        return round($remaining / $daysRemaining, 2);
    }

    /**
     * Get pace indicator (ahead/behind/on_pace)
     */
    private function getPaceIndicator(float $percentageUsed): string
    {
        $periodProgress = $this->getPeriodProgressPercentage();

        if ($periodProgress == 0) {
            return 'not_started';
        }

        $expectedPercentage = $periodProgress;
        $difference = $percentageUsed - $expectedPercentage;

        if (abs($difference) <= 5) { // Within 5% tolerance
            return 'on_pace';
        } elseif ($difference > 5) {
            return 'ahead';
        } else {
            return 'behind';
        }
    }
}
