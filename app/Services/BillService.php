<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\User;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BillService
{
    /**
     * Create a new bill
     */
    public function createBill(User $user, array $data): Bill
    {
        $data['user_id'] = $user->id;
        $data['status'] = 'active';
        $data['reminder_days'] = $data['reminder_days'] ?? 3;
        $data['is_recurring'] = $data['is_recurring'] ?? true;
        $data['payment_history'] = [];

        // Check if due date is already overdue
        if (Carbon::parse($data['due_date'])->isPast()) {
            $data['status'] = 'overdue';
        }

        return Bill::create($data);
    }

    /**
     * Update a bill
     */
    public function updateBill(Bill $bill, array $data): Bill
    {
        // Check if due date changed and update status accordingly
        if (isset($data['due_date'])) {
            $dueDate = Carbon::parse($data['due_date']);
            if ($dueDate->isPast() && $bill->status === 'active') {
                $data['status'] = 'overdue';
            } elseif ($dueDate->isFuture() && $bill->status === 'overdue') {
                $data['status'] = 'active';
            }
        }

        $bill->update($data);
        return $bill->fresh();
    }

    /**
     * Delete a bill
     */
    public function deleteBill(Bill $bill): bool
    {
        return $bill->delete();
    }

    /**
     * Mark a bill as paid
     */
    public function markBillAsPaid(Bill $bill, array $data): Bill
    {
        $paymentDate = Carbon::parse($data['payment_date']);
        $amount = $data['amount'] ?? $bill->amount;

        // Add to payment history
        $paymentHistory = $bill->payment_history ?? [];
        $paymentHistory[] = [
            'payment_date' => $paymentDate->toDateString(),
            'amount' => $amount,
            'transaction_id' => $data['transaction_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => now()->toDateTimeString(),
        ];

        $updateData = [
            'status' => 'paid',
            'payment_history' => $paymentHistory,
        ];

        // If recurring and next due date is provided, update it
        if ($bill->is_recurring && isset($data['next_due_date'])) {
            $updateData['due_date'] = $data['next_due_date'];
            $updateData['status'] = 'active';
        } elseif ($bill->is_recurring) {
            // Auto-calculate next due date based on frequency
            $nextDueDate = $this->calculateNextDueDate($bill->due_date, $bill->frequency);
            $updateData['due_date'] = $nextDueDate;
            $updateData['status'] = 'active';
        }

        $bill->update($updateData);

        // Create a transaction if not already linked
        if (!isset($data['transaction_id']) && isset($data['create_transaction']) && $data['create_transaction']) {
            $this->createTransactionForBill($bill, $amount, $paymentDate);
        }

        return $bill->fresh();
    }

    /**
     * Calculate next due date based on frequency
     */
    protected function calculateNextDueDate(string $currentDueDate, string $frequency): string
    {
        $date = Carbon::parse($currentDueDate);

        switch ($frequency) {
            case 'weekly':
                return $date->addWeek()->toDateString();
            case 'bi-weekly':
                return $date->addWeeks(2)->toDateString();
            case 'monthly':
                return $date->addMonth()->toDateString();
            case 'quarterly':
                return $date->addMonths(3)->toDateString();
            case 'semi-annually':
                return $date->addMonths(6)->toDateString();
            case 'annually':
                return $date->addYear()->toDateString();
            default:
                return $date->addMonth()->toDateString();
        }
    }

    /**
     * Get upcoming bills
     */
    public function getUpcomingBills(User $user, int $days = 30, ?int $limit = null): Collection
    {
        $endDate = Carbon::now()->addDays($days);

        $query = $user->bills()
            ->with('category')
            ->whereIn('status', ['active', 'overdue'])
            ->where('due_date', '<=', $endDate)
            ->orderBy('due_date', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get overdue bills
     */
    public function getOverdueBills(User $user, ?int $limit = null): Collection
    {
        $query = $user->bills()
            ->with('category')
            ->where('status', 'overdue')
            ->orderBy('due_date', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Update overdue statuses
     */
    public function updateOverdueStatuses(User $user): void
    {
        $user->bills()
            ->where('status', 'active')
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue']);
    }

    /**
     * Get bills summary
     */
    public function getBillsSummary(User $user): array
    {
        $bills = $user->bills;

        $totalMonthlyBills = $bills->where('frequency', 'monthly')->sum('amount');
        $totalAnnualBills = $this->calculateAnnualAmount($bills);

        return [
            'total_bills' => $bills->count(),
            'active_bills' => $bills->where('status', 'active')->count(),
            'overdue_bills' => $bills->where('status', 'overdue')->count(),
            'total_monthly_amount' => round($totalMonthlyBills, 2),
            'total_annual_amount' => round($totalAnnualBills, 2),
            'upcoming_week' => $this->getUpcomingBills($user, 7)->count(),
            'upcoming_month' => $this->getUpcomingBills($user, 30)->count(),
        ];
    }

    /**
     * Calculate annual amount based on frequency
     */
    protected function calculateAnnualAmount(Collection $bills): float
    {
        $annualAmount = 0;

        foreach ($bills as $bill) {
            switch ($bill->frequency) {
                case 'weekly':
                    $annualAmount += $bill->amount * 52;
                    break;
                case 'bi-weekly':
                    $annualAmount += $bill->amount * 26;
                    break;
                case 'monthly':
                    $annualAmount += $bill->amount * 12;
                    break;
                case 'quarterly':
                    $annualAmount += $bill->amount * 4;
                    break;
                case 'semi-annually':
                    $annualAmount += $bill->amount * 2;
                    break;
                case 'annually':
                    $annualAmount += $bill->amount;
                    break;
            }
        }

        return $annualAmount;
    }

    /**
     * Get payment history for a bill
     */
    public function getPaymentHistory(Bill $bill, ?string $startDate = null, ?string $endDate = null, ?int $limit = null): array
    {
        $paymentHistory = collect($bill->payment_history ?? []);

        // Filter by date range if provided
        if ($startDate) {
            $paymentHistory = $paymentHistory->filter(function ($payment) use ($startDate) {
                return Carbon::parse($payment['payment_date'])->greaterThanOrEqualTo($startDate);
            });
        }

        if ($endDate) {
            $paymentHistory = $paymentHistory->filter(function ($payment) use ($endDate) {
                return Carbon::parse($payment['payment_date'])->lessThanOrEqualTo($endDate);
            });
        }

        // Sort by payment date descending
        $paymentHistory = $paymentHistory->sortByDesc('payment_date')->values();

        // Apply limit if provided
        if ($limit) {
            $paymentHistory = $paymentHistory->take($limit);
        }

        return $paymentHistory->toArray();
    }

    /**
     * Get oldest overdue days
     */
    public function getOldestOverdueDays(Collection $bills): ?int
    {
        if ($bills->isEmpty()) {
            return null;
        }

        $oldestDueDate = $bills->min('due_date');
        return Carbon::parse($oldestDueDate)->diffInDays(Carbon::today());
    }

    /**
     * Get bill statistics
     */
    public function getBillStatistics(User $user, string $period = 'month', ?string $startDate = null, ?string $endDate = null): array
    {
        // Set date range based on period if not provided
        if (!$startDate || !$endDate) {
            $now = Carbon::now();
            switch ($period) {
                case 'month':
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                    break;
                case 'quarter':
                    $startDate = $now->copy()->startOfQuarter();
                    $endDate = $now->copy()->endOfQuarter();
                    break;
                case 'year':
                    $startDate = $now->copy()->startOfYear();
                    $endDate = $now->copy()->endOfYear();
                    break;
            }
        }

        $bills = $user->bills()
            ->whereBetween('due_date', [$startDate, $endDate])
            ->get();

        // Calculate statistics
        $totalAmount = $bills->sum('amount');
        $paidBills = $bills->where('status', 'paid');
        $paidAmount = $paidBills->sum('amount');
        $overdueBills = $bills->where('status', 'overdue');
        $overdueAmount = $overdueBills->sum('amount');

        // Category breakdown
        $categoryBreakdown = $bills->groupBy('category_id')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('amount'),
                'average_amount' => $group->avg('amount'),
            ];
        });

        return [
            'period' => [
                'type' => $period,
                'start_date' => Carbon::parse($startDate)->toDateString(),
                'end_date' => Carbon::parse($endDate)->toDateString(),
            ],
            'summary' => [
                'total_bills' => $bills->count(),
                'total_amount' => round($totalAmount, 2),
                'paid_bills' => $paidBills->count(),
                'paid_amount' => round($paidAmount, 2),
                'overdue_bills' => $overdueBills->count(),
                'overdue_amount' => round($overdueAmount, 2),
                'pending_bills' => $bills->where('status', 'active')->count(),
                'pending_amount' => round($bills->where('status', 'active')->sum('amount'), 2),
            ],
            'payment_rate' => $bills->count() > 0 ? round(($paidBills->count() / $bills->count()) * 100, 2) : 0,
            'category_breakdown' => $categoryBreakdown,
            'frequency_breakdown' => $bills->groupBy('frequency')->map->count(),
        ];
    }

    /**
     * Duplicate a bill
     */
    public function duplicateBill(Bill $bill, ?string $name = null, ?string $dueDate = null): Bill
    {
        $data = $bill->only([
            'user_id',
            'category_id',
            'amount',
            'frequency',
            'reminder_days',
            'is_recurring',
            'color',
            'icon',
            'notes',
        ]);

        $data['name'] = $name ?? $bill->name . ' (Copy)';
        $data['due_date'] = $dueDate ?? $bill->due_date;
        $data['status'] = 'active';
        $data['payment_history'] = [];

        // Check if due date is already overdue
        if (Carbon::parse($data['due_date'])->isPast()) {
            $data['status'] = 'overdue';
        }

        return Bill::create($data);
    }

    /**
     * Create a transaction for a bill payment
     */
    protected function createTransactionForBill(Bill $bill, float $amount, Carbon $paymentDate): Transaction
    {
        return Transaction::create([
            'user_id' => $bill->user_id,
            'account_id' => $bill->user->accounts()->first()->id, // Use default account
            'category_id' => $bill->category_id,
            'type' => 'expense',
            'amount' => $amount,
            'description' => "Payment for: {$bill->name}",
            'date' => $paymentDate->toDateString(),
            'is_recurring' => false,
            'tags' => ['bill_payment'],
            'notes' => "Automatic transaction for bill payment: {$bill->name}",
        ]);
    }

    /**
     * Check and send bill reminders
     */
    public function checkAndSendReminders(User $user): array
    {
        $reminders = [];
        $bills = $user->bills()
            ->where('status', 'active')
            ->where('reminder_days', '>', 0)
            ->get();

        foreach ($bills as $bill) {
            $daysUntilDue = Carbon::parse($bill->due_date)->diffInDays(Carbon::today(), false);

            // Check if we should send a reminder
            if ($daysUntilDue == $bill->reminder_days) {
                $reminders[] = [
                    'bill_id' => $bill->id,
                    'bill_name' => $bill->name,
                    'amount' => $bill->amount,
                    'due_date' => $bill->due_date,
                    'days_until_due' => $bill->reminder_days,
                ];

                // Here you would typically trigger a notification
                // This could be email, push notification, or in-app notification
            }
        }

        return $reminders;
    }
}
