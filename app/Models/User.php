<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'date_of_birth',
        'currency',
        'timezone',
        'language',
        'preferences',
        'last_login_at',
        'last_login_ip',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'preferences' => 'array',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default preferences when creating a new user
        static::creating(function ($user) {
            if (empty($user->preferences)) {
                $user->preferences = config('user.default_preferences', []);
            }
        });

        // Clean up related files when deleting user
        static::deleting(function ($user) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
        });
    }

    // Relationships

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function offlineTransactions(): HasMany
    {
        return $this->hasMany(OfflineTransaction::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function budgetPeriods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class);
    }

    // Accessors & Mutators

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    /**
     * Get the user's full name with title.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name);
    }

    /**
     * Get the user's age.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    /**
     * Get the user's total net worth.
     */
    public function getNetWorthAttribute(): float
    {
        return $this->accounts()
            ->where('include_in_net_worth', true)
            ->where('is_active', true)
            ->sum('balance');
    }

    // Financial Methods

    /**
     * Get the user's total income for a specific period.
     */
    public function getTotalIncome(string $startDate, string $endDate): float
    {
        return $this->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Get the user's total expenses for a specific period.
     */
    public function getTotalExpenses(string $startDate, string $endDate): float
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Get the user's net income for a specific period.
     */
    public function getNetIncome(string $startDate, string $endDate): float
    {
        return $this->getTotalIncome($startDate, $endDate) - $this->getTotalExpenses($startDate, $endDate);
    }

    /**
     * Get the user's savings rate for a specific period.
     */
    public function getSavingsRate(string $startDate, string $endDate): float
    {
        $income = $this->getTotalIncome($startDate, $endDate);

        if ($income <= 0) {
            return 0;
        }

        $expenses = $this->getTotalExpenses($startDate, $endDate);
        return (($income - $expenses) / $income) * 100;
    }

    /**
     * Get the user's current month budget utilization.
     */
    public function getCurrentBudgetUtilization(): array
    {
        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->endOfMonth()->format('Y-m-d');

        $budgets = $this->budgets()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('is_active', true)
            ->with('category')
            ->get();

        return $budgets->map(function ($budget) use ($startDate, $endDate) {
            $spent = $this->transactions()
                ->where('category_id', $budget->category_id)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            return [
                'budget_id' => $budget->id,
                'category' => $budget->category->name,
                'category_color' => $budget->category->color,
                'budgeted' => $budget->amount,
                'spent' => $spent,
                'remaining' => $budget->amount - $spent,
                'percentage' => $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0,
                'status' => $this->getBudgetStatus($spent, $budget->amount, $budget->alert_threshold),
            ];
        })->toArray();
    }

    /**
     * Get budget status based on spending.
     */
    private function getBudgetStatus(float $spent, float $budgeted, float $alertThreshold): string
    {
        if ($budgeted <= 0) {
            return 'no_budget';
        }

        $percentage = ($spent / $budgeted) * 100;

        if ($percentage >= 100) {
            return 'exceeded';
        } elseif ($percentage >= $alertThreshold) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'on_track';
        } else {
            return 'safe';
        }
    }

    /**
     * Get the user's active financial goals progress.
     */
    public function getActiveGoalsProgress(): array
    {
        $goals = $this->financialGoals()
            ->where('status', 'active')
            ->get();

        return $goals->map(function ($goal) {
            $progressPercentage = $goal->target_amount > 0 ? ($goal->current_amount / $goal->target_amount) * 100 : 0;
            $daysRemaining = now()->diffInDays($goal->target_date, false);

            return [
                'goal_id' => $goal->id,
                'name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
                'remaining_amount' => $goal->target_amount - $goal->current_amount,
                'progress_percentage' => round($progressPercentage, 2),
                'target_date' => $goal->target_date,
                'days_remaining' => $daysRemaining,
                'status' => $this->getGoalStatus($progressPercentage, $daysRemaining),
                'color' => $goal->color,
                'icon' => $goal->icon,
            ];
        })->toArray();
    }

    /**
     * Get goal status based on progress and time remaining.
     */
    private function getGoalStatus(float $progressPercentage, int $daysRemaining): string
    {
        if ($progressPercentage >= 100) {
            return 'completed';
        } elseif ($daysRemaining < 0) {
            return 'overdue';
        } elseif ($daysRemaining <= 30) {
            return 'urgent';
        } elseif ($progressPercentage >= 75) {
            return 'on_track';
        } elseif ($progressPercentage >= 25) {
            return 'progress';
        } else {
            return 'behind';
        }
    }

    // Preference & Settings Methods

    /**
     * Get user's preferred currency symbol.
     */
    public function getCurrencySymbol(): string
    {
        $currencies = config('user.currencies', []);
        return $currencies[$this->currency]['symbol'] ?? $this->currency;
    }

    /**
     * Get user's preferred currency name.
     */
    public function getCurrencyName(): string
    {
        $currencies = config('user.currencies', []);
        return $currencies[$this->currency]['name'] ?? $this->currency;
    }

    /**
     * Get user's setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set user's setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );
    }

    /**
     * Get user's preference value.
     */
    public function getPreference(string $key, $default = null)
    {
        $preferences = $this->preferences ?? [];
        return $preferences[$key] ?? $default;
    }

    /**
     * Set user's preference value.
     */
    public function setPreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->update(['preferences' => $preferences]);
    }

    // Notification Methods

    /**
     * Check if user has unread notifications.
     */
    public function hasUnreadNotifications(): bool
    {
        return $this->notifications()->where('is_read', false)->exists();
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadNotificationsCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsAsRead(): void
    {
        $this->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Create a notification for the user.
     */
    public function createNotification(string $type, string $title, string $message, array $data = [], string $priority = 'normal'): void
    {
        $this->notifications()->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'channel' => 'app',
        ]);
    }

    // Security & Session Methods

    /**
     * Get active sessions count.
     */
    public function getActiveSessionsCount(): int
    {
        return $this->tokens()->count();
    }

    /**
     * Revoke all sessions except current.
     */
    public function revokeOtherSessions(int $currentTokenId): int
    {
        return $this->tokens()->where('id', '!=', $currentTokenId)->delete();
    }

    /**
     * Check if user is online (last activity within 15 minutes).
     */
    public function isOnline(): bool
    {
        if (!$this->last_login_at) {
            return false;
        }

        return $this->last_login_at->diffInMinutes(now()) <= 15;
    }

    /**
     * Update last activity.
     */
    public function updateLastActivity(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip()
        ]);
    }

    // Data Export Methods

    /**
     * Get exportable data structure.
     */
    public function getExportableData(): array
    {
        return [
            'profile' => [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'date_of_birth' => $this->date_of_birth,
                'currency' => $this->currency,
                'timezone' => $this->timezone,
                'language' => $this->language,
                'preferences' => $this->preferences,
                'created_at' => $this->created_at,
            ],
            'accounts' => $this->accounts()->get(['name', 'type', 'balance', 'currency', 'created_at']),
            'categories' => $this->categories()->get(['name', 'type', 'color', 'icon', 'created_at']),
            'transactions' => $this->transactions()->with(['account:id,name', 'category:id,name'])
                ->get(['description', 'amount', 'type', 'date', 'notes', 'account_id', 'category_id']),
            'budgets' => $this->budgets()->with('category:id,name')
                ->get(['name', 'amount', 'period', 'start_date', 'end_date']),
            'goals' => $this->financialGoals()->get(['name', 'target_amount', 'current_amount', 'target_date', 'status']),
            'debts' => $this->debts()->get(['name', 'type', 'original_balance', 'current_balance', 'interest_rate']),
            'bills' => $this->bills()->with('category:id,name')
                ->get(['name', 'amount', 'due_date', 'frequency']),
            'settings' => $this->settings()->pluck('value', 'key'),
            'export_metadata' => [
                'exported_at' => now()->toISOString(),
                'version' => '1.0',
                'total_records' => [
                    'accounts' => $this->accounts()->count(),
                    'categories' => $this->categories()->count(),
                    'transactions' => $this->transactions()->count(),
                    'budgets' => $this->budgets()->count(),
                    'goals' => $this->financialGoals()->count(),
                    'debts' => $this->debts()->count(),
                    'bills' => $this->bills()->count(),
                    'settings' => $this->settings()->count(),
                ]
            ]
        ];
    }

    // Validation & Business Logic Methods

    /**
     * Check if user can delete account.
     */
    public function canDeleteAccount(): bool
    {
        // Check if user has any active debts
        $activeDebts = $this->debts()->where('status', 'active')->exists();
        if ($activeDebts) {
            return false;
        }

        // Check if user has any pending bills
        $pendingBills = $this->bills()
            ->where('status', 'active')
            ->where('due_date', '>=', now())
            ->exists();

        return !$pendingBills;
    }

    /**
     * Get account deletion warnings.
     */
    public function getAccountDeletionWarnings(): array
    {
        $warnings = [];

        // Check active debts
        $activeDebtsCount = $this->debts()->where('status', 'active')->count();
        if ($activeDebtsCount > 0) {
            $warnings[] = "You have {$activeDebtsCount} active debt(s). Please pay off or close them before deleting your account.";
        }

        // Check pending bills
        $pendingBillsCount = $this->bills()
            ->where('status', 'active')
            ->where('due_date', '>=', now())
            ->count();

        if ($pendingBillsCount > 0) {
            $warnings[] = "You have {$pendingBillsCount} upcoming bill(s). Please review them before deleting your account.";
        }

        // Check recent transactions
        $recentTransactionsCount = $this->transactions()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentTransactionsCount > 50) {
            $warnings[] = "You have {$recentTransactionsCount} transactions from the last 30 days. Consider exporting your data before deletion.";
        }

        // Check account balances
        $totalBalance = $this->accounts()->where('is_active', true)->sum('balance');
        if ($totalBalance > 0) {
            $warnings[] = "You have a total balance of {$this->getCurrencySymbol()}" . number_format($totalBalance, 2) . " across your accounts.";
        }

        return $warnings;
    }

    /**
     * Prepare account for deletion.
     */
    public function prepareForDeletion(): array
    {
        $exportData = $this->getExportableData();

        // Create backup
        $backupPath = $this->createDataBackup($exportData);

        // Log deletion request
        $this->createNotification(
            'account_deletion',
            'Account Deletion Requested',
            'Your account deletion has been scheduled. You have 30 days to reactivate your account.',
            ['backup_path' => $backupPath],
            'high'
        );

        return [
            'backup_created' => true,
            'backup_path' => $backupPath,
            'deletion_date' => now()->addDays(30)->toDateString(),
            'can_reactivate_until' => now()->addDays(30)->toDateTimeString(),
        ];
    }

    /**
     * Create data backup before deletion.
     */
    private function createDataBackup(array $data): string
    {
        $fileName = 'user_' . $this->id . '_backup_' . now()->format('Y_m_d_H_i_s') . '.json';
        $backupPath = 'backups/' . $fileName;

        Storage::disk('local')->put($backupPath, json_encode($data, JSON_PRETTY_PRINT));

        return $backupPath;
    }

    /**
     * Get user statistics summary.
     */
    public function getStatisticsSummary(): array
    {
        $currentMonth = now();
        $startOfMonth = $currentMonth->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

        return [
            'profile' => [
                'member_since' => $this->created_at->format('Y-m-d'),
                'days_active' => $this->created_at->diffInDays(now()),
                'last_login' => $this->last_login_at?->format('Y-m-d H:i:s'),
                'is_online' => $this->isOnline(),
            ],
            'financial' => [
                'net_worth' => $this->net_worth,
                'currency' => $this->currency,
                'currency_symbol' => $this->getCurrencySymbol(),
                'current_month_income' => $this->getTotalIncome($startOfMonth, $endOfMonth),
                'current_month_expenses' => $this->getTotalExpenses($startOfMonth, $endOfMonth),
                'current_month_savings' => $this->getNetIncome($startOfMonth, $endOfMonth),
                'savings_rate' => $this->getSavingsRate($startOfMonth, $endOfMonth),
            ],
            'activity' => [
                'total_accounts' => $this->accounts()->count(),
                'active_accounts' => $this->accounts()->where('is_active', true)->count(),
                'total_categories' => $this->categories()->count(),
                'total_transactions' => $this->transactions()->count(),
                'transactions_this_month' => $this->transactions()
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->count(),
            ],
            'goals_and_budgets' => [
                'active_budgets' => $this->budgets()->where('is_active', true)->count(),
                'active_goals' => $this->financialGoals()->where('status', 'active')->count(),
                'completed_goals' => $this->financialGoals()->where('status', 'completed')->count(),
                'active_debts' => $this->debts()->where('status', 'active')->count(),
                'upcoming_bills' => $this->bills()
                    ->where('status', 'active')
                    ->where('due_date', '>=', now())
                    ->where('due_date', '<=', now()->addDays(7))
                    ->count(),
            ],
            'notifications' => [
                'total_notifications' => $this->notifications()->count(),
                'unread_notifications' => $this->getUnreadNotificationsCount(),
                'notifications_this_week' => $this->notifications()
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
            ]
        ];
    }
}
