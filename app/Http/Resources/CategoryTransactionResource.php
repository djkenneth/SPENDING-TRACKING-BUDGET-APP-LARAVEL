<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryTransactionResource extends JsonResource
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
            'date' => $this->date,
            'formatted_date' => $this->date->format('M j, Y'),
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'reference_number' => $this->reference_number,
            'is_cleared' => $this->is_cleared,
            'is_recurring' => $this->is_recurring,

            // Account information
            'account' => $this->when($this->relationLoaded('account'), function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'type' => $this->account->type,
                    'color' => $this->account->color,
                    'icon' => $this->account->icon,
                ];
            }),

            // Transfer account information
            'transfer_account' => $this->when(
                $this->relationLoaded('transferAccount') && $this->transferAccount,
                function () {
                    return [
                        'id' => $this->transferAccount->id,
                        'name' => $this->transferAccount->name,
                        'type' => $this->transferAccount->type,
                        'color' => $this->transferAccount->color,
                        'icon' => $this->transferAccount->icon,
                    ];
                }
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get formatted amount with currency symbol and sign
     */
    private function getFormattedAmount(): string
    {
        $currency = $this->account->currency ?? 'USD';
        $currencies = config('user.currencies', []);
        $symbol = $currencies[$currency]['symbol'] ?? $currency;

        $amount = $this->amount;
        $prefix = '';

        // Add sign based on transaction type
        switch ($this->type) {
            case 'income':
                $prefix = '+';
                break;
            case 'expense':
                $prefix = '-';
                break;
            case 'transfer':
                // For transfers, don't add a sign
                break;
        }

        return $prefix . $symbol . number_format(abs($amount), 2);
    }
}
