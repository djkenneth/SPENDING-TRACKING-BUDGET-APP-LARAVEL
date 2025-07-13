<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'transfer_account_id',
        'description',
        'amount',
        'type',
        'date',
        'notes',
        'tags',
        'reference_number',
        'location',
        'attachments',
        'is_recurring',
        'recurring_type',
        'recurring_interval',
        'recurring_end_date',
        'is_cleared',
        'cleared_at',
        'sync_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'tags' => 'array',
            'attachments' => 'array',
            'is_recurring' => 'boolean',
            'recurring_interval' => 'integer',
            'recurring_end_date' => 'date',
            'is_cleared' => 'boolean',
            'cleared_at' => 'datetime',
            'synced_at' => 'datetime',
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

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }
}
