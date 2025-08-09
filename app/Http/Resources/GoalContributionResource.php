<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalContributionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'financial_goal_id' => $this->financial_goal_id,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'date' => $this->date->format('Y-m-d'),
            'notes' => $this->notes,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
