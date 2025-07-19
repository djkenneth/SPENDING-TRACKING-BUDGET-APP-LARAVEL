<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'balance',
        'date',
        'change_type',
        'change_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * Get the account that owns the balance history record.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the formatted balance with currency symbol.
     */
    public function getFormattedBalanceAttribute(): string
    {
        $currency = $this->account->currency ?? 'USD';
        $currencies = config('user.currencies', []);
        $symbol = $currencies[$currency]['symbol'] ?? $currency;

        return $symbol . number_format($this->balance, 2);
    }

    /**
     * Get the formatted change amount with currency symbol.
     */
    public function getFormattedChangeAmountAttribute(): ?string
    {
        if ($this->change_amount === null) {
            return null;
        }

        $currency = $this->account->currency ?? 'USD';
        $currencies = config('user.currencies', []);
        $symbol = $currencies[$currency]['symbol'] ?? $currency;

        $prefix = $this->change_amount > 0 ? '+' : '';
        return $prefix . $symbol . number_format($this->change_amount, 2);
    }

    /**
     * Get the change type label.
     */
    public function getChangeTypeLabelAttribute(): string
    {
        $labels = [
            'initial' => 'Initial Balance',
            'transaction' => 'Transaction',
            'adjustment' => 'Manual Adjustment',
            'sync' => 'Balance Sync',
        ];

        return $labels[$this->change_type] ?? ucfirst($this->change_type);
    }
}
