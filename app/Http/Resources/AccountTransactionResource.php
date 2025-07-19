<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTransactionResource extends JsonResource
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
            'description' => $this->description,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'date' => $this->date,
            'formatted_date' => $this->date->format('M j, Y'),
            'notes' => $this->notes,
            'tags' => $this->tags,
            'reference_number' => $this->reference_number,
            'location' => $this->location,
            'is_cleared' => $this->is_cleared,
            'is_recurring' => $this->is_recurring,
            'category' => $this->when($this->relationLoaded('category'), function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'color' => $this->category->color,
                    'icon' => $this->category->icon,
                ];
            }),
            'account' => $this->when($this->relationLoaded('account'), function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'type' => $this->account->type,
                    'color' => $this->account->color,
                    'icon' => $this->account->icon,
                ];
            }),
            'transfer_account' => $this->when($this->relationLoaded('transferAccount') && $this->transferAccount, function () {
                return [
                    'id' => $this->transferAccount->id,
                    'name' => $this->transferAccount->name,
                    'type' => $this->transferAccount->type,
                    'color' => $this->transferAccount->color,
                    'icon' => $this->transferAccount->icon,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get formatted amount with currency symbol
     */
    private function getFormattedAmount(): string
    {
        $currencies = config('user.currencies', []);
        $currency = $this->account->currency ?? 'USD';
        $symbol = $currencies[$currency]['symbol'] ?? $currency;

        $prefix = '';
        if ($this->type === 'expense' && $this->amount > 0) {
            $prefix = '-';
        } elseif ($this->type === 'income' && $this->amount > 0) {
            $prefix = '+';
        }

        return $prefix . $symbol . number_format(abs($this->amount), 2);
    }

    /**
     * Get transaction type label
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
}
