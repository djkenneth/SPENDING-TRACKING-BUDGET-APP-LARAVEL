<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialGoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $progressPercentage = $this->target_amount > 0
            ? ($this->current_amount / $this->target_amount) * 100
            : 0;

        $daysRemaining = now()->diffInDays($this->target_date, false);
        $remainingAmount = max(0, $this->target_amount - $this->current_amount);

        return [
            'id' => $this->id,
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
            'monthly_target' => $this->monthly_target,
            'milestone_settings' => $this->milestone_settings,
            'progress_percentage' => round($progressPercentage, 2),
            'days_remaining' => max(0, $daysRemaining),
            'is_overdue' => $daysRemaining < 0,
            'is_completed' => $this->status === 'completed',
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'contributions_count' => $this->whenCounted('contributions'),
            'contributions' => GoalContributionResource::collection($this->whenLoaded('contributions')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
