<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $progressPercentage = $this->target_amount > 0
            ? ($this->current_amount / $this->target_amount) * 100
            : 0;

        $daysElapsed = $this->created_at->diffInDays(now());
        $totalDays = $this->created_at->diffInDays($this->target_date);
        $timeProgressPercentage = $totalDays > 0 ? ($daysElapsed / $totalDays) * 100 : 0;

        return [
            'goal_id' => $this->id,
            'goal_name' => $this->name,
            'progress' => [
                'current_amount' => $this->current_amount,
                'target_amount' => $this->target_amount,
                'remaining_amount' => max(0, $this->target_amount - $this->current_amount),
                'percentage' => round($progressPercentage, 2),
            ],
            'time' => [
                'start_date' => $this->created_at->format('Y-m-d'),
                'target_date' => $this->target_date->format('Y-m-d'),
                'days_elapsed' => $daysElapsed,
                'days_remaining' => max(0, now()->diffInDays($this->target_date, false)),
                'total_days' => $totalDays,
                'time_progress_percentage' => round($timeProgressPercentage, 2),
            ],
            'performance' => [
                'is_ahead_of_schedule' => $progressPercentage > $timeProgressPercentage,
                'performance_ratio' => $timeProgressPercentage > 0
                    ? round($progressPercentage / $timeProgressPercentage, 2)
                    : 0,
            ],
            'monthly_target' => $this->monthly_target,
            'status' => $this->status,
            'priority' => $this->priority,
        ];
    }
}
