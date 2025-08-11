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
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $settings = $user->settings()->pluck('value', 'key')->toArray();

            // Parse JSON values
            foreach ($settings as $key => $value) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $settings[$key] = $decoded;
                }
            }

            // Include user preferences
            $settings['preferences'] = $user->preferences ?? config('user.default_preferences');
            $settings['currency'] = $user->currency;
            $settings['timezone'] = $user->timezone;
            $settings['language'] = $user->language;

            return response()->json([
                'success' => true,
                'data' => $settings,
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
     * @param Request $request
     * @return JsonResponse
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
                    $user->update([$key => $value]);
                    $updated[$key] = $value;
                } else {
                    // Store in user_settings table
                    $user->setSetting($key, $value);
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
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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

            $user->update(['preferences' => $newPreferences]);

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
     * @param Request $request
     * @return JsonResponse
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

            $backup = $this->backupService->createBackup($user, $options);

            if ($backup['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'data' => [
                        'filename' => $backup['filename'],
                        'size' => $backup['size'],
                        'download_url' => $backup['url'],
                        'expires_at' => $backup['expires_at'],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup',
                'error' => $backup['error'] ?? 'Unknown error',
            ], 500);
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
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
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
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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
