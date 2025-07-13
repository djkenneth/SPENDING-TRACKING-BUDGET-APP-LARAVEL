<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
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
     * Get the accounts for the user.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get the categories for the user.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the budgets for the user.
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get the financial goals for the user.
     */
    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    /**
     * Get the debts for the user.
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * Get the bills for the user.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user settings for the user.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get the sync logs for the user.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Get the offline transactions for the user.
     */
    public function offlineTransactions(): HasMany
    {
        return $this->hasMany(OfflineTransaction::class);
    }

    /**
     * Get the recurring transactions for the user.
     */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * Get the budget periods for the user.
     */
    public function budgetPeriods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class);
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
     * Get the user's current month budget utilization.
     */
    public function getCurrentBudgetUtilization(): array
    {
        $currentMonth = now()->format('Y-m');
        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->endOfMonth()->format('Y-m-d');

        $budgets = $this->budgets()
            ->whereMonth('start_date', '<=', now())
            ->whereMonth('end_date', '>=', now())
            ->with('category')
            ->get();

        $utilization = [];
        foreach ($budgets as $budget) {
            $spent = $this->transactions()
                ->where('category_id', $budget->category_id)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $utilization[] = [
                'budget_id' => $budget->id,
                'category' => $budget->category->name,
                'budgeted' => $budget->amount,
                'spent' => $spent,
                'remaining' => $budget->amount - $spent,
                'percentage' => $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0,
            ];
        }

        return $utilization;
    }

    /**
     * Get the user's active financial goals progress.
     */
    public function getActiveGoalsProgress(): array
    {
        $goals = $this->financialGoals()
            ->where('status', 'active')
            ->get();

        $progress = [];
        foreach ($goals as $goal) {
            $progress[] = [
                'goal_id' => $goal->id,
                'name' => $goal->name,
                'target_amount' => $goal->target_amount,
                'current_amount' => $goal->current_amount,
                'progress_percentage' => $goal->target_amount > 0 ? ($goal->current_amount / $goal->target_amount) * 100 : 0,
                'target_date' => $goal->target_date,
                'days_remaining' => now()->diffInDays($goal->target_date, false),
            ];
        }

        return $progress;
    }

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
     * Get user's preferred currency symbol.
     */
    public function getCurrencySymbol(): string
    {
        $currencies = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'PHP' => '₱',
            'SGD' => 'S$',
            'MYR' => 'RM',
            'THB' => '฿',
            'IDR' => 'Rp',
            'VND' => '₫',
        ];

        return $currencies[$this->currency] ?? $this->currency;
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
            ['value' => $value]
        );
    }
}
