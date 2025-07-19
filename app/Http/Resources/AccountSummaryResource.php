<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_accounts' => $this->resource['total_accounts'],
            'total_balance' => $this->resource['total_balance'],
            'formatted_total_balance' => $this->getFormattedAmount($this->resource['total_balance']),
            'net_worth' => $this->resource['net_worth'],
            'formatted_net_worth' => $this->getFormattedAmount($this->resource['net_worth']),
            'currency' => $this->resource['currency'],
            'currency_symbol' => $this->resource['currency_symbol'],
            'accounts_by_type' => $this->resource['accounts_by_type'],
            'credit_utilization' => $this->when(
                isset($this->resource['credit_utilization']),
                $this->resource['credit_utilization']
            ),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get formatted amount with currency symbol
     */
    private function getFormattedAmount(float $amount): string
    {
        $symbol = $this->resource['currency_symbol'];
        $prefix = $amount < 0 ? '-' : '';

        return $prefix . $symbol . number_format(abs($amount), 2);
    }
}
