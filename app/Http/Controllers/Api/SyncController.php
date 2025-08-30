<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\OfflineTransaction;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SyncController extends Controller
{
    protected SyncService $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Get sync status for the user
     *
     * @OA\Get(
     *     path="/api/sync/status",
     *     summary="Get sync status",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="last_sync", type="string", format="date-time"),
     *                 @OA\Property(property="pending_changes", type="integer"),
     *                 @OA\Property(property="sync_enabled", type="boolean"),
     *                 @OA\Property(property="sync_frequency", type="string", example="auto"),
     *                 @OA\Property(property="conflicts", type="integer"),
     *                 @OA\Property(
     *                     property="devices",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="device_id", type="string"),
     *                         @OA\Property(property="device_name", type="string"),
     *                         @OA\Property(property="last_sync", type="string", format="date-time"),
     *                         @OA\Property(property="status", type="string", enum={"synced", "pending", "error"})
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get last sync log
            $lastSync = SyncLog::where('user_id', $user->id)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            // Get pending offline transactions
            $pendingTransactions = OfflineTransaction::where('user_id', $user->id)
                ->where('sync_status', 'pending')
                ->count();

            // Get sync conflicts
            $conflicts = OfflineTransaction::where('user_id', $user->id)
                ->where('sync_status', 'conflict')
                ->count();

            // Check if sync is currently in progress
            $syncInProgress = SyncLog::where('user_id', $user->id)
                ->where('status', 'started')
                ->exists();

            $status = [
                'is_synced' => $pendingTransactions === 0 && $conflicts === 0,
                'sync_in_progress' => $syncInProgress,
                'last_sync' => $lastSync ? [
                    'timestamp' => $lastSync->completed_at->toISOString(),
                    'type' => $lastSync->sync_type,
                    'duration' => $lastSync->started_at->diffInSeconds($lastSync->completed_at),
                    'items_synced' => $lastSync->sync_data['items_synced'] ?? 0,
                ] : null,
                'pending_items' => [
                    'transactions' => $pendingTransactions,
                    'conflicts' => $conflicts,
                    'total' => $pendingTransactions + $conflicts,
                ],
                'device_id' => $request->header('X-Device-ID'),
                'online' => true,
            ];

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get sync status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync offline transactions
     *
     * @OA\Post(
     *     path="/api/sync/transactions",
     *     summary="Sync offline transactions",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transactions", "device_id", "last_sync"},
     *             @OA\Property(
     *                 property="transactions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="local_id", type="string"),
     *                     @OA\Property(property="server_id", type="integer", nullable=true),
     *                     @OA\Property(property="action", type="string", enum={"create", "update", "delete"}),
     *                     @OA\Property(property="data", type="object"),
     *                     @OA\Property(property="timestamp", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="last_sync", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transactions synced successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="synced", type="integer"),
     *                 @OA\Property(property="conflicts", type="integer"),
     *                 @OA\Property(property="errors", type="integer"),
     *                 @OA\Property(
     *                     property="mapping",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="local_id", type="string"),
     *                         @OA\Property(property="server_id", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(property="server_changes", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function syncTransactions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'transactions' => 'required|array',
                'transactions.*.client_id' => 'required|string',
                'transactions.*.data' => 'required|array',
                'transactions.*.data.account_id' => 'required|exists:accounts,id',
                'transactions.*.data.category_id' => 'required|exists:categories,id',
                'transactions.*.data.amount' => 'required|numeric|min:0',
                'transactions.*.data.type' => 'required|in:income,expense',
                'transactions.*.data.date' => 'required|date',
                'transactions.*.data.description' => 'required|string|max:255',
                'transactions.*.created_at' => 'required|date',
                'device_id' => 'required|string',
                'force' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $transactions = $request->input('transactions');
            $deviceId = $request->input('device_id');
            $force = $request->input('force', false);

            // Start sync log
            $syncLog = SyncLog::create([
                'user_id' => $user->id,
                'sync_type' => 'incremental',
                'status' => 'started',
                'started_at' => now(),
                'sync_data' => [
                    'device_id' => $deviceId,
                    'transactions_count' => count($transactions),
                ],
            ]);

            $results = [
                'synced' => [],
                'conflicts' => [],
                'errors' => [],
            ];

            DB::beginTransaction();

            try {
                foreach ($transactions as $transaction) {
                    $result = $this->syncService->syncTransaction(
                        $user,
                        $transaction['client_id'],
                        $transaction['data'],
                        $transaction['created_at'],
                        $force
                    );

                    if ($result['status'] === 'synced') {
                        $results['synced'][] = [
                            'client_id' => $transaction['client_id'],
                            'server_id' => $result['transaction_id'],
                        ];
                    } elseif ($result['status'] === 'conflict') {
                        $results['conflicts'][] = [
                            'client_id' => $transaction['client_id'],
                            'conflict' => $result['conflict'],
                        ];
                    } else {
                        $results['errors'][] = [
                            'client_id' => $transaction['client_id'],
                            'error' => $result['error'],
                        ];
                    }
                }

                DB::commit();

                // Update sync log
                $syncLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'sync_data' => array_merge($syncLog->sync_data, [
                        'items_synced' => count($results['synced']),
                        'conflicts' => count($results['conflicts']),
                        'errors' => count($results['errors']),
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transactions synced',
                    'data' => $results,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();

                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync transactions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync transactions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Perform full data sync
     *
     * @OA\Post(
     *     path="/api/sync/full",
     *     summary="Full data sync",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id"},
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="last_sync", type="string", format="date-time"),
     *             @OA\Property(property="force", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Full sync completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="synced_entities",
     *                     type="object",
     *                     @OA\Property(property="transactions", type="integer"),
     *                     @OA\Property(property="accounts", type="integer"),
     *                     @OA\Property(property="categories", type="integer"),
     *                     @OA\Property(property="budgets", type="integer"),
     *                     @OA\Property(property="goals", type="integer")
     *                 ),
     *                 @OA\Property(property="conflicts_resolved", type="integer"),
     *                 @OA\Property(property="timestamp", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function fullSync(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'last_sync' => 'sometimes|date',
                'device_id' => 'required|string',
                'include' => 'sometimes|array',
                'include.*' => 'string|in:accounts,categories,budgets,goals,bills,transactions',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $lastSync = $request->input('last_sync');
            $deviceId = $request->input('device_id');
            $include = $request->input('include', [
                'accounts',
                'categories',
                'budgets',
                'goals',
                'bills',
                'transactions'
            ]);

            // Start sync log
            $syncLog = SyncLog::create([
                'user_id' => $user->id,
                'sync_type' => 'full',
                'status' => 'started',
                'started_at' => now(),
                'sync_data' => [
                    'device_id' => $deviceId,
                    'include' => $include,
                ],
            ]);

            try {
                $data = [];
                $lastSyncDate = $lastSync ? Carbon::parse($lastSync) : null;

                // Sync accounts
                if (in_array('accounts', $include)) {
                    $query = $user->accounts();
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    }
                    $data['accounts'] = $query->get();
                }

                // Sync categories
                if (in_array('categories', $include)) {
                    $query = $user->categories();
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    }
                    $data['categories'] = $query->get();
                }

                // Sync budgets
                if (in_array('budgets', $include)) {
                    $query = $user->budgets()->with('items');
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    }
                    $data['budgets'] = $query->get();
                }

                // Sync goals
                if (in_array('goals', $include)) {
                    $query = $user->goals();
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    }
                    $data['goals'] = $query->get();
                }

                // Sync bills
                if (in_array('bills', $include)) {
                    $query = $user->bills();
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    }
                    $data['bills'] = $query->get();
                }

                // Sync transactions (limited to last 3 months if no last sync date)
                if (in_array('transactions', $include)) {
                    $query = $user->transactions()->with(['account', 'category']);
                    if ($lastSyncDate) {
                        $query->where('updated_at', '>', $lastSyncDate);
                    } else {
                        $query->where('date', '>=', now()->subMonths(3));
                    }
                    $data['transactions'] = $query->orderBy('date', 'desc')->get();
                }

                // Get deleted items
                $deletedItems = $this->syncService->getDeletedItems($user, $lastSyncDate);

                // Update sync log
                $syncLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'sync_data' => array_merge($syncLog->sync_data, [
                        'items_synced' => array_sum(array_map('count', $data)),
                        'deleted_items' => count($deletedItems),
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Full sync completed',
                    'data' => [
                        'sync_timestamp' => now()->toISOString(),
                        'data' => $data,
                        'deleted' => $deletedItems,
                        'meta' => [
                            'total_items' => array_sum(array_map('count', $data)),
                            'sync_id' => $syncLog->id,
                        ],
                    ],
                ]);
            } catch (\Exception $e) {
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to perform full sync: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform full sync',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync conflicts
     *
     * @OA\Get(
     *     path="/api/sync/conflicts",
     *     summary="Get sync conflicts",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="entity_type", type="string", example="transaction"),
     *                     @OA\Property(property="entity_id", type="integer"),
     *                     @OA\Property(property="local_data", type="object"),
     *                     @OA\Property(property="server_data", type="object"),
     *                     @OA\Property(property="conflict_type", type="string", enum={"update", "delete"}),
     *                     @OA\Property(property="detected_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getConflicts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $conflicts = OfflineTransaction::where('user_id', $user->id)
                ->where('sync_status', 'conflict')
                ->get()
                ->map(function ($conflict) {
                    return [
                        'id' => $conflict->id,
                        'client_id' => $conflict->client_id,
                        'transaction_data' => $conflict->transaction_data,
                        'sync_error' => $conflict->sync_error,
                        'created_at_client' => $conflict->created_at_client,
                        'created_at' => $conflict->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'conflicts' => $conflicts,
                    'count' => $conflicts->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get sync conflicts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync conflicts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve sync conflicts
     *
     * @OA\Post(
     *     path="/api/sync/resolve-conflicts",
     *     summary="Resolve sync conflicts",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"resolutions"},
     *             @OA\Property(
     *                 property="resolutions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="conflict_id", type="integer"),
     *                     @OA\Property(property="resolution", type="string", enum={"keep_local", "keep_server", "merge"})
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conflicts resolved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="resolved", type="integer"),
     *                 @OA\Property(property="failed", type="integer"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function resolveConflicts(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'resolutions' => 'required|array',
                'resolutions.*.conflict_id' => 'required|exists:offline_transactions,id',
                'resolutions.*.action' => 'required|in:use_client,use_server,merge',
                'resolutions.*.data' => 'required_if:action,merge|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $resolutions = $request->input('resolutions');
            $results = [];

            DB::beginTransaction();

            try {
                foreach ($resolutions as $resolution) {
                    $conflict = OfflineTransaction::where('id', $resolution['conflict_id'])
                        ->where('user_id', $user->id)
                        ->where('sync_status', 'conflict')
                        ->first();

                    if (!$conflict) {
                        $results[] = [
                            'conflict_id' => $resolution['conflict_id'],
                            'status' => 'not_found',
                        ];
                        continue;
                    }

                    $result = $this->syncService->resolveConflict(
                        $conflict,
                        $resolution['action'],
                        $resolution['data'] ?? null
                    );

                    $results[] = [
                        'conflict_id' => $resolution['conflict_id'],
                        'status' => $result['status'],
                        'transaction_id' => $result['transaction_id'] ?? null,
                    ];
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Conflicts resolved',
                    'data' => [
                        'resolved' => array_filter($results, fn($r) => $r['status'] === 'resolved'),
                        'failed' => array_filter($results, fn($r) => $r['status'] !== 'resolved'),
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to resolve conflicts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve conflicts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get last sync timestamp
     *
     * @OA\Get(
     *     path="/api/sync/last-sync",
     *     summary="Get last sync timestamp",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="device_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="last_sync", type="string", format="date-time"),
     *                 @OA\Property(property="device_id", type="string", nullable=true),
     *                 @OA\Property(property="sync_count", type="integer"),
     *                 @OA\Property(property="next_sync", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getLastSync(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $deviceId = $request->header('X-Device-ID');

            $lastSync = SyncLog::where('user_id', $user->id)
                ->where('status', 'completed');

            if ($deviceId) {
                $lastSync->where('sync_data->device_id', $deviceId);
            }

            $lastSync = $lastSync->orderBy('completed_at', 'desc')->first();

            if (!$lastSync) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'last_sync' => null,
                        'has_synced' => false,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'last_sync' => $lastSync->completed_at->toISOString(),
                    'has_synced' => true,
                    'sync_type' => $lastSync->sync_type,
                    'items_synced' => $lastSync->sync_data['items_synced'] ?? 0,
                    'duration' => $lastSync->started_at->diffInSeconds($lastSync->completed_at),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get last sync timestamp: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get last sync timestamp',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear sync data and reset sync state
     *
     * @OA\Delete(
     *     path="/api/sync/clear",
     *     summary="Clear sync data",
     *     tags={"Sync"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="device_id", type="string"),
     *             @OA\Property(property="clear_conflicts", type="boolean", default=true),
     *             @OA\Property(property="clear_history", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sync data cleared successfully")
     *         )
     *     )
     * )
     */
    public function clearSyncData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'confirm' => 'required|boolean|accepted',
                'device_id' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $deviceId = $request->input('device_id');

            DB::beginTransaction();

            try {
                // Clear offline transactions
                $query = OfflineTransaction::where('user_id', $user->id);
                if ($deviceId) {
                    // Assuming device_id is stored in transaction_data
                    $query->where('transaction_data->device_id', $deviceId);
                }
                $deletedTransactions = $query->delete();

                // Clear sync logs
                $query = SyncLog::where('user_id', $user->id);
                if ($deviceId) {
                    $query->where('sync_data->device_id', $deviceId);
                }
                $deletedLogs = $query->delete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Sync data cleared successfully',
                    'data' => [
                        'deleted_transactions' => $deletedTransactions,
                        'deleted_logs' => $deletedLogs,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear sync data: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear sync data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
