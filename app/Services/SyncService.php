<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\OfflineTransaction;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SyncService
{
    protected array $syncableModels = [
        'accounts' => \App\Models\Account::class,
        'categories' => \App\Models\Category::class,
        'transactions' => \App\Models\Transaction::class,
        'budgets' => \App\Models\Budget::class,
        'goals' => \App\Models\Goal::class,
        'bills' => \App\Models\Bill::class,
        'debts' => \App\Models\Debt::class,
    ];

    /**
     * Sync a single offline transaction
     *
     * @param User $user
     * @param string $clientId
     * @param array $data
     * @param string $createdAt
     * @param bool $force
     * @return array
     */
    public function syncTransaction(User $user, string $clientId, array $data, string $createdAt, bool $force = false): array
    {
        try {
            // Check if already synced
            $existing = OfflineTransaction::where('user_id', $user->id)
                ->where('client_id', $clientId)
                ->first();

            if ($existing && $existing->sync_status === 'synced' && !$force) {
                return [
                    'status' => 'already_synced',
                    'transaction_id' => $existing->transaction_data['server_id'] ?? null,
                ];
            }

            // Check for conflicts
            if (!$force) {
                $conflict = $this->detectConflict($user, $data, $createdAt);
                if ($conflict) {
                    // Store as conflict
                    OfflineTransaction::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'client_id' => $clientId,
                        ],
                        [
                            'transaction_data' => $data,
                            'sync_status' => 'conflict',
                            'sync_error' => json_encode($conflict),
                            'created_at_client' => $createdAt,
                        ]
                    );

                    return [
                        'status' => 'conflict',
                        'conflict' => $conflict,
                    ];
                }
            }

            // Create the transaction
            $transaction = $this->createTransaction($user, $data);

            // Mark as synced
            OfflineTransaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'client_id' => $clientId,
                ],
                [
                    'transaction_data' => array_merge($data, ['server_id' => $transaction->id]),
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                    'created_at_client' => $createdAt,
                ]
            );

            // Update account balance
            $this->updateAccountBalance($transaction);

            return [
                'status' => 'synced',
                'transaction_id' => $transaction->id,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to sync transaction {$clientId}: " . $e->getMessage());

            // Store error
            OfflineTransaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'client_id' => $clientId,
                ],
                [
                    'transaction_data' => $data,
                    'sync_status' => 'failed',
                    'sync_error' => $e->getMessage(),
                    'created_at_client' => $createdAt,
                ]
            );

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect conflicts with existing data
     *
     * @param User $user
     * @param array $data
     * @param string $createdAt
     * @return array|null
     */
    protected function detectConflict(User $user, array $data, string $createdAt): ?array
    {
        // Check for duplicate transaction
        $duplicate = Transaction::where('user_id', $user->id)
            ->where('account_id', $data['account_id'])
            ->where('amount', $data['amount'])
            ->where('date', $data['date'])
            ->where('description', 'LIKE', '%' . substr($data['description'], 0, 20) . '%')
            ->first();

        if ($duplicate) {
            // Check if it's a real conflict or just a duplicate
            $clientTime = Carbon::parse($createdAt);
            $serverTime = $duplicate->created_at;

            // If times are very close (within 5 minutes), consider it a duplicate
            if (abs($clientTime->diffInMinutes($serverTime)) < 5) {
                return null; // Not a conflict, just a duplicate
            }

            return [
                'type' => 'duplicate_transaction',
                'server_data' => [
                    'id' => $duplicate->id,
                    'amount' => $duplicate->amount,
                    'date' => $duplicate->date,
                    'description' => $duplicate->description,
                    'created_at' => $duplicate->created_at->toISOString(),
                ],
                'client_data' => $data,
            ];
        }

        // Check for account balance conflict
        $account = $user->accounts()->find($data['account_id']);
        if ($account) {
            $expectedBalance = $this->calculateExpectedBalance($account, $data);

            // If balance difference is significant (> $100), flag as conflict
            if (abs($account->balance - $expectedBalance) > 100) {
                return [
                    'type' => 'balance_mismatch',
                    'account_id' => $account->id,
                    'server_balance' => $account->balance,
                    'expected_balance' => $expectedBalance,
                    'difference' => abs($account->balance - $expectedBalance),
                ];
            }
        }

        return null;
    }

    /**
     * Create transaction from sync data
     *
     * @param User $user
     * @param array $data
     * @return Transaction
     */
    protected function createTransaction(User $user, array $data): Transaction
    {
        return $user->transactions()->create([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'date' => $data['date'],
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'is_cleared' => $data['is_cleared'] ?? true,
            'cleared_at' => $data['is_cleared'] ? now() : null,
            'location' => $data['location'] ?? null,
            'attachment' => $data['attachment'] ?? null,
            'tags' => $data['tags'] ?? [],
            'metadata' => [
                'synced_from_offline' => true,
                'client_created_at' => $data['created_at'] ?? null,
                'device_id' => $data['device_id'] ?? null,
            ],
        ]);
    }

    /**
     * Update account balance after transaction
     *
     * @param Transaction $transaction
     * @return void
     */
    protected function updateAccountBalance(Transaction $transaction): void
    {
        $account = $transaction->account;

        if ($transaction->type === 'income') {
            $account->increment('balance', $transaction->amount);
        } else {
            $account->decrement('balance', $transaction->amount);
        }

        // Log balance change
        DB::table('account_balance_history')->insert([
            'account_id' => $account->id,
            'balance' => $account->balance,
            'date' => now()->format('Y-m-d'),
            'change_type' => 'transaction',
            'change_amount' => $transaction->type === 'income' ? $transaction->amount : -$transaction->amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Calculate expected balance
     *
     * @param \App\Models\Account $account
     * @param array $transactionData
     * @return float
     */
    protected function calculateExpectedBalance($account, array $transactionData): float
    {
        $balance = $account->balance;

        if ($transactionData['type'] === 'income') {
            $balance += $transactionData['amount'];
        } else {
            $balance -= $transactionData['amount'];
        }

        return $balance;
    }

    /**
     * Resolve sync conflict
     *
     * @param OfflineTransaction $conflict
     * @param string $action
     * @param array|null $mergedData
     * @return array
     */
    public function resolveConflict(OfflineTransaction $conflict, string $action, ?array $mergedData = null): array
    {
        try {
            switch ($action) {
                case 'use_client':
                    // Use client version
                    $transaction = $this->createTransaction($conflict->user, $conflict->transaction_data);

                    $conflict->update([
                        'sync_status' => 'synced',
                        'synced_at' => now(),
                        'sync_error' => null,
                        'transaction_data' => array_merge(
                            $conflict->transaction_data,
                            ['server_id' => $transaction->id]
                        ),
                    ]);

                    return [
                        'status' => 'resolved',
                        'transaction_id' => $transaction->id,
                    ];

                case 'use_server':
                    // Keep server version, mark client as synced
                    $conflictData = json_decode($conflict->sync_error, true);
                    $serverId = $conflictData['server_data']['id'] ?? null;

                    $conflict->update([
                        'sync_status' => 'synced',
                        'synced_at' => now(),
                        'sync_error' => null,
                        'transaction_data' => array_merge(
                            $conflict->transaction_data,
                            ['server_id' => $serverId]
                        ),
                    ]);

                    return [
                        'status' => 'resolved',
                        'transaction_id' => $serverId,
                    ];

                case 'merge':
                    // Merge data
                    if (!$mergedData) {
                        return [
                            'status' => 'error',
                            'error' => 'Merged data required for merge action',
                        ];
                    }

                    $transaction = $this->createTransaction($conflict->user, $mergedData);

                    $conflict->update([
                        'sync_status' => 'synced',
                        'synced_at' => now(),
                        'sync_error' => null,
                        'transaction_data' => array_merge(
                            $mergedData,
                            ['server_id' => $transaction->id]
                        ),
                    ]);

                    return [
                        'status' => 'resolved',
                        'transaction_id' => $transaction->id,
                    ];

                default:
                    return [
                        'status' => 'error',
                        'error' => 'Invalid action',
                    ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to resolve conflict {$conflict->id}: " . $e->getMessage());

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get deleted items since last sync
     *
     * @param User $user
     * @param Carbon|null $lastSync
     * @return array
     */
    public function getDeletedItems(User $user, ?Carbon $lastSync): array
    {
        if (!$lastSync) {
            return [];
        }

        $deleted = [];

        // Check soft deleted items for each model
        foreach ($this->syncableModels as $entity => $modelClass) {
            if (method_exists($modelClass, 'withTrashed')) {
                $items = $modelClass::withTrashed()
                    ->where('user_id', $user->id)
                    ->where('deleted_at', '>', $lastSync)
                    ->select('id', 'deleted_at')
                    ->get();

                if ($items->count() > 0) {
                    $deleted[$entity] = $items->pluck('id')->toArray();
                }
            }
        }

        return $deleted;
    }

    /**
     * Perform incremental sync
     *
     * @param User $user
     * @param Carbon $lastSync
     * @param array $options
     * @return array
     */
    public function incrementalSync(User $user, Carbon $lastSync, array $options = []): array
    {
        $changes = [];
        $include = $options['include'] ?? array_keys($this->syncableModels);

        foreach ($include as $entity) {
            if (!isset($this->syncableModels[$entity])) {
                continue;
            }

            $modelClass = $this->syncableModels[$entity];

            // Get updated items
            $updated = $modelClass::where('user_id', $user->id)
                ->where('updated_at', '>', $lastSync)
                ->get();

            if ($updated->count() > 0) {
                $changes[$entity] = [
                    'updated' => $updated->toArray(),
                    'count' => $updated->count(),
                ];
            }
        }

        // Get deleted items
        $deleted = $this->getDeletedItems($user, $lastSync);
        if (!empty($deleted)) {
            $changes['deleted'] = $deleted;
        }

        return $changes;
    }

    /**
     * Merge offline data with server data
     *
     * @param User $user
     * @param array $offlineData
     * @param array $serverData
     * @return array
     */
    public function mergeData(User $user, array $offlineData, array $serverData): array
    {
        $merged = [];
        $conflicts = [];

        foreach ($offlineData as $entity => $items) {
            $merged[$entity] = [];

            foreach ($items as $item) {
                $serverItem = $this->findServerItem($serverData[$entity] ?? [], $item);

                if ($serverItem) {
                    // Check for conflict
                    if ($this->hasConflict($item, $serverItem)) {
                        $conflicts[] = [
                            'entity' => $entity,
                            'client' => $item,
                            'server' => $serverItem,
                        ];
                    } else {
                        // Use server version (assuming server is authoritative)
                        $merged[$entity][] = $serverItem;
                    }
                } else {
                    // New item from client
                    $merged[$entity][] = $item;
                }
            }

            // Add server-only items
            foreach ($serverData[$entity] ?? [] as $serverItem) {
                if (!$this->findOfflineItem($items, $serverItem)) {
                    $merged[$entity][] = $serverItem;
                }
            }
        }

        return [
            'merged' => $merged,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Find server item matching offline item
     *
     * @param array $serverItems
     * @param array $offlineItem
     * @return array|null
     */
    protected function findServerItem(array $serverItems, array $offlineItem): ?array
    {
        foreach ($serverItems as $serverItem) {
            if (($serverItem['client_id'] ?? null) === ($offlineItem['client_id'] ?? null)) {
                return $serverItem;
            }

            if (($serverItem['id'] ?? null) === ($offlineItem['server_id'] ?? null)) {
                return $serverItem;
            }
        }

        return null;
    }

    /**
     * Find offline item matching server item
     *
     * @param array $offlineItems
     * @param array $serverItem
     * @return array|null
     */
    protected function findOfflineItem(array $offlineItems, array $serverItem): ?array
    {
        foreach ($offlineItems as $offlineItem) {
            if (($offlineItem['client_id'] ?? null) === ($serverItem['client_id'] ?? null)) {
                return $offlineItem;
            }

            if (($offlineItem['server_id'] ?? null) === ($serverItem['id'] ?? null)) {
                return $offlineItem;
            }
        }

        return null;
    }

    /**
     * Check if items have conflict
     *
     * @param array $item1
     * @param array $item2
     * @return bool
     */
    protected function hasConflict(array $item1, array $item2): bool
    {
        // Compare timestamps
        $time1 = Carbon::parse($item1['updated_at'] ?? $item1['created_at']);
        $time2 = Carbon::parse($item2['updated_at'] ?? $item2['created_at']);

        // If updated within 1 minute, no conflict
        if (abs($time1->diffInSeconds($time2)) < 60) {
            return false;
        }

        // Compare key fields
        $keyFields = ['amount', 'description', 'date', 'status'];

        foreach ($keyFields as $field) {
            if (isset($item1[$field]) && isset($item2[$field])) {
                if ($item1[$field] !== $item2[$field]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clean up old sync data
     *
     * @param int $daysToKeep
     * @return int
     */
    public function cleanupOldSyncData(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        $deleted = 0;

        // Clean old sync logs
        $deleted += SyncLog::where('created_at', '<', $cutoffDate)
            ->where('status', 'completed')
            ->delete();

        // Clean synced offline transactions
        $deleted += OfflineTransaction::where('synced_at', '<', $cutoffDate)
            ->where('sync_status', 'synced')
            ->delete();

        // Clean old balance history
        $deleted += DB::table('account_balance_history')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        Log::info("Cleaned up {$deleted} old sync records");

        return $deleted;
    }

    /**
     * Get sync statistics for user
     *
     * @param User $user
     * @return array
     */
    public function getSyncStatistics(User $user): array
    {
        $stats = [
            'total_syncs' => SyncLog::where('user_id', $user->id)->count(),
            'successful_syncs' => SyncLog::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),
            'failed_syncs' => SyncLog::where('user_id', $user->id)
                ->where('status', 'failed')
                ->count(),
            'pending_items' => OfflineTransaction::where('user_id', $user->id)
                ->where('sync_status', 'pending')
                ->count(),
            'conflicts' => OfflineTransaction::where('user_id', $user->id)
                ->where('sync_status', 'conflict')
                ->count(),
            'last_sync' => SyncLog::where('user_id', $user->id)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->value('completed_at'),
            'average_sync_time' => SyncLog::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_time')
                ->value('avg_time'),
        ];

        // Get sync frequency
        $recentSyncs = SyncLog::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        $stats['sync_frequency'] = $recentSyncs > 0 ? round(7 / $recentSyncs, 1) : null;

        return $stats;
    }
}
