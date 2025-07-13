<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'name',
        'description',
        'amount',
        'type',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_occurrence',
        'is_active',
        'occurrences_count',
        'max_occurrences',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'interval' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_occurrence' => 'date',
            'is_active' => 'boolean',
            'occurrences_count' => 'integer',
            'max_occurrences' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
