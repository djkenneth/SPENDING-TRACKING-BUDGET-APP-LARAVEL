<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'transaction_data',
        'sync_status',
        'sync_error',
        'created_at_client',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_data' => 'array',
            'created_at_client' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
