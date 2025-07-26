<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TransactionResource extends JsonResource
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
            'description' => $this->description,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'date' => $this->date,
            'formatted_date' => $this->date->format('M j, Y'),
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'reference_number' => $this->reference_number,
            'location' => $this->location,
            'attachments' => $this->getFormattedAttachments(),
            'is_recurring' => $this->is_recurring,
            'recurring_type' => $this->recurring_type,
            'recurring_interval' => $this->recurring_interval,
            'recurring_end_date' => $this->recurring_end_date,
            'is_cleared' => $this->is_cleared,
            'cleared_at' => $this->cleared_at,
            'sync_id' => $this->sync_id,
            'synced_at' => $this->synced_at,

            // Related data
            'account' => $this->when($this->relationLoaded('account'), function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'type' => $this->account->type,
                    'color' => $this->account->color,
                    'icon' => $this->account->icon,
                    'currency' => $this->account->currency,
                ];
            }),

            'category' => $this->when($this->relationLoaded('category'), function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'type' => $this->category->type,
                    'color' => $this->category->color,
                    'icon' => $this->category->icon,
                ];
            }),

            'transfer_account' => $this->when(
                $this->relationLoaded('transferAccount') && $this->transferAccount,
                function () {
                    return [
                        'id' => $this->transferAccount->id,
                        'name' => $this->transferAccount->name,
                        'type' => $this->transferAccount->type,
                        'color' => $this->transferAccount->color,
                        'icon' => $this->transferAccount->icon,
                        'currency' => $this->transferAccount->currency,
                    ];
                }
            ),

            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at_human' => $this->updated_at->diffForHumans(),

            // Additional computed fields
            'is_transfer' => $this->type === 'transfer',
            'is_income' => $this->type === 'income',
            'is_expense' => $this->type === 'expense',
            'has_attachments' => $this->attachments && is_array($this->attachments) && count($this->attachments) > 0,
            'attachment_count' => $this->attachments ? count($this->attachments) : 0,
            'has_tags' => $this->tags && is_array($this->tags) && count($this->tags) > 0,
            'tag_count' => $this->tags ? count($this->tags) : 0,
            'has_notes' => !empty($this->notes),
            'is_future' => $this->date->isFuture(),
            'is_today' => $this->date->isToday(),
            'days_ago' => $this->date->diffInDays(now(), false),
        ];
    }

    /**
     * Get formatted amount with currency symbol and sign
     */
    private function getFormattedAmount(): string
    {
        $currency = $this->account->currency ?? 'USD';
        $currencies = config('user.currencies', []);
        $symbol = $currencies[$currency]['symbol'] ?? $currency;

        $amount = $this->amount;
        $prefix = '';

        // Add sign based on transaction type
        switch ($this->type) {
            case 'income':
                $prefix = '+';
                break;
            case 'expense':
                $prefix = '-';
                break;
            case 'transfer':
                // For transfers, don't add a sign
                break;
        }

        return $prefix . $symbol . number_format(abs($amount), 2);
    }

    /**
     * Get transaction type label
     */
    private function getTypeLabel(): string
    {
        $labels = [
            'income' => 'Income',
            'expense' => 'Expense',
            'transfer' => 'Transfer',
        ];

        return $labels[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get formatted attachments with download URLs
     */
    private function getFormattedAttachments(): array
    {
        if (!$this->attachments || !is_array($this->attachments)) {
            return [];
        }

        return array_map(function ($attachment) {
            return [
                'id' => $attachment['id'] ?? null,
                'name' => $attachment['name'] ?? 'Unknown',
                'size' => $attachment['size'] ?? 0,
                'type' => $attachment['type'] ?? 'unknown',
                'url' => isset($attachment['path']) ? Storage::disk('public')->url($attachment['path']) : null,
                'thumbnail' => $this->getThumbnailUrl($attachment),
                'is_image' => $this->isImageFile($attachment['type'] ?? ''),
                'uploaded_at' => $attachment['uploaded_at'] ?? null,
            ];
        }, $this->attachments);
    }

    /**
     * Get thumbnail URL for image attachments
     */
    private function getThumbnailUrl(array $attachment): ?string
    {
        if (!$this->isImageFile($attachment['type'] ?? '')) {
            return null;
        }

        $path = $attachment['path'] ?? null;
        if (!$path) {
            return null;
        }

        // Check if thumbnail exists
        $thumbnailPath = str_replace('/attachments/', '/attachments/thumbnails/', $path);
        if (Storage::disk('public')->exists($thumbnailPath)) {
            return Storage::disk('public')->url($thumbnailPath);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Check if file is an image
     */
    private function isImageFile(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Get additional transaction statistics (when requested)
     */
    public function withStatistics(): array
    {
        return [
            'account_balance_after' => $this->getAccountBalanceAfter(),
            'running_balance' => $this->getRunningBalance(),
            'category_total_this_month' => $this->getCategoryTotalThisMonth(),
            'similar_transactions_count' => $this->getSimilarTransactionsCount(),
        ];
    }

    /**
     * Get account balance after this transaction
     */
    private function getAccountBalanceAfter(): ?float
    {
        // This would need to be calculated based on transaction date and subsequent transactions
        return null; // Placeholder - implement if needed
    }

    /**
     * Get running balance at the time of this transaction
     */
    private function getRunningBalance(): ?float
    {
        // This would need to be calculated based on account history
        return null; // Placeholder - implement if needed
    }

    /**
     * Get total spending in this category for current month
     */
    private function getCategoryTotalThisMonth(): ?float
    {
        if (!$this->relationLoaded('category') || $this->type !== 'expense') {
            return null;
        }

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return $this->category->transactions()
            ->where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
    }

    /**
     * Get count of similar transactions
     */
    private function getSimilarTransactionsCount(): int
    {
        return $this->user->transactions()
            ->where('id', '!=', $this->id)
            ->where('description', 'like', '%' . $this->description . '%')
            ->count();
    }

    /**
     * Get transaction with full details for single transaction view
     */
    public function withFullDetails(): array
    {
        $data = $this->toArray(request());

        // Add extra details for single transaction view
        $data['statistics'] = $this->withStatistics();

        // Add related transactions
        $data['related_transactions'] = $this->getRelatedTransactions();

        // Add recurring information if applicable
        if ($this->is_recurring) {
            $data['recurring_info'] = $this->getRecurringInfo();
        }

        return $data;
    }

    /**
     * Get related transactions
     */
    private function getRelatedTransactions(): array
    {
        $related = collect();

        // If this is a transfer, find the corresponding transaction
        if ($this->type === 'transfer' && $this->transfer_account_id) {
            $correspondingTransfer = $this->user->transactions()
                ->where('id', '!=', $this->id)
                ->where('type', 'transfer')
                ->where('date', $this->date)
                ->where('amount', $this->amount)
                ->where(function ($query) {
                    $query->where('account_id', $this->transfer_account_id)
                          ->where('transfer_account_id', $this->account_id);
                })
                ->first();

            if ($correspondingTransfer) {
                $related->push($correspondingTransfer);
            }
        }

        // Find similar transactions
        $similarTransactions = $this->user->transactions()
            ->where('id', '!=', $this->id)
            ->where('description', 'like', '%' . $this->description . '%')
            ->where('category_id', $this->category_id)
            ->limit(5)
            ->get();

        $related = $related->merge($similarTransactions);

        return $related->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'date' => $transaction->date,
                'account_name' => $transaction->account->name ?? 'Unknown',
                'category_name' => $transaction->category->name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Get recurring information
     */
    private function getRecurringInfo(): array
    {
        if (!$this->is_recurring) {
            return [];
        }

        $nextOccurrence = $this->calculateNextOccurrence();
        $remainingOccurrences = $this->calculateRemainingOccurrences();

        return [
            'recurring_type' => $this->recurring_type,
            'recurring_interval' => $this->recurring_interval,
            'recurring_end_date' => $this->recurring_end_date,
            'next_occurrence' => $nextOccurrence,
            'remaining_occurrences' => $remainingOccurrences,
            'frequency_label' => $this->getFrequencyLabel(),
        ];
    }

    /**
     * Calculate next occurrence date
     */
    private function calculateNextOccurrence(): ?string
    {
        if (!$this->is_recurring || !$this->recurring_type) {
            return null;
        }

        $date = $this->date->copy();
        $interval = $this->recurring_interval ?? 1;

        switch ($this->recurring_type) {
            case 'weekly':
                $nextDate = $date->addWeeks($interval);
                break;
            case 'monthly':
                $nextDate = $date->addMonths($interval);
                break;
            case 'quarterly':
                $nextDate = $date->addMonths($interval * 3);
                break;
            case 'yearly':
                $nextDate = $date->addYears($interval);
                break;
            default:
                return null;
        }

        // Check if next occurrence is before end date
        if ($this->recurring_end_date && $nextDate->isAfter($this->recurring_end_date)) {
            return null;
        }

        return $nextDate->format('Y-m-d');
    }

    /**
     * Calculate remaining occurrences
     */
    private function calculateRemainingOccurrences(): ?int
    {
        if (!$this->is_recurring || !$this->recurring_end_date) {
            return null;
        }

        $currentDate = $this->date->copy();
        $endDate = $this->recurring_end_date;
        $interval = $this->recurring_interval ?? 1;
        $count = 0;

        while ($currentDate->isBefore($endDate)) {
            switch ($this->recurring_type) {
                case 'weekly':
                    $currentDate->addWeeks($interval);
                    break;
                case 'monthly':
                    $currentDate->addMonths($interval);
                    break;
                case 'quarterly':
                    $currentDate->addMonths($interval * 3);
                    break;
                case 'yearly':
                    $currentDate->addYears($interval);
                    break;
                default:
                    return null;
            }

            if ($currentDate->isBeforeOrEqualTo($endDate)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get frequency label
     */
    private function getFrequencyLabel(): string
    {
        if (!$this->is_recurring) {
            return '';
        }

        $interval = $this->recurring_interval ?? 1;
        $type = $this->recurring_type;

        if ($interval === 1) {
            return ucfirst($type);
        }

        $labels = [
            'weekly' => 'week',
            'monthly' => 'month',
            'quarterly' => 'quarter',
            'yearly' => 'year',
        ];

        $unit = $labels[$type] ?? $type;
        return "Every {$interval} {$unit}s";
    }
}
