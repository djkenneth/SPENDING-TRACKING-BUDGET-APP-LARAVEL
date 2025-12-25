<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CategoryService
{
    /**
     * Create a new category
     */
    public function createCategory(array $data): Category
    {
        $user = Auth::user();

        return $user->categories()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'color' => $data['color'] ?? $this->getDefaultColor($data['type']),
            'icon' => $data['icon'] ?? $this->getDefaultIcon($data['type']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? $this->getNextSortOrder($user),
        ]);
    }

    /**
     * Update a category
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);
        return $category;
    }

    /**
     * Delete a category
     */
    public function deleteCategory(Category $category): void
    {
        // Check if category can be deleted
        $canDelete = $this->canDeleteCategory($category);
        if (!$canDelete['can_delete']) {
            throw new \Exception('Cannot delete category: ' . implode(', ', $canDelete['reasons']));
        }

        $category->delete();
    }

    /**
     * Check if category can be deleted
     */
    public function canDeleteCategory(Category $category): array
    {
        $reasons = [];

        // Check for transactions
        $transactionCount = $category->transactions()->count();
        if ($transactionCount > 0) {
            $reasons[] = "Category has {$transactionCount} transaction(s)";
        }

        // Check for active budgets
        $activeBudgetCount = $category->budgets()->where('is_active', true)->count();
        if ($activeBudgetCount > 0) {
            $reasons[] = "Category has {$activeBudgetCount} active budget(s)";
        }

        // Check if it's the user's only category of this type
        $sameTypeCount = $category->user->categories()
            ->where('type', $category->type)
            ->where('is_active', true)
            ->count();

        if ($sameTypeCount <= 1) {
            $reasons[] = "Cannot delete the last active category of type '{$category->type}'";
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Get category statistics
     */
    public function getCategoryStatistics(Category $category): array
    {
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();
        $lastMonth = $currentMonth->copy()->subMonth();
        $lastMonthStart = $lastMonth->copy()->startOfMonth();
        $lastMonthEnd = $lastMonth->copy()->endOfMonth();

        // Basic statistics
        $totalTransactions = $category->transactions()->count();
        $totalAmount = $category->transactions()->sum('amount');
        $averageAmount = $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0;

        // Current month statistics
        $currentMonthTransactions = $category->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->count();
        $currentMonthAmount = $category->transactions()
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Last month statistics
        $lastMonthAmount = $category->transactions()
            ->whereBetween('date', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        // Calculate trend
        $trend = $this->calculateTrend($lastMonthAmount, $currentMonthAmount);

        // Last transaction
        $lastTransaction = $category->transactions()
            ->latest('date')
            ->first();

        // Budget information
        $activeBudgets = $category->budgets()->where('is_active', true)->count();
        $currentBudget = $category->budgets()
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        return [
            'total_transactions' => $totalTransactions,
            'total_amount' => $totalAmount,
            'average_amount' => $averageAmount,
            'current_month_transactions' => $currentMonthTransactions,
            'current_month_amount' => $currentMonthAmount,
            'last_month_amount' => $lastMonthAmount,
            'trend' => $trend,
            'trend_percentage' => $this->calculateTrendPercentage($lastMonthAmount, $currentMonthAmount),
            'last_transaction_date' => $lastTransaction?->date,
            'last_transaction_amount' => $lastTransaction?->amount,
            'active_budgets' => $activeBudgets,
            'current_budget' => $currentBudget ? [
                'id' => $currentBudget->id,
                'amount' => $currentBudget->amount,
                'spent' => $currentBudget->spent,
                'remaining' => $currentBudget->amount - $currentBudget->spent,
                'percentage' => $currentBudget->amount > 0 ? ($currentBudget->spent / $currentBudget->amount) * 100 : 0,
            ] : null,
        ];
    }

    /**
     * Get categories statistics for all user categories
     */
    public function getCategoriesStatistics(User $user): array
    {
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $categories = $user->categories()->with('transactions')->get();

        $statistics = [
            'total_categories' => $categories->count(),
            'active_categories' => $categories->where('is_active', true)->count(),
            'inactive_categories' => $categories->where('is_active', false)->count(),
            'by_type' => [
                'income' => $categories->where('type', 'income')->count(),
                'expense' => $categories->where('type', 'expense')->count(),
                'transfer' => $categories->where('type', 'transfer')->count(),
            ],
            'with_transactions' => $categories->filter(function ($category) {
                return $category->transactions->count() > 0;
            })->count(),
            'current_month_activity' => $categories->map(function ($category) use ($startOfMonth, $endOfMonth) {
                $monthlyTransactions = $category->transactions->filter(function ($transaction) use ($startOfMonth, $endOfMonth) {
                    return $transaction->date->between($startOfMonth, $endOfMonth);
                });

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'transaction_count' => $monthlyTransactions->count(),
                    'total_amount' => $monthlyTransactions->sum('amount'),
                ];
            })->filter(function ($item) {
                return $item['transaction_count'] > 0;
            })->values(),
        ];

        return $statistics;
    }

    /**
     * Get spending analysis by category
     */
    public function getSpendingAnalysis(User $user, string $period = 'month', string $type = 'expense', ?string $startDate = null, ?string $endDate = null): array
    {
        $dateRange = $this->getDateRangeForPeriod($period, $startDate, $endDate);

        $query = $user->transactions()
            ->where('type', $type)
            ->whereBetween('date', [$dateRange['start'], $dateRange['end']])
            ->with('category');

        $transactions = $query->get();
        $totalAmount = $transactions->sum('amount');

        // Group by category
        $categoryBreakdown = $transactions->groupBy('category_id')->map(function ($categoryTransactions) use ($totalAmount) {
            $category = $categoryTransactions->first()->category;
            $categoryTotal = $categoryTransactions->sum('amount');

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_color' => $category->color,
                'category_icon' => $category->icon,
                'total_amount' => $categoryTotal,
                'transaction_count' => $categoryTransactions->count(),
                'average_amount' => $categoryTransactions->avg('amount'),
                'percentage_of_total' => $totalAmount > 0 ? ($categoryTotal / $totalAmount) * 100 : 0,
            ];
        })->sortByDesc('total_amount')->values();

        return [
            'period' => $period,
            'type' => $type,
            'date_range' => $dateRange,
            'total_amount' => $totalAmount,
            'total_transactions' => $transactions->count(),
            'categories' => $categoryBreakdown,
            'top_categories' => $categoryBreakdown->take(5),
        ];
    }

    /**
     * Get category trends over time
     */
    public function getCategoryTrends(User $user, string $period = 'month', int $months = 6, ?array $categoryIds = null): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subMonths($months);

        $query = $user->transactions()
            ->whereBetween('date', [$startDate, $endDate])
            ->with('category');

        if ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        }

        $transactions = $query->get();

        // Group by category and period
        $trends = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $periodKey = $current->format('Y-m');
            $periodStart = $current->copy()->startOfMonth();
            $periodEnd = $current->copy()->endOfMonth();

            $periodTransactions = $transactions->filter(function ($transaction) use ($periodStart, $periodEnd) {
                return $transaction->date->between($periodStart, $periodEnd);
            });

            $categoryData = $periodTransactions->groupBy('category_id')->map(function ($categoryTransactions) {
                $category = $categoryTransactions->first()->category;
                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_color' => $category->color,
                    'total_amount' => $categoryTransactions->sum('amount'),
                    'transaction_count' => $categoryTransactions->count(),
                ];
            });

            $trends[$periodKey] = [
                'period' => $current->format('M Y'),
                'date' => $current->format('Y-m-d'),
                'categories' => $categoryData->values(),
                'total_amount' => $categoryData->sum('total_amount'),
                'total_transactions' => $categoryData->sum('transaction_count'),
            ];

            $current->addMonth();
        }

        return [
            'period' => $period,
            'months' => $months,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'trends' => array_values($trends),
        ];
    }

    /**
     * Merge two categories
     */
    public function mergeCategories(Category $sourceCategory, Category $targetCategory, bool $deleteSource = false): array
    {
        // Validate that categories belong to the same user and have the same type
        if ($sourceCategory->user_id !== $targetCategory->user_id) {
            throw new \Exception('Categories must belong to the same user');
        }

        if ($sourceCategory->type !== $targetCategory->type) {
            throw new \Exception('Categories must be of the same type');
        }

        $movedTransactions = 0;
        $movedBudgets = 0;

        // Move all transactions from source to target category
        $sourceCategory->transactions()->update(['category_id' => $targetCategory->id]);
        $movedTransactions = $sourceCategory->transactions()->count();

        // Move all budgets from source to target category
        $sourceCategory->budgets()->update(['category_id' => $targetCategory->id]);
        $movedBudgets = $sourceCategory->budgets()->count();

        $result = [
            'moved_transactions' => $movedTransactions,
            'moved_budgets' => $movedBudgets,
            'source_category' => $sourceCategory->name,
            'target_category' => $targetCategory->name,
            'source_deleted' => false,
        ];

        // Delete source category if requested
        if ($deleteSource) {
            $sourceCategory->delete();
            $result['source_deleted'] = true;
        } else {
            // Deactivate source category
            $sourceCategory->update(['is_active' => false]);
        }

        return $result;
    }

    /**
     * Get available icons and colors
     */
    public function getAvailableIconsAndColors(): array
    {
        return [
            'colors' => [
                '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5',
                '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
                '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800',
                '#FF5722', '#795548', '#9E9E9E', '#607D8B', '#000000',
            ],
            'icons' => [
                // Income icons
                'income' => [
                    'work', 'business_center', 'trending_up', 'account_balance_wallet',
                    'monetization_on', 'payment', 'card_giftcard', 'savings',
                ],
                // Expense icons
                'expense' => [
                    'shopping_cart', 'restaurant', 'local_gas_station', 'home',
                    'directions_car', 'school', 'local_hospital', 'movie',
                    'sports_esports', 'fitness_center', 'shopping_bag', 'fastfood',
                ],
                // Transfer icons
                'transfer' => [
                    'swap_horiz', 'compare_arrows', 'sync_alt', 'transform',
                ],
                // General icons
                'general' => [
                    'category', 'label', 'bookmark', 'star', 'favorite',
                    'help', 'info', 'settings', 'tune', 'build',
                ],
            ],
        ];
    }

    /**
     * Get default categories for new users
     */
    public function getDefaultCategories(): array
    {
        return [
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'color' => '#4CAF50', 'icon' => 'work'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#2196F3', 'icon' => 'computer'],
            ['name' => 'Investment', 'type' => 'income', 'color' => '#FF9800', 'icon' => 'trending_up'],
            ['name' => 'Other Income', 'type' => 'income', 'color' => '#9C27B0', 'icon' => 'account_balance_wallet'],

            // Expense categories
            ['name' => 'Food & Dining', 'type' => 'expense', 'color' => '#F44336', 'icon' => 'restaurant'],
            ['name' => 'Transportation', 'type' => 'expense', 'color' => '#607D8B', 'icon' => 'directions_car'],
            ['name' => 'Utilities', 'type' => 'expense', 'color' => '#795548', 'icon' => 'electrical_services'],
            ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#E91E63', 'icon' => 'movie'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#9C27B0', 'icon' => 'shopping_cart'],
            ['name' => 'Healthcare', 'type' => 'expense', 'color' => '#009688', 'icon' => 'local_hospital'],
            ['name' => 'Education', 'type' => 'expense', 'color' => '#3F51B5', 'icon' => 'school'],
            ['name' => 'Bills', 'type' => 'expense', 'color' => '#FF5722', 'icon' => 'receipt'],
            ['name' => 'Other Expense', 'type' => 'expense', 'color' => '#757575', 'icon' => 'category'],

            // Transfer category
            ['name' => 'Transfer', 'type' => 'transfer', 'color' => '#00BCD4', 'icon' => 'swap_horiz'],
        ];
    }

    /**
     * Create default categories for a user
     */
    public function createDefaultCategories(User $user): Collection
    {
        $defaultCategories = $this->getDefaultCategories();
        $categories = collect();

        foreach ($defaultCategories as $index => $categoryData) {
            $category = $user->categories()->create([
                'name' => $categoryData['name'],
                'type' => $categoryData['type'],
                'color' => $categoryData['color'],
                'icon' => $categoryData['icon'],
                'is_active' => true,
                'sort_order' => $index,
            ]);

            $categories->push($category);
        }

        return $categories;
    }

    /**
     * Get next sort order for user
     */
    private function getNextSortOrder(User $user): int
    {
        return $user->categories()->max('sort_order') + 1;
    }

    /**
     * Get default color for category type
     */
    private function getDefaultColor(string $type): string
    {
        $defaults = [
            'income' => '#4CAF50',
            'expense' => '#F44336',
            'transfer' => '#00BCD4',
        ];

        return $defaults[$type] ?? '#607D8B';
    }

    /**
     * Get default icon for category type
     */
    private function getDefaultIcon(string $type): string
    {
        $defaults = [
            'income' => 'trending_up',
            'expense' => 'shopping_cart',
            'transfer' => 'swap_horiz',
        ];

        return $defaults[$type] ?? 'category';
    }

    /**
     * Calculate trend between two values
     */
    private function calculateTrend(float $oldValue, float $newValue): string
    {
        if ($oldValue == 0 && $newValue == 0) {
            return 'stable';
        }

        if ($oldValue == 0) {
            return $newValue > 0 ? 'up' : 'stable';
        }

        $percentChange = (($newValue - $oldValue) / abs($oldValue)) * 100;

        if ($percentChange > 5) {
            return 'up';
        } elseif ($percentChange < -5) {
            return 'down';
        } else {
            return 'stable';
        }
    }

    /**
     * Calculate trend percentage
     */
    private function calculateTrendPercentage(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return (($newValue - $oldValue) / abs($oldValue)) * 100;
    }

    /**
     * Get date range for period
     */
    private function getDateRangeForPeriod(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate && $endDate) {
            return [
                'start' => $startDate,
                'end' => $endDate,
            ];
        }

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
