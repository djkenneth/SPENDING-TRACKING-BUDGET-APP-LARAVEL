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
        $paymentHistory = $this->payment_history ? json_decode($this->payment_history, true) : [];
        $lastPaidDate = $this->getLastPaidDate($paymentHistory);
        $totalPaid = $this->calculateTotalPaid($paymentHistory);
        $paymentCount = count($paymentHistory);

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
            'is_overdue' => $this->isOverdue(),
            'last_paid_date' => $lastPaidDate,
            'total_paid' => $totalPaid,
            'payment_count' => $paymentCount,
            'last_payment' => $this->getLastPayment(),
            'next_due_amount' => $this->getNextDueAmount(),
            'next_due_date' => $this->getNextDueDate(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the next due date for recurring bills
     */
    private function getNextDueDate(): ?string
    {
        if (!$this->is_recurring) {
            return $this->due_date->format('Y-m-d');
        }

        $dueDate = $this->due_date->copy();
        $now = now();

        while ($dueDate->isPast()) {
            switch ($this->frequency) {
                case 'weekly':
                    $dueDate->addWeek();
                    break;
                case 'bi-weekly':
                    $dueDate->addWeeks(2);
                    break;
                case 'monthly':
                    $dueDate->addMonth();
                    break;
                case 'quarterly':
                    $dueDate->addMonths(3);
                    break;
                case 'semi-annually':
                    $dueDate->addMonths(6);
                    break;
                case 'annually':
                    $dueDate->addYear();
                    break;
            }
        }

        return $dueDate->format('Y-m-d');
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
     * Check if bill is overdue
     */
    private function isOverdue(): bool
    {
        return $this->status === 'overdue' ||
               ($this->status === 'active' && $this->due_date->isPast());
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
     * Get last paid date from payment history
     */
    private function getLastPaidDate(?array $paymentHistory): ?string
    {
        if (empty($paymentHistory)) {
            return null;
        }

        $dates = array_column($paymentHistory, 'date');
        if (empty($dates)) {
            return null;
        }

        rsort($dates);
        return $dates[0];
    }

    /**
     * Calculate total paid from payment history
     */
    private function calculateTotalPaid(?array $paymentHistory): float
    {
        if (empty($paymentHistory)) {
            return 0.0;
        }

        return array_sum(array_column($paymentHistory, 'amount'));
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
