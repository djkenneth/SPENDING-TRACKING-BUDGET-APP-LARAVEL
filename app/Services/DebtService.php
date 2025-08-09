<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DebtService
{
    /**
     * Create a new debt
     */
    public function createDebt(User $user, array $data): Debt
    {
        $data['user_id'] = $user->id;
        $data['status'] = $data['status'] ?? 'active';

        return Debt::create($data);
    }

    /**
     * Update a debt
     */
    public function updateDebt(Debt $debt, array $data): Debt
    {
        // If balance is updated to 0, mark as paid off
        if (isset($data['current_balance']) && $data['current_balance'] == 0) {
            $data['status'] = 'paid_off';
        }

        $debt->update($data);
        return $debt->fresh();
    }

    /**
     * Delete a debt
     */
    public function deleteDebt(Debt $debt): bool
    {
        return $debt->delete();
    }

    /**
     * Record a debt payment
     */
    public function recordPayment(Debt $debt, array $data): DebtPayment
    {
        // Calculate principal and interest portions
        $paymentAmount = $data['amount'];
        $interestRate = $debt->interest_rate / 100;
        $monthlyInterestRate = $interestRate / 12;

        // Calculate interest portion (simple interest for the period)
        $interestPortion = $debt->current_balance * $monthlyInterestRate;
        $interestPortion = min($interestPortion, $paymentAmount); // Interest can't exceed payment

        // Calculate principal portion
        $principalPortion = $paymentAmount - $interestPortion;

        // Create payment record
        $payment = DebtPayment::create([
            'debt_id' => $debt->id,
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount' => $paymentAmount,
            'principal' => $principalPortion,
            'interest' => $interestPortion,
            'payment_date' => $data['payment_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Update debt's current balance
        $newBalance = max(0, $debt->current_balance - $principalPortion);
        $debt->update([
            'current_balance' => $newBalance,
            'status' => $newBalance == 0 ? 'paid_off' : 'active',
        ]);

        return $payment;
    }

    /**
     * Calculate payoff schedule for a debt
     */
    public function calculatePayoffSchedule(Debt $debt, float $extraPayment = 0, string $strategy = 'minimum'): array
    {
        $balance = $debt->current_balance;
        $interestRate = $debt->interest_rate / 100;
        $monthlyInterestRate = $interestRate / 12;
        $minimumPayment = $debt->minimum_payment;
        $totalPayment = $minimumPayment + $extraPayment;

        $schedule = [];
        $month = 0;
        $totalInterestPaid = 0;
        $currentDate = Carbon::now();

        while ($balance > 0 && $month < 360) { // Max 30 years
            $month++;
            $paymentDate = $currentDate->copy()->addMonths($month);

            // Calculate interest for this period
            $interestCharge = $balance * $monthlyInterestRate;

            // Determine payment amount
            $payment = min($totalPayment, $balance + $interestCharge);

            // Calculate principal payment
            $principalPayment = $payment - $interestCharge;

            // Update balance
            $balance = max(0, $balance - $principalPayment);
            $totalInterestPaid += $interestCharge;

            $schedule[] = [
                'month' => $month,
                'date' => $paymentDate->format('Y-m-d'),
                'beginning_balance' => $balance + $principalPayment,
                'payment' => round($payment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestCharge, 2),
                'ending_balance' => round($balance, 2),
                'cumulative_interest' => round($totalInterestPaid, 2),
            ];

            // Break if balance is paid off
            if ($balance <= 0.01) {
                break;
            }
        }

        return [
            'schedule' => $schedule,
            'summary' => [
                'months_to_payoff' => $month,
                'years_to_payoff' => round($month / 12, 1),
                'payoff_date' => $currentDate->copy()->addMonths($month)->format('Y-m-d'),
                'total_amount_paid' => round($debt->current_balance + $totalInterestPaid, 2),
                'total_interest_paid' => round($totalInterestPaid, 2),
                'interest_saved_with_extra_payment' => $this->calculateInterestSaved($debt, $extraPayment),
                'time_saved_months' => $this->calculateTimeSaved($debt, $extraPayment),
            ],
        ];
    }

    /**
     * Calculate interest saved with extra payment
     */
    private function calculateInterestSaved(Debt $debt, float $extraPayment): float
    {
        if ($extraPayment <= 0) {
            return 0;
        }

        // Calculate without extra payment
        $withoutExtra = $this->calculatePayoffSchedule($debt, 0);

        // Calculate with extra payment
        $withExtra = $this->calculatePayoffSchedule($debt, $extraPayment);

        return round(
            $withoutExtra['summary']['total_interest_paid'] - $withExtra['summary']['total_interest_paid'],
            2
        );
    }

    /**
     * Calculate time saved with extra payment
     */
    private function calculateTimeSaved(Debt $debt, float $extraPayment): int
    {
        if ($extraPayment <= 0) {
            return 0;
        }

        // Calculate without extra payment
        $withoutExtra = $this->calculatePayoffSchedule($debt, 0);

        // Calculate with extra payment
        $withExtra = $this->calculatePayoffSchedule($debt, $extraPayment);

        return $withoutExtra['summary']['months_to_payoff'] - $withExtra['summary']['months_to_payoff'];
    }

    /**
     * Calculate total payoff time for multiple debts
     */
    public function calculateTotalPayoffTime(Collection $debts): array
    {
        if ($debts->isEmpty()) {
            return [
                'date' => null,
                'total_interest' => 0,
                'months' => 0,
            ];
        }

        $totalMonths = 0;
        $totalInterest = 0;
        $latestPayoffDate = null;

        foreach ($debts as $debt) {
            $schedule = $this->calculatePayoffSchedule($debt);
            $months = $schedule['summary']['months_to_payoff'];
            $interest = $schedule['summary']['total_interest_paid'];

            $totalInterest += $interest;

            $payoffDate = Carbon::parse($schedule['summary']['payoff_date']);
            if (!$latestPayoffDate || $payoffDate->isAfter($latestPayoffDate)) {
                $latestPayoffDate = $payoffDate;
                $totalMonths = $months;
            }
        }

        return [
            'date' => $latestPayoffDate ? $latestPayoffDate->format('Y-m-d') : null,
            'total_interest' => round($totalInterest, 2),
            'months' => $totalMonths,
            'years' => round($totalMonths / 12, 1),
        ];
    }

    /**
     * Calculate debt consolidation options
     */
    public function calculateConsolidationOptions(Collection $debts, ?float $newInterestRate = null, ?int $loanTermMonths = null): array
    {
        $totalBalance = $debts->sum('current_balance');
        $currentTotalMinimum = $debts->sum('minimum_payment');
        $averageInterestRate = $debts->avg('interest_rate');

        // If no new interest rate provided, use weighted average
        if (!$newInterestRate) {
            $weightedSum = 0;
            foreach ($debts as $debt) {
                $weightedSum += $debt->current_balance * $debt->interest_rate;
            }
            $newInterestRate = $totalBalance > 0 ? $weightedSum / $totalBalance : 0;
        }

        // Default loan terms to evaluate
        $loanTerms = $loanTermMonths ? [$loanTermMonths] : [12, 24, 36, 48, 60];

        $options = [];

        foreach ($loanTerms as $term) {
            $monthlyRate = ($newInterestRate / 100) / 12;

            // Calculate monthly payment using loan formula
            if ($monthlyRate > 0) {
                $monthlyPayment = $totalBalance * ($monthlyRate * pow(1 + $monthlyRate, $term)) / (pow(1 + $monthlyRate, $term) - 1);
            } else {
                $monthlyPayment = $totalBalance / $term;
            }

            $totalPayment = $monthlyPayment * $term;
            $totalInterest = $totalPayment - $totalBalance;

            // Calculate current scenario (without consolidation)
            $currentTotalInterest = 0;
            $currentMaxMonths = 0;

            foreach ($debts as $debt) {
                $schedule = $this->calculatePayoffSchedule($debt);
                $currentTotalInterest += $schedule['summary']['total_interest_paid'];
                $currentMaxMonths = max($currentMaxMonths, $schedule['summary']['months_to_payoff']);
            }

            $options[] = [
                'loan_term_months' => $term,
                'loan_term_years' => round($term / 12, 1),
                'interest_rate' => round($newInterestRate, 2),
                'monthly_payment' => round($monthlyPayment, 2),
                'total_payment' => round($totalPayment, 2),
                'total_interest' => round($totalInterest, 2),
                'savings' => [
                    'monthly_payment_difference' => round($currentTotalMinimum - $monthlyPayment, 2),
                    'total_interest_saved' => round($currentTotalInterest - $totalInterest, 2),
                    'months_saved' => max(0, $currentMaxMonths - $term),
                ],
                'is_beneficial' => $totalInterest < $currentTotalInterest,
            ];
        }

        return [
            'current_situation' => [
                'total_balance' => round($totalBalance, 2),
                'total_minimum_payment' => round($currentTotalMinimum, 2),
                'average_interest_rate' => round($averageInterestRate, 2),
                'number_of_debts' => $debts->count(),
                'debt_types' => $debts->pluck('type')->unique()->values(),
            ],
            'consolidation_options' => $options,
            'recommendation' => $this->getConsolidationRecommendation($options),
        ];
    }

    /**
     * Get consolidation recommendation
     */
    private function getConsolidationRecommendation(array $options): array
    {
        // Find the option with the most total interest saved
        $bestOption = null;
        $maxSavings = 0;

        foreach ($options as $option) {
            if ($option['is_beneficial'] && $option['savings']['total_interest_saved'] > $maxSavings) {
                $maxSavings = $option['savings']['total_interest_saved'];
                $bestOption = $option;
            }
        }

        if ($bestOption) {
            return [
                'recommended' => true,
                'best_term_months' => $bestOption['loan_term_months'],
                'estimated_savings' => $bestOption['savings']['total_interest_saved'],
                'reason' => 'This option provides the best balance of monthly payment and interest savings.',
            ];
        }

        return [
            'recommended' => false,
            'reason' => 'Current debt structure may be more favorable than consolidation at available rates.',
        ];
    }

    /**
     * Get debt payoff strategies
     */
    public function getPayoffStrategies(Collection $debts): array
    {
        $strategies = [];

        // Avalanche Method (Highest Interest First)
        $avalancheOrder = $debts->sortByDesc('interest_rate')->values();
        $strategies['avalanche'] = [
            'name' => 'Avalanche Method',
            'description' => 'Pay off debts with highest interest rates first',
            'order' => $avalancheOrder->map(function ($debt) {
                return [
                    'debt_id' => $debt->id,
                    'debt_name' => $debt->name,
                    'interest_rate' => $debt->interest_rate,
                    'current_balance' => $debt->current_balance,
                ];
            }),
            'benefits' => 'Minimizes total interest paid over time',
        ];

        // Snowball Method (Lowest Balance First)
        $snowballOrder = $debts->sortBy('current_balance')->values();
        $strategies['snowball'] = [
            'name' => 'Snowball Method',
            'description' => 'Pay off debts with smallest balances first',
            'order' => $snowballOrder->map(function ($debt) {
                return [
                    'debt_id' => $debt->id,
                    'debt_name' => $debt->name,
                    'current_balance' => $debt->current_balance,
                    'interest_rate' => $debt->interest_rate,
                ];
            }),
            'benefits' => 'Provides psychological wins by eliminating debts quickly',
        ];

        return $strategies;
    }
}
