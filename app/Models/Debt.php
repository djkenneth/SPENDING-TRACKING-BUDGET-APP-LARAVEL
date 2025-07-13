<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'original_balance',
        'current_balance',
        'interest_rate',
        'minimum_payment',
        'due_date',
        'payment_frequency',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'minimum_payment' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }
}
