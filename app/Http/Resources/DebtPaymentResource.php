<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtPaymentResource extends JsonResource
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
            'debt_id' => $this->debt_id,
            'debt' => new DebtResource($this->whenLoaded('debt')),
            'transaction_id' => $this->transaction_id,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'amount' => $this->amount,
            'principal' => $this->principal,
            'interest' => $this->interest,
            'principal_percentage' => $this->getPrincipalPercentage(),
            'interest_percentage' => $this->getInterestPercentage(),
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'days_ago' => $this->payment_date->diffInDays(now()),
            'notes' => $this->notes,
            'remaining_balance' => $this->whenLoaded('debt', function () {
                return $this->getRemainingBalance();
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get principal percentage of payment
     */
    private function getPrincipalPercentage(): float
    {
        if ($this->amount == 0) {
            return 0;
        }

        return round(($this->principal / $this->amount) * 100, 2);
    }

    /**
     * Get interest percentage of payment
     */
    private function getInterestPercentage(): float
    {
        if ($this->amount == 0) {
            return 0;
        }

        return round(($this->interest / $this->amount) * 100, 2);
    }

    /**
     * Get remaining balance after this payment
     */
    private function getRemainingBalance(): ?float
    {
        if (!$this->relationLoaded('debt')) {
            return null;
        }

        // Calculate remaining balance based on payment history
        $previousPayments = $this->debt->payments()
            ->where('payment_date', '<=', $this->payment_date)
            ->where('id', '<=', $this->id)
            ->sum('principal');

        return $this->debt->original_balance - $previousPayments;
    }
}
