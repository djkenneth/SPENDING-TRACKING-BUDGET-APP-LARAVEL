<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at_human' => $this->updated_at->diffForHumans(),

            // Statistics (when loaded)
            'statistics' => $this->when($request->routeIs('categories.show'), function () {
                return [
                    'transaction_count' => $this->transactions()->count(),
                    'total_amount' => $this->getTotalAmount(),
                    'current_month_amount' => $this->getCurrentMonthAmount(),
                    'last_transaction_date' => $this->getLastTransactionDate(),
                    'average_transaction' => $this->getAverageTransaction(),
                    'budget_count' => $this->budgets()->count(),
                    'active_budget_count' => $this->budgets()->where('is_active', true)->count(),
                ];
            }),

            // Computed fields
            'has_transactions' => $this->transactions()->exists(),
            'has_budgets' => $this->budgets()->exists(),
            'can_delete' => $this->canDelete(),
        ];
    }

    /**
     * Get the category type label
     */
    private function getTypeLabel(): string
    {
        $labels = [
            'income' => 'Income',
            'expense' => 'Expense',
            'transfer' => 'Transfer',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get total amount for this category
     */
    private function getTotalAmount(): float
    {
        return $this->transactions()->sum('amount');
    }

    /**
     * Get current month amount for this category
     */
    private function getCurrentMonthAmount(): float
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return $this->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
    }

    /**
     * Get last transaction date
     */
    private function getLastTransactionDate(): ?string
    {
        $lastTransaction = $this->transactions()
            ->latest('date')
            ->first();

        return $lastTransaction?->date?->format('Y-m-d');
    }

    /**
     * Get average transaction amount
     */
    private function getAverageTransaction(): float
    {
        return $this->transactions()->avg('amount') ?? 0;
    }

    /**
     * Check if category can be deleted
     */
    private function canDelete(): bool
    {
        // Cannot delete if has transactions
        if ($this->transactions()->exists()) {
            return false;
        }

        // Cannot delete if has active budgets
        if ($this->budgets()->where('is_active', true)->exists()) {
            return false;
        }

        // Cannot delete if it's the user's only category of this type
        $sameTypeCount = $this->user->categories()
            ->where('type', $this->type)
            ->where('is_active', true)
            ->count();

        return $sameTypeCount > 1;
    }
}
