<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BillResource extends JsonResource
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
            'user_id' => $this->user_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'due_date' => $this->due_date,
            'formatted_due_date' => Carbon::parse($this->due_date)->format('M d, Y'),
            'days_until_due' => $this->getDaysUntilDue(),
            'frequency' => $this->frequency,
            'frequency_label' => $this->getFrequencyLabel(),
            'reminder_days' => $this->reminder_days,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'is_recurring' => $this->is_recurring,
            'color' => $this->color,
            'icon' => $this->icon,
            'notes' => $this->notes,
            'payment_history' => $this->payment_history,
            'payment_count' => count($this->payment_history ?? []),
            'last_payment' => $this->getLastPayment(),
            'next_due_amount' => $this->getNextDueAmount(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get formatted amount with currency symbol
     */
    private function getFormattedAmount(): string
    {
        $user = auth()->user();
        $symbol = $user ? $user->getCurrencySymbol() : '$';

        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Get days until due date
     */
    private function getDaysUntilDue(): ?int
    {
        if ($this->status === 'paid') {
            return null;
        }

        $dueDate = Carbon::parse($this->due_date);
        $today = Carbon::today();

        if ($dueDate->isPast()) {
            return -$dueDate->diffInDays($today);
        }

        return $dueDate->diffInDays($today);
    }

    /**
     * Get frequency label
     */
    private function getFrequencyLabel(): string
    {
        $labels = [
            'weekly' => 'Weekly',
            'bi-weekly' => 'Bi-Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'semi-annually' => 'Semi-Annually',
            'annually' => 'Annually',
        ];

        return $labels[$this->frequency] ?? ucfirst($this->frequency);
    }

    /**
     * Get last payment information
     */
    private function getLastPayment(): ?array
    {
        if (empty($this->payment_history)) {
            return null;
        }

        $lastPayment = collect($this->payment_history)
            ->sortByDesc('payment_date')
            ->first();

        if (!$lastPayment) {
            return null;
        }

        return [
            'date' => $lastPayment['payment_date'],
            'amount' => $lastPayment['amount'],
            'formatted_amount' => $this->formatCurrency($lastPayment['amount']),
        ];
    }

    /**
     * Get next due amount (considering partial payments)
     */
    private function getNextDueAmount(): float
    {
        // For now, return the full amount
        // This could be enhanced to handle partial payments
        return $this->amount;
    }

    /**
     * Format currency
     */
    private function formatCurrency(float $amount): string
    {
        $user = auth()->user();
        $symbol = $user ? $user->getCurrencySymbol() : '$';

        return $symbol . number_format($amount, 2);
    }
}
