<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryAnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category_id' => $this->resource['category_id'],
            'category_name' => $this->resource['category_name'],
            'category_color' => $this->resource['category_color'],
            'category_icon' => $this->resource['category_icon'],
            'total_amount' => $this->resource['total_amount'],
            'transaction_count' => $this->resource['transaction_count'],
            'average_amount' => $this->resource['average_amount'],
            'percentage_of_total' => $this->resource['percentage_of_total'],
            'trend' => $this->resource['trend'] ?? null,
            'monthly_data' => $this->resource['monthly_data'] ?? [],
            'formatted_total_amount' => $this->getFormattedAmount($this->resource['total_amount']),
            'formatted_average_amount' => $this->getFormattedAmount($this->resource['average_amount']),
        ];
    }

    /**
     * Get formatted amount with currency symbol
     */
    private function getFormattedAmount(float $amount): string
    {
        $user = auth()->user();
        $symbol = $user ? $user->getCurrencySymbol() : '$';

        return $symbol . number_format(abs($amount), 2);
    }
}
