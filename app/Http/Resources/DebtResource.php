<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
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
            'original_balance' => $this->original_balance,
            'current_balance' => $this->current_balance,
            'paid_amount' => $this->original_balance - $this->current_balance,
            'progress_percentage' => $this->getProgressPercentage(),
            'interest_rate' => $this->interest_rate,
            'minimum_payment' => $this->minimum_payment,
            'due_date' => $this->due_date->format('Y-m-d'),
            'days_until_due' => $this->getDaysUntilDue(),
            'payment_frequency' => $this->payment_frequency,
            'payment_frequency_label' => $this->getPaymentFrequencyLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'payments' => DebtPaymentResource::collection($this->whenLoaded('payments')),
            'latest_payment' => $this->when($this->relationLoaded('payments'), function () {
                return $this->payments->sortByDesc('payment_date')->first();
            }),
            'total_payments' => $this->when($this->relationLoaded('payments'), function () {
                return [
                    'count' => $this->payments->count(),
                    'total_amount' => $this->payments->sum('amount'),
                    'total_principal' => $this->payments->sum('principal'),
                    'total_interest' => $this->payments->sum('interest'),
                ];
            }),
            'estimated_payoff' => $this->getEstimatedPayoff(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get type label
     */
    private function getTypeLabel(): string
    {
        $labels = [
            'credit_card' => 'Credit Card',
            'personal_loan' => 'Personal Loan',
            'mortgage' => 'Mortgage',
            'auto_loan' => 'Auto Loan',
            'student_loan' => 'Student Loan',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Get payment frequency label
     */
    private function getPaymentFrequencyLabel(): string
    {
        $labels = [
            'monthly' => 'Monthly',
            'weekly' => 'Weekly',
            'bi-weekly' => 'Bi-Weekly',
        ];

        return $labels[$this->payment_frequency] ?? $this->payment_frequency;
    }

    /**
     * Get status label
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'active' => 'Active',
            'paid_off' => 'Paid Off',
            'closed' => 'Closed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get progress percentage
     */
    private function getProgressPercentage(): float
    {
        if ($this->original_balance == 0) {
            return 100;
        }

        $paidAmount = $this->original_balance - $this->current_balance;
        return round(($paidAmount / $this->original_balance) * 100, 2);
    }

    /**
     * Get days until due
     */
    private function getDaysUntilDue(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Check if debt is overdue
     */
    private function isOverdue(): bool
    {
        return $this->status === 'active' && $this->due_date->isPast();
    }

    /**
     * Get estimated payoff information
     */
    private function getEstimatedPayoff(): array
    {
        if ($this->current_balance == 0 || $this->status !== 'active') {
            return [
                'months' => 0,
                'date' => null,
                'total_interest' => 0,
            ];
        }

        // Simple calculation for estimated payoff
        $monthlyRate = ($this->interest_rate / 100) / 12;
        $monthlyPayment = $this->minimum_payment;

        if ($monthlyRate > 0 && $monthlyPayment > ($this->current_balance * $monthlyRate)) {
            $months = ceil(
                log($monthlyPayment / ($monthlyPayment - $this->current_balance * $monthlyRate)) /
                log(1 + $monthlyRate)
            );
        } else {
            $months = ceil($this->current_balance / $monthlyPayment);
        }

        return [
            'months' => $months,
            'date' => now()->addMonths($months)->format('Y-m-d'),
            'total_interest' => round(($months * $monthlyPayment) - $this->current_balance, 2),
        ];
    }
}
