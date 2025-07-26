<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountService
{
    /**
     * Record balance history for an account
     */
    public function recordBalanceHistory(Account $account, float $balance, string $changeType, float $changeAmount = null): void
    {
        $account->balanceHistory()->updateOrCreate(
            [
                'date' => now()->format('Y-m-d'),
            ],
            [
                'balance' => $balance,
                'change_type' => $changeType,
                'change_amount' => $changeAmount,
            ]
        );
    }

    /**
     * Get account statistics
     */
    public function getAccountStatistics(Account $account): array
    {
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Transaction counts
        $totalTransactions = $account->transactions()->count();
        $monthlyTransactions = $account->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();

        // Amount statistics
        $monthlyIncome = $account->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyExpenses = $account->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyTransfers = $account->transactions()
            ->where('type', 'transfer')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Last transaction
        $lastTransaction = $account->transactions()
            ->latest('date')
            ->first();

        // Credit utilization (for credit cards)
        $creditUtilization = null;
        if ($account->type === 'credit_card' && $account->credit_limit > 0) {
            $creditUtilization = (abs($account->balance) / $account->credit_limit) * 100;
        }

        return [
            'total_transactions' => $totalTransactions,
            'monthly_transactions' => $monthlyTransactions,
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'monthly_transfers' => $monthlyTransfers,
            'monthly_net' => $monthlyIncome - $monthlyExpenses,
            'last_transaction_date' => $lastTransaction?->date,
            'last_transaction_amount' => $lastTransaction?->amount,
            'credit_utilization' => $creditUtilization,
            'available_credit' => $account->type === 'credit_card' && $account->credit_limit ?
                $account->credit_limit - abs($account->balance) : null,
        ];
    }

    /**
     * Get balance history for an account
     */
    public function getBalanceHistory(Account $account, string $period = 'month', ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } else {
            $dateRange = $this->getDateRangeForPeriod($period);
            $start = Carbon::parse($dateRange['start']);
            $end = Carbon::parse($dateRange['end']);
        }

        $history = $account->balanceHistory()
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('date')
            ->get();

        // Fill missing dates with previous balance
        $filledHistory = [];
        $lastBalance = 0;

        $current = $start->copy();
        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $record = $history->firstWhere('date', $dateStr);

            if ($record) {
                $lastBalance = $record->balance;
                $filledHistory[] = [
                    'date' => $dateStr,
                    'balance' => $record->balance,
                    'change_amount' => $record->change_amount,
                    'change_type' => $record->change_type,
                ];
            } else {
                $filledHistory[] = [
                    'date' => $dateStr,
                    'balance' => $lastBalance,
                    'change_amount' => 0,
                    'change_type' => null,
                ];
            }

            $current->addDay();
        }

        return $filledHistory;
    }

    /**
     * Check if account can be deleted
     */
    public function canDeleteAccount(Account $account): array
    {
        $reasons = [];

        // Check for recent transactions
        $recentTransactionCount = $account->transactions()
            ->where('date', '>=', now()->subDays(30))
            ->count();

        if ($recentTransactionCount > 0) {
            $reasons[] = "Account has {$recentTransactionCount} transactions in the last 30 days";
        }

        // Check for pending/future transactions
        $futureTransactionCount = $account->transactions()
            ->where('date', '>', now())
            ->count();

        if ($futureTransactionCount > 0) {
            $reasons[] = "Account has {$futureTransactionCount} future transactions";
        }

        // Check for active recurring transactions
        $recurringTransactionCount = $account->user->recurringTransactions()
            ->where('account_id', $account->id)
            ->where('is_active', true)
            ->count();

        if ($recurringTransactionCount > 0) {
            $reasons[] = "Account is used in {$recurringTransactionCount} active recurring transactions";
        }

        // Check if account has outstanding balance (except for investment accounts where fluctuation is normal)
        if ($account->type !== 'investment' && abs($account->balance) > 0.01) {
            $reasons[] = "Account has an outstanding balance of " .
                        $account->user->getCurrencySymbol() . number_format(abs($account->balance), 2);
        }

        // Check if it's the user's last account
        $activeAccountCount = $account->user->accounts()->where('is_active', true)->count();
        if ($activeAccountCount <= 1) {
            $reasons[] = "Cannot delete the last active account";
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Transfer money between accounts
     */
    public function transferMoney(Account $fromAccount, Account $toAccount, float $amount, string $description, string $date, ?string $notes = null): array
    {
        $user = $fromAccount->user;

        // Find or create transfer category
        $transferCategory = $user->categories()
            ->where('type', 'transfer')
            ->where('name', 'Transfer')
            ->first();

        if (!$transferCategory) {
            $transferCategory = $user->categories()->create([
                'name' => 'Transfer',
                'type' => 'transfer',
                'color' => '#00BCD4',
                'icon' => 'swap_horiz',
            ]);
        }

        // Create outgoing transaction
        $fromTransaction = $user->transactions()->create([
            'account_id' => $fromAccount->id,
            'category_id' => $transferCategory->id,
            'transfer_account_id' => $toAccount->id,
            'description' => $description,
            'amount' => $amount,
            'type' => 'transfer',
            'date' => $date,
            'notes' => $notes,
            'is_cleared' => true,
            'cleared_at' => now(),
        ]);

        // Create incoming transaction
        $toTransaction = $user->transactions()->create([
            'account_id' => $toAccount->id,
            'category_id' => $transferCategory->id,
            'transfer_account_id' => $fromAccount->id,
            'description' => $description,
            'amount' => $amount,
            'type' => 'transfer',
            'date' => $date,
            'notes' => $notes,
            'is_cleared' => true,
            'cleared_at' => now(),
        ]);

        // Update account balances
        $this->updateAccountBalanceForTransfer($fromAccount, $amount, 'outgoing');
        $this->updateAccountBalanceForTransfer($toAccount, $amount, 'incoming');

        // Record balance history
        $this->recordBalanceHistory($fromAccount, $fromAccount->balance, 'transaction', -$amount);
        $this->recordBalanceHistory($toAccount, $toAccount->balance, 'transaction', $amount);

        return [
            'transfer_id' => $fromTransaction->id,
            'from_transaction' => $fromTransaction,
            'to_transaction' => $toTransaction,
        ];
    }

    /**
     * Update account balance for transfer
     */
    private function updateAccountBalanceForTransfer(Account $account, float $amount, string $direction): void
    {
        if ($direction === 'outgoing') {
            // Money leaving the account
            if ($account->type === 'credit_card') {
                // For credit cards, money leaving increases the balance (more debt)
                $account->increment('balance', $amount);
            } else {
                // For other accounts, money leaving decreases the balance
                $account->decrement('balance', $amount);
            }
        } else {
            // Money coming into the account
            if ($account->type === 'credit_card') {
                // For credit cards, money coming in decreases the balance (less debt)
                $account->decrement('balance', $amount);
            } else {
                // For other accounts, money coming in increases the balance
                $account->increment('balance', $amount);
            }
        }
    }

    /**
     * Get date range for period
     */
    private function getDateRangeForPeriod(string $period): array
    {
        $now = now();

        switch ($period) {
            case 'week':
                return [
                    'start' => $now->startOfWeek()->format('Y-m-d'),
                    'end' => $now->endOfWeek()->format('Y-m-d'),
                ];
            case 'month':
                return [
                    'start' => $now->startOfMonth()->format('Y-m-d'),
                    'end' => $now->endOfMonth()->format('Y-m-d'),
                ];
            case 'quarter':
                return [
                    'start' => $now->startOfQuarter()->format('Y-m-d'),
                    'end' => $now->endOfQuarter()->format('Y-m-d'),
                ];
            case 'year':
                return [
                    'start' => $now->startOfYear()->format('Y-m-d'),
                    'end' => $now->endOfYear()->format('Y-m-d'),
                ];
            default:
                return [
                    'start' => $now->startOfMonth()->format('Y-m-d'),
                    'end' => $now->endOfMonth()->format('Y-m-d'),
                ];
        }
    }

    /**
     * Sync account balance with actual balance
     */
    public function syncAccountBalance(Account $account, float $actualBalance, string $reason = 'Manual sync'): void
    {
        $oldBalance = $account->balance;
        $difference = $actualBalance - $oldBalance;

        if (abs($difference) > 0.01) { // Only sync if difference is significant
            $account->update(['balance' => $actualBalance]);

            // Record balance history
            $this->recordBalanceHistory($account, $actualBalance, 'sync', $difference);

            // Create adjustment transaction if needed
            if (abs($difference) > 0.01) {
                $this->createBalanceAdjustmentTransaction($account, $difference, $reason);
            }
        }
    }

    /**
     * Create balance adjustment transaction
     */
    private function createBalanceAdjustmentTransaction(Account $account, float $adjustment, string $reason): void
    {
        $user = $account->user;

        // Find or create adjustment category
        $adjustmentCategory = $user->categories()
            ->where('name', 'Balance Adjustment')
            ->first();

        if (!$adjustmentCategory) {
            $adjustmentCategory = $user->categories()->create([
                'name' => 'Balance Adjustment',
                'type' => $adjustment > 0 ? 'income' : 'expense',
                'color' => '#757575',
                'icon' => 'tune',
            ]);
        }

        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $adjustmentCategory->id,
            'description' => 'Balance Adjustment: ' . $reason,
            'amount' => abs($adjustment),
            'type' => $adjustment > 0 ? 'income' : 'expense',
            'date' => now()->format('Y-m-d'),
            'notes' => "Account balance adjusted by {$user->getCurrencySymbol()}" . number_format($adjustment, 2),
            'is_cleared' => true,
            'cleared_at' => now(),
        ]);
    }

    /**
     * Calculate account performance metrics
     */
    public function getAccountPerformanceMetrics(Account $account, int $months = 6): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();
        $endDate = now()->endOfMonth();

        $transactions = $account->transactions()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $monthlyData = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $monthKey = $current->format('Y-m');
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $monthTransactions = $transactions->filter(function ($transaction) use ($monthStart, $monthEnd) {
                return $transaction->date->between($monthStart, $monthEnd);
            });

            $income = $monthTransactions->where('type', 'income')->sum('amount');
            $expenses = $monthTransactions->where('type', 'expense')->sum('amount');
            $transfers = $monthTransactions->where('type', 'transfer')->sum('amount');

            $monthlyData[$monthKey] = [
                'month' => $current->format('M Y'),
                'income' => $income,
                'expenses' => $expenses,
                'transfers' => $transfers,
                'net' => $income - $expenses,
                'transaction_count' => $monthTransactions->count(),
            ];

            $current->addMonth();
        }

        // Calculate trends
        $values = array_values($monthlyData);
        $netAmounts = array_column($values, 'net');
        $trend = $this->calculateTrend($netAmounts);

        return [
            'monthly_data' => array_values($monthlyData),
            'trend' => $trend,
            'total_income' => array_sum(array_column($values, 'income')),
            'total_expenses' => array_sum(array_column($values, 'expenses')),
            'total_net' => array_sum(array_column($values, 'net')),
            'average_monthly_net' => count($values) > 0 ? array_sum(array_column($values, 'net')) / count($values) : 0,
        ];
    }

    /**
     * Calculate trend from array of values
     */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'stable';
        }

        $first = array_slice($values, 0, ceil(count($values) / 2));
        $second = array_slice($values, floor(count($values) / 2));

        $firstAvg = array_sum($first) / count($first);
        $secondAvg = array_sum($second) / count($second);

        $percentChange = $firstAvg != 0 ? (($secondAvg - $firstAvg) / abs($firstAvg)) * 100 : 0;

        if ($percentChange > 10) {
            return 'increasing';
        } elseif ($percentChange < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
}
