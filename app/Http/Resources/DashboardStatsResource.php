<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'name' => $this->resource['user']->name,
                'currency' => $this->resource['user']->currency,
                'currency_symbol' => $this->resource['user']->getCurrencySymbol(),
            ],
            'financial' => [
                'net_worth' => $this->resource['net_worth'],
                'current_month_income' => $this->resource['current_month_income'],
                'current_month_expenses' => $this->resource['current_month_expenses'],
                'current_month_savings' => $this->resource['current_month_income'] - $this->resource['current_month_expenses'],
            ],
            'accounts' => [
                'total_accounts' => $this->resource['total_accounts'],
                'total_transactions' => $this->resource['total_transactions'],
            ],
            'budgets_and_goals' => [
                'active_budgets' => $this->resource['active_budgets'],
                'active_goals' => $this->resource['active_goals'],
                'active_debts' => $this->resource['active_debts'],
            ],
            'notifications' => [
                'upcoming_bills' => $this->resource['upcoming_bills'],
                'unread_notifications' => $this->resource['unread_notifications'],
            ],
        ];
    }
}
