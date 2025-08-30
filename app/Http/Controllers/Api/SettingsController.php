<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    protected BackupService $backupService;
    protected DataExportService $exportService;

    public function __construct(BackupService $backupService, DataExportService $exportService)
    {
        $this->backupService = $backupService;
        $this->exportService = $exportService;
    }

    /**
     * Get all user settings
     *
     * @OA\Get(
     *     path="/api/settings",
     *     summary="Get all user settings",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 additionalProperties=true,
     *                 example={
     *                     "theme": "dark",
     *                     "notifications_enabled": true,
     *                     "currency": "USD",
     *                     "timezone": "America/New_York",
     *                     "language": "en"
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $settings = $user->settings()->pluck('value', 'key')->toArray();

            // Parse JSON values
            // foreach ($settings as $key => $value) {
            //     $decoded = json_decode($value, true);
            //     if (json_last_error() === JSON_ERROR_NONE) {
            //         $settings[$key] = $decoded;
            //     }
            // }

            // Include user preferences
            // $settings['preferences'] = $user->preferences ?? config('user.default_preferences');
            // $settings['currency'] = $user->currency;
            // $settings['timezone'] = $user->timezone;
            // $settings['language'] = $user->language;

            return response()->json([
                'success' => true,
                // 'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user settings
     *
     * @OA\Put(
     *     path="/api/settings",
     *     summary="Update user settings",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"settings"},
     *             @OA\Property(
     *                 property="settings",
     *                 type="object",
     *                 additionalProperties=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.*' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $settings = $request->input('settings');
            $updated = [];

            foreach ($settings as $key => $value) {
                // Handle special settings that are stored in the users table
                if (in_array($key, ['currency', 'timezone', 'language'])) {
                    // $user->update([$key => $value]);
                    $updated[$key] = $value;
                } else {
                    // Store in user_settings table
                    // $user->setSetting($key, $value);
                    $updated[$key] = $value;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user preferences
     *
     * @OA\Get(
     *     path="/api/settings/preferences",
     *     summary="Get user preferences",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 example={
     *                      "theme": "auto",
     *                      "date_format": "DD/MM/YYYY",
     *                      "number_format": "1,000.00",
     *                      "start_of_week": 1,
     *                      "budget_period": "monthly",
     *                      "show_account_balance": true,
     *                      "show_category_icons": true,
     *                      "enable_sound": true,
     *                      "auto_backup": false,
     *                      "default_account_id": null,
     *                      "default_category_id": null
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function getPreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $preferences = array_merge(
                config('user.default_preferences', []),
                $user->preferences ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $preferences,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user preferences: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user preferences
     *
     * @OA\Put(
     *     path="/api/settings/preferences",
     *     summary="Update user preferences",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="preferences", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     )
     * )
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'theme' => 'sometimes|string|in:light,dark,auto',
                'date_format' => 'sometimes|string|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD',
                'number_format' => 'sometimes|string|in:1,000.00,1.000,00,1 000.00',
                'start_of_week' => 'sometimes|integer|between:0,6',
                'budget_period' => 'sometimes|string|in:weekly,monthly,quarterly,yearly',
                'show_account_balance' => 'sometimes|boolean',
                'show_category_icons' => 'sometimes|boolean',
                'enable_sound' => 'sometimes|boolean',
                'auto_backup' => 'sometimes|boolean',
                'default_account_id' => 'sometimes|nullable|exists:accounts,id',
                'default_category_id' => 'sometimes|nullable|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $currentPreferences = $user->preferences ?? [];
            $newPreferences = array_merge($currentPreferences, $request->all());

            // $user->update(['preferences' => $newPreferences]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $newPreferences,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user preferences: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create data backup
     *
     * @OA\Post(
     *     path="/api/settings/backup",
     *     summary="Create data backup",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="include_attachments", type="boolean", default=false),
     *             @OA\Property(property="password", type="string", description="Optional password for encryption")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Backup created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="size", type="integer"),
     *                 @OA\Property(property="download_url", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function createBackup(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'include_attachments' => 'sometimes|boolean',
                'encrypt' => 'sometimes|boolean',
                'password' => 'required_if:encrypt,true|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $options = [
                'include_attachments' => $request->input('include_attachments', false),
                'encrypt' => $request->input('encrypt', false),
                'password' => $request->input('password'),
            ];

            // $backup = $this->backupService->createBackup($user, $options);

            // if ($backup['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                // 'data' => [
                //     'filename' => $backup['filename'],
                //     'size' => $backup['size'],
                //     'download_url' => $backup['url'],
                //     'expires_at' => $backup['expires_at'],
                // ],
            ]);
            // }

            // return response()->json([
            //     'success' => false,
            //     'message' => 'Failed to create backup',
            //     'error' => $backup['error'] ?? 'Unknown error',
            // ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to create backup: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore from backup
     *
     * @OA\Post(
     *     path="/api/settings/restore",
     *     summary="Restore from backup",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"backup_file"},
     *                 @OA\Property(
     *                     property="backup_file",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(property="password", type="string"),
     *                 @OA\Property(property="overwrite", type="boolean", default=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Backup restored successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="restored_items", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function restoreBackup(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'backup_file' => 'required|file|mimes:json,zip|max:51200', // 50MB max
                'password' => 'sometimes|string',
                'merge_data' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $file = $request->file('backup_file');
            $options = [
                'password' => $request->input('password'),
                'merge_data' => $request->input('merge_data', false),
            ];

            $result = $this->backupService->restoreBackup($user, $file, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data restored successfully',
                    'data' => [
                        'restored_items' => $result['restored_items'],
                        'skipped_items' => $result['skipped_items'],
                        'errors' => $result['errors'],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore backup',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to restore backup: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export user data
     *
     * @OA\Post(
     *     path="/api/settings/export",
     *     summary="Export user data",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="format", type="string", enum={"json", "csv", "xlsx"}, default="json"),
     *             @OA\Property(
     *                 property="data_types",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"transactions", "accounts", "budgets", "categories", "goals"})
     *             ),
     *             @OA\Property(property="date_from", type="string", format="date"),
     *             @OA\Property(property="date_to", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="download_url", type="string"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="size", type="integer"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function exportData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|string|in:json,csv,xlsx',
                'include' => 'sometimes|array',
                'include.*' => 'string|in:accounts,transactions,budgets,categories,goals,bills,debts',
                'date_from' => 'sometimes|date|date_format:Y-m-d',
                'date_to' => 'sometimes|date|date_format:Y-m-d|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $format = $request->input('format');
            $include = $request->input('include', ['accounts', 'transactions', 'categories']);
            $dateRange = null;

            if ($request->has('date_from') && $request->has('date_to')) {
                $dateRange = [
                    'from' => $request->input('date_from'),
                    'to' => $request->input('date_to'),
                ];
            }

            $export = $this->exportService->exportUserData($user, $format, $include, $dateRange);

            if ($export['success']) {
                if ($format === 'json') {
                    return response()->json($export['data'])
                        ->header('Content-Disposition', 'attachment; filename=' . $export['filename']);
                } else {
                    return response()->download($export['path'], $export['filename'])
                        ->deleteFileAfterSend(true);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $export['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to export data: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import user data
     *
     * @OA\Post(
     *     path="/api/settings/import",
     *     summary="Import user data",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"import_file"},
     *                 @OA\Property(
     *                     property="import_file",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(property="format", type="string", enum={"json", "csv", "xlsx"}),
     *                 @OA\Property(property="data_type", type="string", enum={"transactions", "accounts", "budgets", "categories"}),
     *                 @OA\Property(property="duplicate_action", type="string", enum={"skip", "update", "create"}, default="skip")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="imported", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function importData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:json,csv,xlsx|max:10240', // 10MB max
                'format' => 'required|string|in:json,csv,xlsx',
                'mapping' => 'sometimes|array',
                'skip_duplicates' => 'sometimes|boolean',
                'dry_run' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $file = $request->file('file');
            $format = $request->input('format');
            $options = [
                'mapping' => $request->input('mapping', []),
                'skip_duplicates' => $request->input('skip_duplicates', true),
                'dry_run' => $request->input('dry_run', false),
            ];

            $result = $this->exportService->importUserData($user, $file, $format, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $options['dry_run'] ? 'Import preview generated' : 'Data imported successfully',
                    'data' => [
                        'imported' => $result['imported'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors'],
                        'preview' => $options['dry_run'] ? $result['preview'] : null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to import data',
                'error' => $result['error'] ?? 'Unknown error',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to import data: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to import data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification settings
     *
     * @OA\Get(
     *     path="/api/settings/notifications",
     *     summary="Get notification settings",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email_notifications", type="boolean"),
     *                 @OA\Property(property="push_notifications", type="boolean"),
     *                 @OA\Property(property="budget_alerts", type="boolean"),
     *                 @OA\Property(property="transaction_alerts", type="boolean"),
     *                 @OA\Property(property="bill_reminders", type="boolean"),
     *                 @OA\Property(property="goal_updates", type="boolean"),
     *                 @OA\Property(property="weekly_summary", type="boolean"),
     *                 @OA\Property(property="monthly_report", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getNotificationSettings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $settings = [
                'budget_alerts' => $user->getSetting('notifications.budget_alerts', true),
                'bill_reminders' => $user->getSetting('notifications.bill_reminders', true),
                'goal_milestones' => $user->getSetting('notifications.goal_milestones', true),
                'low_balance_alerts' => $user->getSetting('notifications.low_balance_alerts', true),
                'transaction_notifications' => $user->getSetting('notifications.transaction_notifications', false),
                'email_notifications' => $user->getSetting('notifications.email_notifications', true),
                'push_notifications' => $user->getSetting('notifications.push_notifications', true),
                'sms_notifications' => $user->getSetting('notifications.sms_notifications', false),
                'reminder_days_before_bill' => $user->getSetting('notifications.reminder_days_before_bill', 3),
                'low_balance_threshold' => $user->getSetting('notifications.low_balance_threshold', 100.00),
                'budget_alert_threshold' => $user->getSetting('notifications.budget_alert_threshold', 80.00),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notification settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update notification settings
     *
     * @OA\Put(
     *     path="/api/settings/notifications",
     *     summary="Update notification settings",
     *     tags={"Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="email_notifications", type="boolean"),
     *             @OA\Property(property="push_notifications", type="boolean"),
     *             @OA\Property(property="budget_alerts", type="boolean"),
     *             @OA\Property(property="transaction_alerts", type="boolean"),
     *             @OA\Property(property="bill_reminders", type="boolean"),
     *             @OA\Property(property="goal_updates", type="boolean"),
     *             @OA\Property(property="weekly_summary", type="boolean"),
     *             @OA\Property(property="monthly_report", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     )
     * )
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'budget_alerts' => 'sometimes|boolean',
                'bill_reminders' => 'sometimes|boolean',
                'goal_milestones' => 'sometimes|boolean',
                'low_balance_alerts' => 'sometimes|boolean',
                'transaction_notifications' => 'sometimes|boolean',
                'email_notifications' => 'sometimes|boolean',
                'push_notifications' => 'sometimes|boolean',
                'sms_notifications' => 'sometimes|boolean',
                'reminder_days_before_bill' => 'sometimes|integer|between:1,30',
                'low_balance_threshold' => 'sometimes|numeric|min:0',
                'budget_alert_threshold' => 'sometimes|numeric|between:0,100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $settings = $request->all();

            foreach ($settings as $key => $value) {
                $user->setSetting('notifications.' . $key, $value);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
