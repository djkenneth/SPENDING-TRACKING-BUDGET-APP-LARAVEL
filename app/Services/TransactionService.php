<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Models\Category;
// use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TransactionService
{
    /**
     * Create a new transaction
     */
    public function createTransaction(array $data): Transaction
    {
        $user = Auth::user();

        // Handle file attachments
        if (isset($data['attachments'])) {
            $data['attachments'] = $this->handleAttachments($data['attachments']);
        }

        // Create the transaction
        $transaction = $user->transactions()->create([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'transfer_account_id' => $data['transfer_account_id'] ?? null,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
            'tags' => $data['tags'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'location' => $data['location'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurring_type' => $data['recurring_type'] ?? null,
            'recurring_interval' => $data['recurring_interval'] ?? null,
            'recurring_end_date' => $data['recurring_end_date'] ?? null,
            'is_cleared' => $data['is_cleared'] ?? true,
            'cleared_at' => ($data['is_cleared'] ?? true) ? now() : null,
        ]);

        // Update account balances
        $this->updateAccountBalances($transaction);

        // Create recurring transactions if needed
        if ($transaction->is_recurring) {
            $this->createRecurringTransactions($transaction);
        }

        // Update budget spending
        $this->updateBudgetSpending($transaction);

        return $transaction;
    }

    /**
     * Update a transaction
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        $oldAmount = $transaction->amount;
        $oldAccountId = $transaction->account_id;
        $oldTransferAccountId = $transaction->transfer_account_id;
        $oldType = $transaction->type;

        // Handle file attachments
        if (isset($data['attachments'])) {
            $data['attachments'] = $this->handleAttachments($data['attachments'], $transaction->attachments);
        }

        // Update the transaction
        $transaction->update($data);

        // Revert old account balance changes
        $this->revertAccountBalances($transaction, $oldAmount, $oldAccountId, $oldTransferAccountId, $oldType);

        // Apply new account balance changes
        $this->updateAccountBalances($transaction);

        // Update budget spending
        $this->updateBudgetSpending($transaction, $oldAmount, $oldType);

        return $transaction;
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        // Revert account balance changes
        $this->revertAccountBalances(
            $transaction,
            $transaction->amount,
            $transaction->account_id,
            $transaction->transfer_account_id,
            $transaction->type
        );

        // Update budget spending
        $this->updateBudgetSpending($transaction, $transaction->amount, $transaction->type, true);

        // Delete attachments
        if ($transaction->attachments) {
            $this->deleteAttachments($transaction->attachments);
        }

        // Delete the transaction
        $transaction->delete();
    }

    /**
     * Bulk create transactions
     */
    public function bulkCreateTransactions(array $transactionsData): Collection
    {
        $transactions = collect();

        foreach ($transactionsData as $data) {
            $transaction = $this->createTransaction($data);
            $transactions->push($transaction);
        }

        return $transactions;
    }

    /**
     * Bulk delete transactions
     */
    public function bulkDeleteTransactions(Collection $transactions): int
    {
        $deletedCount = 0;

        foreach ($transactions as $transaction) {
            $this->deleteTransaction($transaction);
            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * Search transactions
     */
    public function searchTransactions(User $user, string $query, array $filters = [], int $limit = 20): Collection
    {
        $searchQuery = $user->transactions()
            ->with(['account', 'category', 'transferAccount'])
            ->where(function ($q) use ($query) {
                $q->where('description', 'like', "%{$query}%")
                  ->orWhere('notes', 'like', "%{$query}%")
                  ->orWhere('reference_number', 'like', "%{$query}%")
                  ->orWhereHas('category', function ($categoryQuery) use ($query) {
                      $categoryQuery->where('name', 'like', "%{$query}%");
                  })
                  ->orWhereHas('account', function ($accountQuery) use ($query) {
                      $accountQuery->where('name', 'like', "%{$query}%");
                  });
            });

        // Apply filters
        if (isset($filters['type'])) {
            $searchQuery->where('type', $filters['type']);
        }

        if (isset($filters['account_id'])) {
            $searchQuery->where('account_id', $filters['account_id']);
        }

        if (isset($filters['category_id'])) {
            $searchQuery->where('category_id', $filters['category_id']);
        }

        return $searchQuery->latest('date')->limit($limit)->get();
    }

    /**
     * Import transactions from CSV
     */
    public function importTransactionsFromCsv(User $user, UploadedFile $file, array $mappings, array $options = []): array
    {
        $content = file_get_contents($file->getRealPath());
        $lines = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($lines);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (empty(array_filter($line))) {
                continue; // Skip empty lines
            }

            try {
                $transactionData = $this->mapCsvRowToTransaction($line, $headers, $mappings, $options, $user);

                // Check for duplicates if option is enabled
                if ($options['skip_duplicates'] ?? false) {
                    if ($this->isDuplicateTransaction($user, $transactionData)) {
                        $skipped++;
                        continue;
                    }
                }

                $this->createTransaction($transactionData);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'line' => $index + 2, // +2 because we removed headers and arrays are 0-indexed
                    'error' => $e->getMessage(),
                    'data' => $line,
                ];
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_processed' => count($lines),
        ];
    }

    /**
     * Export transactions
     */
    public function exportTransactions(User $user, string $format, array $filters = [], array $options = []): array
    {
        $query = $user->transactions()->with(['account', 'category', 'transferAccount']);

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        $fileName = 'transactions_export_' . now()->format('Y_m_d_H_i_s') . '.' . $format;
        $filePath = 'exports/' . $fileName;

        switch ($format) {
            case 'csv':
                $this->exportToCsv($transactions, $filePath);
                break;
            case 'xlsx':
                $this->exportToXlsx($transactions, $filePath);
                break;
            case 'pdf':
                $this->exportToPdf($transactions, $filePath);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }

        return [
            'download_url' => Storage::disk('private')->url($filePath),
            'file_name' => $fileName,
            'file_size' => Storage::disk('private')->size($filePath),
            'total_records' => $transactions->count(),
            'expires_at' => now()->addHours(24)->toISOString(),
        ];
    }

    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics(User $user, string $period, array $filters = []): array
    {
        $dateRange = $this->getDateRangeForPeriod($period);

        $query = $user->transactions()
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']]);

        // Apply filters
        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $transactions = $query->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expenses = $transactions->where('type', 'expense')->sum('amount');
        $transfers = $transactions->where('type', 'transfer')->sum('amount');

        // Group by category
        $categoryBreakdown = $transactions->groupBy('category_id')->map(function ($categoryTransactions) {
            $category = $categoryTransactions->first()->category;
            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_color' => $category->color,
                'total_amount' => $categoryTransactions->sum('amount'),
                'transaction_count' => $categoryTransactions->count(),
                'average_amount' => $categoryTransactions->avg('amount'),
            ];
        })->values();

        // Group by account
        $accountBreakdown = $transactions->groupBy('account_id')->map(function ($accountTransactions) {
            $account = $accountTransactions->first()->account;
            return [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'total_amount' => $accountTransactions->sum('amount'),
                'transaction_count' => $accountTransactions->count(),
            ];
        })->values();

        // Daily breakdown
        $dailyBreakdown = $transactions->groupBy(function ($transaction) {
            return $transaction->date->format('Y-m-d');
        })->map(function ($dayTransactions, $date) {
            return [
                'date' => $date,
                'income' => $dayTransactions->where('type', 'income')->sum('amount'),
                'expenses' => $dayTransactions->where('type', 'expense')->sum('amount'),
                'transfers' => $dayTransactions->where('type', 'transfer')->sum('amount'),
                'net' => $dayTransactions->where('type', 'income')->sum('amount') -
                        $dayTransactions->where('type', 'expense')->sum('amount'),
                'transaction_count' => $dayTransactions->count(),
            ];
        })->values();

        return [
            'period' => $period,
            'date_range' => $dateRange,
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_income' => $income,
                'total_expenses' => $expenses,
                'total_transfers' => $transfers,
                'net_income' => $income - $expenses,
                'average_transaction' => $transactions->count() > 0 ? $transactions->avg('amount') : 0,
            ],
            'transactions_by_type' => [
                'income' => $transactions->where('type', 'income')->count(),
                'expense' => $transactions->where('type', 'expense')->count(),
                'transfer' => $transactions->where('type', 'transfer')->count(),
            ],
            'category_breakdown' => $categoryBreakdown,
            'account_breakdown' => $accountBreakdown,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Update account balances after transaction
     */
    private function updateAccountBalances(Transaction $transaction): void
    {
        $account = Account::find($transaction->account_id);
        $transferAccount = $transaction->transfer_account_id ? Account::find($transaction->transfer_account_id) : null;

        switch ($transaction->type) {
            case 'income':
                $account->increment('balance', $transaction->amount);
                break;

            case 'expense':
                if ($account->type === 'credit_card') {
                    // For credit cards, expenses increase the balance (more debt)
                    $account->increment('balance', $transaction->amount);
                } else {
                    // For other accounts, expenses decrease the balance
                    $account->decrement('balance', $transaction->amount);
                }
                break;

            case 'transfer':
                if ($account->type === 'credit_card') {
                    $account->increment('balance', $transaction->amount);
                } else {
                    $account->decrement('balance', $transaction->amount);
                }

                if ($transferAccount) {
                    if ($transferAccount->type === 'credit_card') {
                        $transferAccount->decrement('balance', $transaction->amount);
                    } else {
                        $transferAccount->increment('balance', $transaction->amount);
                    }
                }
                break;
        }

        // Record balance history
        $this->recordAccountBalanceHistory($account, $transaction);

        if ($transferAccount) {
            $this->recordAccountBalanceHistory($transferAccount, $transaction);
        }
    }

    /**
     * Revert account balances
     */
    private function revertAccountBalances(Transaction $transaction, float $oldAmount, int $oldAccountId, ?int $oldTransferAccountId, string $oldType): void
    {
        $account = Account::find($oldAccountId);
        $transferAccount = $oldTransferAccountId ? Account::find($oldTransferAccountId) : null;

        switch ($oldType) {
            case 'income':
                $account->decrement('balance', $oldAmount);
                break;

            case 'expense':
                if ($account->type === 'credit_card') {
                    $account->decrement('balance', $oldAmount);
                } else {
                    $account->increment('balance', $oldAmount);
                }
                break;

            case 'transfer':
                if ($account->type === 'credit_card') {
                    $account->decrement('balance', $oldAmount);
                } else {
                    $account->increment('balance', $oldAmount);
                }

                if ($transferAccount) {
                    if ($transferAccount->type === 'credit_card') {
                        $transferAccount->increment('balance', $oldAmount);
                    } else {
                        $transferAccount->decrement('balance', $oldAmount);
                    }
                }
                break;
        }
    }

    /**
     * Update budget spending
     */
    private function updateBudgetSpending(Transaction $transaction, ?float $oldAmount = null, ?string $oldType = null, bool $isDelete = false): void
    {
        if ($transaction->type !== 'expense' && $oldType !== 'expense') {
            return;
        }

        $user = $transaction->user;
        $currentDate = $transaction->date;

        // Find active budgets for this category
        $budgets = $user->budgets()
            ->where('category_id', $transaction->category_id)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->where('is_active', true)
            ->get();

        foreach ($budgets as $budget) {
            if ($isDelete && $oldType === 'expense') {
                // Subtract old amount when deleting
                $budget->decrement('spent', $oldAmount);
            } elseif ($oldAmount !== null && $oldType === 'expense') {
                // Update: subtract old amount and add new amount
                $budget->decrement('spent', $oldAmount);
                if ($transaction->type === 'expense') {
                    $budget->increment('spent', $transaction->amount);
                }
            } elseif ($transaction->type === 'expense') {
                // Add new expense
                $budget->increment('spent', $transaction->amount);
            }
        }
    }

    /**
     * Handle file attachments
     */
    private function handleAttachments(array $attachments, ?array $existingAttachments = null): array
    {
        $processedAttachments = [];

        foreach ($attachments as $attachment) {
            if ($attachment instanceof UploadedFile) {
                $path = $attachment->store('attachments', 'public');

                $processedAttachments[] = [
                    'id' => Str::uuid(),
                    'name' => $attachment->getClientOriginalName(),
                    'path' => $path,
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getMimeType(),
                    'uploaded_at' => now()->toISOString(),
                ];
            }
        }

        // Merge with existing attachments if updating
        if ($existingAttachments) {
            $processedAttachments = array_merge($existingAttachments, $processedAttachments);
        }

        return $processedAttachments;
    }

    /**
     * Delete attachments
     */
    private function deleteAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (isset($attachment['path']) && Storage::disk('public')->exists($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }
    }

    /**
     * Create recurring transaction template from a transaction
     *
     * This method creates an entry in the recurring_transactions table
     * which serves as a template for generating future transactions.
     * A scheduled job/command can then use this table to automatically
     * create transactions when they're due.
     */
    private function createRecurringTransactions(Transaction $transaction): RecurringTransaction
    {
        $user = Auth::user();

        // Calculate next occurrence based on the transaction date and frequency
        $nextOccurrence = $this->calculateNextOccurrenceDate(
            $transaction->date,
            $transaction->recurring_type,
            $transaction->recurring_interval ?? 1
        );

        // Calculate max occurrences if end date is provided
        $maxOccurrences = null;
        if ($transaction->recurring_end_date) {
            $maxOccurrences = $this->calculateMaxOccurrences(
                $transaction->date,
                $transaction->recurring_end_date,
                $transaction->recurring_type,
                $transaction->recurring_interval ?? 1
            );
        }

        // Create the recurring transaction template
        $recurringTransaction = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $transaction->account_id,
            'category_id' => $transaction->category_id,
            'name' => $transaction->description, // Use description as name
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'type' => $transaction->type,
            'frequency' => $transaction->recurring_type,
            'interval' => $transaction->recurring_interval ?? 1,
            'start_date' => $transaction->date,
            'end_date' => $transaction->recurring_end_date,
            'next_occurrence' => $nextOccurrence,
            'is_active' => true,
            'occurrences_count' => 1, // First transaction already created
            'max_occurrences' => $maxOccurrences,
        ]);

        return $recurringTransaction;
    }


    /**
     * Calculate the next occurrence date based on frequency and interval
     */
    private function calculateNextOccurrenceDate(
        Carbon|string $fromDate,
        string $frequency,
        int $interval = 1
    ): Carbon {
        $date = $fromDate instanceof Carbon ? $fromDate->copy() : Carbon::parse($fromDate);

        return match ($frequency) {
            'weekly' => $date->addWeeks($interval),
            'monthly' => $date->addMonths($interval),
            'quarterly' => $date->addMonths($interval * 3),
            'yearly' => $date->addYears($interval),
            default => $date->addMonths($interval),
        };
    }


    /**
     * Calculate maximum number of occurrences between start and end date
     */
    private function calculateMaxOccurrences(
        Carbon|string $startDate,
        Carbon|string $endDate,
        string $frequency,
        int $interval = 1
    ): int {
        $start = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate->copy() : Carbon::parse($endDate);

        $occurrences = 1; // Include the first transaction
        $current = $start->copy();

        while (true) {
            $current = $this->calculateNextOccurrenceDate($current, $frequency, $interval);

            if ($current->isAfter($end)) {
                break;
            }

            $occurrences++;

            // Safety limit to prevent infinite loops
            if ($occurrences > 1000) {
                break;
            }
        }

        return $occurrences;
    }

    /**
     * Process due recurring transactions and create actual transactions
     * This method should be called by a scheduled command (e.g., daily)
     */
    public function processDueRecurringTransactions(): array
    {
        $processedCount = 0;
        $errors = [];

        $dueRecurringTransactions = RecurringTransaction::where('is_active', true)
            ->where('next_occurrence', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('max_occurrences')
                    ->orWhereRaw('occurrences_count < max_occurrences');
            })
            ->get();

        foreach ($dueRecurringTransactions as $recurring) {
            try {
                DB::beginTransaction();

                // Create the transaction
                $transaction = $recurring->user->transactions()->create([
                    'account_id' => $recurring->account_id,
                    'category_id' => $recurring->category_id,
                    'description' => $recurring->description,
                    'amount' => $recurring->amount,
                    'type' => $recurring->type,
                    'date' => $recurring->next_occurrence,
                    'is_recurring' => true,
                    'recurring_type' => $recurring->frequency,
                    'recurring_interval' => $recurring->interval,
                    'recurring_end_date' => $recurring->end_date,
                    'is_cleared' => false, // Auto-generated transactions start as uncleared
                ]);

                // Update account balances
                $this->updateAccountBalances($transaction);

                // Update budget spending
                $this->updateBudgetSpending($transaction);

                // Update the recurring transaction template
                $recurring->update([
                    'next_occurrence' => $this->calculateNextOccurrenceDate(
                        $recurring->next_occurrence,
                        $recurring->frequency,
                        $recurring->interval
                    ),
                    'occurrences_count' => $recurring->occurrences_count + 1,
                ]);

                // Deactivate if max occurrences reached or past end date
                if ($this->shouldDeactivateRecurring($recurring)) {
                    $recurring->update(['is_active' => false]);
                }

                DB::commit();
                $processedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = [
                    'recurring_id' => $recurring->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Check if a recurring transaction should be deactivated
     */
    private function shouldDeactivateRecurring(RecurringTransaction $recurring): bool
    {
        // Check if max occurrences reached
        if ($recurring->max_occurrences && $recurring->occurrences_count >= $recurring->max_occurrences) {
            return true;
        }

        // Check if next occurrence is past end date
        if ($recurring->end_date && $recurring->next_occurrence->isAfter($recurring->end_date)) {
            return true;
        }

        return false;
    }

    /**
     * Record account balance history
     */
    private function recordAccountBalanceHistory(Account $account, Transaction $transaction): void
    {
        $account->balanceHistory()->updateOrCreate(
            [
                'date' => $transaction->date->format('Y-m-d'),
            ],
            [
                'balance' => $account->balance,
                'change_type' => 'transaction',
                'change_amount' => $transaction->amount,
            ]
        );
    }

    /**
     * Map CSV row to transaction data
     */
    private function mapCsvRowToTransaction(array $row, array $headers, array $mappings, array $options, User $user): array
    {
        $data = [];

        // Map columns
        foreach ($mappings as $field => $columnName) {
            $columnIndex = array_search($columnName, $headers);
            if ($columnIndex !== false && isset($row[$columnIndex])) {
                $data[$field] = trim($row[$columnIndex]);
            }
        }

        // Process date
        if (isset($data['date'])) {
            $dateFormat = $options['date_format'] ?? 'Y-m-d';
            $data['date'] = Carbon::createFromFormat($dateFormat, $data['date'])->format('Y-m-d');
        }

        // Process amount
        if (isset($data['amount'])) {
            $data['amount'] = floatval(str_replace(['$', ','], '', $data['amount']));
        }

        // Set defaults
        $data['account_id'] = $options['default_account_id'] ?? $user->accounts()->first()->id;
        $data['category_id'] = $options['default_category_id'] ?? $user->categories()->first()->id;
        $data['type'] = $data['type'] ?? $options['default_type'] ?? 'expense';

        return $data;
    }

    /**
     * Check if transaction is duplicate
     */
    private function isDuplicateTransaction(User $user, array $transactionData): bool
    {
        return $user->transactions()
            ->where('description', $transactionData['description'])
            ->where('amount', $transactionData['amount'])
            ->where('date', $transactionData['date'])
            ->where('account_id', $transactionData['account_id'])
            ->exists();
    }

    /**
     * Export transactions to CSV
     */
    private function exportToCsv(Collection $transactions, string $filePath): void
    {
        $csv = "Date,Description,Amount,Type,Account,Category,Notes,Reference Number\n";

        foreach ($transactions as $transaction) {
            $csv .= implode(',', [
                $transaction->date->format('Y-m-d'),
                '"' . str_replace('"', '""', $transaction->description) . '"',
                $transaction->amount,
                $transaction->type,
                '"' . str_replace('"', '""', $transaction->account->name) . '"',
                '"' . str_replace('"', '""', $transaction->category->name) . '"',
                '"' . str_replace('"', '""', $transaction->notes ?? '') . '"',
                '"' . str_replace('"', '""', $transaction->reference_number ?? '') . '"',
            ]) . "\n";
        }

        Storage::disk('private')->put($filePath, $csv);
    }

    /**
     * Export transactions to XLSX
     */
    private function exportToXlsx(Collection $transactions, string $filePath): void
    {
        // This would require a library like PhpSpreadsheet
        // For now, we'll just create a CSV with .xlsx extension
        $this->exportToCsv($transactions, $filePath);
    }

    /**
     * Export transactions to PDF
     */
    private function exportToPdf(Collection $transactions, string $filePath): void
    {
        // This would require a library like TCPDF or DomPDF
        // For now, we'll just create a simple text file
        $content = "TRANSACTION REPORT\n";
        $content .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($transactions as $transaction) {
            $content .= $transaction->date->format('Y-m-d') . " - ";
            $content .= $transaction->description . " - ";
            $content .= $transaction->amount . " - ";
            $content .= $transaction->type . "\n";
        }

        Storage::disk('private')->put($filePath, $content);
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
}
