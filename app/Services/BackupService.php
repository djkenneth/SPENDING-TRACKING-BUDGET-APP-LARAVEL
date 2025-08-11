<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\UploadedFile;
use ZipArchive;
use Carbon\Carbon;

class BackupService
{
    protected string $backupPath = 'backups';
    protected int $backupRetentionDays = 30;

    /**
     * Create a backup for user
     *
     * @param User $user
     * @param array $options
     * @return array
     */
    public function createBackup(User $user, array $options = []): array
    {
        try {
            $includeAttachments = $options['include_attachments'] ?? false;
            $encrypt = $options['encrypt'] ?? false;
            $password = $options['password'] ?? null;

            // Generate backup filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$user->id}_{$timestamp}.json";

            // Collect user data
            $backupData = $this->collectUserData($user, $includeAttachments);

            // Convert to JSON
            $jsonData = json_encode($backupData, JSON_PRETTY_PRINT);

            // Encrypt if requested
            if ($encrypt && $password) {
                $jsonData = $this->encryptData($jsonData, $password);
                $filename = str_replace('.json', '.encrypted', $filename);
            }

            // Create zip if including attachments
            if ($includeAttachments && !empty($backupData['attachments'])) {
                $filename = str_replace(['.json', '.encrypted'], '.zip', $filename);
                $filePath = $this->createZipBackup($user, $jsonData, $backupData['attachments'], $filename);
            } else {
                // Store backup file
                $filePath = "{$this->backupPath}/{$user->id}/{$filename}";
                Storage::put($filePath, $jsonData);
            }

            // Generate temporary download URL (expires in 24 hours)
            $url = Storage::temporaryUrl($filePath, now()->addHours(24));

            // Clean old backups
            $this->cleanOldBackups($user);

            // Log backup creation
            $this->logBackupActivity($user, 'created', $filename);

            return [
                'success' => true,
                'filename' => $filename,
                'size' => Storage::size($filePath),
                'url' => $url,
                'expires_at' => now()->addHours(24)->toISOString(),
                'checksum' => md5($jsonData),
            ];
        } catch (\Exception $e) {
            Log::error("Backup creation failed for user {$user->id}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore backup for user
     *
     * @param User $user
     * @param UploadedFile $file
     * @param array $options
     * @return array
     */
    public function restoreBackup(User $user, UploadedFile $file, array $options = []): array
    {
        try {
            $password = $options['password'] ?? null;
            $mergeData = $options['merge_data'] ?? false;

            // Read file content
            $content = file_get_contents($file->getPathname());

            // Handle different file types
            $extension = $file->getClientOriginalExtension();

            if ($extension === 'zip') {
                $content = $this->extractZipBackup($file);
            } elseif ($extension === 'encrypted' || $this->isEncrypted($content)) {
                if (!$password) {
                    return [
                        'success' => false,
                        'error' => 'Password required for encrypted backup',
                    ];
                }
                $content = $this->decryptData($content, $password);
            }

            // Parse JSON data
            $backupData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid backup file format',
                ];
            }

            // Validate backup structure
            if (!$this->validateBackupStructure($backupData)) {
                return [
                    'success' => false,
                    'error' => 'Invalid backup structure',
                ];
            }

            // Verify backup belongs to user (optional)
            if (($backupData['user']['email'] ?? '') !== $user->email) {
                Log::warning("User {$user->id} attempting to restore backup from different account");
            }

            // Start restoration
            $result = $this->restoreUserData($user, $backupData, $mergeData);

            // Log restore activity
            $this->logBackupActivity($user, 'restored', $file->getClientOriginalName());

            return [
                'success' => true,
                'restored_items' => $result['restored'],
                'skipped_items' => $result['skipped'],
                'errors' => $result['errors'],
            ];
        } catch (\Exception $e) {
            Log::error("Backup restoration failed for user {$user->id}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect all user data for backup
     *
     * @param User $user
     * @param bool $includeAttachments
     * @return array
     */
    protected function collectUserData(User $user, bool $includeAttachments = false): array
    {
        $data = [
            'backup_version' => '1.0',
            'created_at' => now()->toISOString(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'currency' => $user->currency,
                'timezone' => $user->timezone,
                'language' => $user->language,
                'preferences' => $user->preferences,
            ],
            'accounts' => $user->accounts()->get()->toArray(),
            'categories' => $user->categories()->get()->toArray(),
            'transactions' => $user->transactions()
                ->with(['account', 'category', 'tags'])
                ->get()
                ->toArray(),
            'budgets' => $user->budgets()
                ->with('items')
                ->get()
                ->toArray(),
            'goals' => $user->goals()
                ->with('contributions')
                ->get()
                ->toArray(),
            'bills' => $user->bills()->get()->toArray(),
            'debts' => $user->debts()
                ->with('payments')
                ->get()
                ->toArray(),
            'recurring_transactions' => $user->recurringTransactions()->get()->toArray(),
            'tags' => $user->tags()->get()->toArray(),
            'settings' => $user->settings()->get()->toArray(),
        ];

        // Include attachments if requested
        if ($includeAttachments) {
            $data['attachments'] = $this->collectAttachments($user);
        }

        return $data;
    }

    /**
     * Restore user data from backup
     *
     * @param User $user
     * @param array $backupData
     * @param bool $merge
     * @return array
     */
    protected function restoreUserData(User $user, array $backupData, bool $merge = false): array
    {
        $result = [
            'restored' => [],
            'skipped' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            // If not merging, clear existing data
            if (!$merge) {
                $this->clearUserData($user);
            }

            // Restore user preferences
            if (isset($backupData['user']['preferences'])) {
                $user->update(['preferences' => $backupData['user']['preferences']]);
                $result['restored'][] = 'user_preferences';
            }

            // Restore accounts
            if (isset($backupData['accounts'])) {
                $accountMap = [];
                foreach ($backupData['accounts'] as $accountData) {
                    $oldId = $accountData['id'];
                    unset($accountData['id'], $accountData['created_at'], $accountData['updated_at']);

                    $account = $user->accounts()->create($accountData);
                    $accountMap[$oldId] = $account->id;
                }
                $result['restored'][] = 'accounts (' . count($backupData['accounts']) . ')';
            }

            // Restore categories
            if (isset($backupData['categories'])) {
                $categoryMap = [];
                foreach ($backupData['categories'] as $categoryData) {
                    $oldId = $categoryData['id'];
                    unset($categoryData['id'], $categoryData['created_at'], $categoryData['updated_at']);

                    // Check for parent category
                    if (isset($categoryData['parent_id']) && isset($categoryMap[$categoryData['parent_id']])) {
                        $categoryData['parent_id'] = $categoryMap[$categoryData['parent_id']];
                    } else {
                        $categoryData['parent_id'] = null;
                    }

                    $category = $user->categories()->create($categoryData);
                    $categoryMap[$oldId] = $category->id;
                }
                $result['restored'][] = 'categories (' . count($backupData['categories']) . ')';
            }

            // Restore transactions
            if (isset($backupData['transactions'])) {
                foreach ($backupData['transactions'] as $transactionData) {
                    unset($transactionData['id'], $transactionData['created_at'], $transactionData['updated_at']);

                    // Map account and category IDs
                    if (isset($accountMap[$transactionData['account_id']])) {
                        $transactionData['account_id'] = $accountMap[$transactionData['account_id']];
                    }
                    if (isset($categoryMap[$transactionData['category_id']])) {
                        $transactionData['category_id'] = $categoryMap[$transactionData['category_id']];
                    }

                    // Remove nested relationships
                    unset($transactionData['account'], $transactionData['category'], $transactionData['tags']);

                    $user->transactions()->create($transactionData);
                }
                $result['restored'][] = 'transactions (' . count($backupData['transactions']) . ')';
            }

            // Restore budgets
            if (isset($backupData['budgets'])) {
                foreach ($backupData['budgets'] as $budgetData) {
                    $items = $budgetData['items'] ?? [];
                    unset($budgetData['id'], $budgetData['items'], $budgetData['created_at'], $budgetData['updated_at']);

                    $budget = $user->budgets()->create($budgetData);

                    // Restore budget items
                    foreach ($items as $itemData) {
                        unset($itemData['id'], $itemData['budget_id'], $itemData['created_at'], $itemData['updated_at']);

                        if (isset($categoryMap[$itemData['category_id']])) {
                            $itemData['category_id'] = $categoryMap[$itemData['category_id']];
                            $budget->items()->create($itemData);
                        }
                    }
                }
                $result['restored'][] = 'budgets (' . count($backupData['budgets']) . ')';
            }

            // Restore goals
            if (isset($backupData['goals'])) {
                foreach ($backupData['goals'] as $goalData) {
                    $contributions = $goalData['contributions'] ?? [];
                    unset($goalData['id'], $goalData['contributions'], $goalData['created_at'], $goalData['updated_at']);

                    $goal = $user->goals()->create($goalData);

                    // Restore goal contributions
                    foreach ($contributions as $contributionData) {
                        unset($contributionData['id'], $contributionData['goal_id'], $contributionData['created_at'], $contributionData['updated_at']);
                        $goal->contributions()->create($contributionData);
                    }
                }
                $result['restored'][] = 'goals (' . count($backupData['goals']) . ')';
            }

            // Restore bills
            if (isset($backupData['bills'])) {
                foreach ($backupData['bills'] as $billData) {
                    unset($billData['id'], $billData['created_at'], $billData['updated_at']);

                    if (isset($categoryMap[$billData['category_id']])) {
                        $billData['category_id'] = $categoryMap[$billData['category_id']];
                        $user->bills()->create($billData);
                    }
                }
                $result['restored'][] = 'bills (' . count($backupData['bills']) . ')';
            }

            // Restore settings
            if (isset($backupData['settings'])) {
                foreach ($backupData['settings'] as $settingData) {
                    $user->settings()->updateOrCreate(
                        ['key' => $settingData['key']],
                        ['value' => $settingData['value']]
                    );
                }
                $result['restored'][] = 'settings (' . count($backupData['settings']) . ')';
            }

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();

            $result['errors'][] = $e->getMessage();
            Log::error("Data restoration failed: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Clear all user data (for clean restore)
     *
     * @param User $user
     * @return void
     */
    protected function clearUserData(User $user): void
    {
        // Order matters due to foreign key constraints
        $user->transactions()->delete();
        $user->bills()->delete();
        $user->goals()->delete();
        $user->budgets()->delete();
        $user->debts()->delete();
        $user->recurringTransactions()->delete();
        $user->categories()->delete();
        $user->accounts()->delete();
        $user->tags()->delete();
        $user->settings()->delete();
    }

    /**
     * Encrypt data
     *
     * @param string $data
     * @param string $password
     * @return string
     */
    protected function encryptData(string $data, string $password): string
    {
        $method = 'AES-256-CBC';
        $key = hash('sha256', $password);
        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);

        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt data
     *
     * @param string $data
     * @param string $password
     * @return string
     */
    protected function decryptData(string $data, string $password): string
    {
        $method = 'AES-256-CBC';
        $key = hash('sha256', $password);

        $data = base64_decode($data);
        list($iv, $encrypted) = explode('::', $data, 2);

        $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception('Invalid password or corrupted data');
        }

        return $decrypted;
    }

    /**
     * Check if content is encrypted
     *
     * @param string $content
     * @return bool
     */
    protected function isEncrypted(string $content): bool
    {
        // Check if content is base64 encoded and contains encryption marker
        if (base64_encode(base64_decode($content, true)) === $content) {
            $decoded = base64_decode($content);
            return strpos($decoded, '::') !== false;
        }

        return false;
    }

    /**
     * Validate backup structure
     *
     * @param array $data
     * @return bool
     */
    protected function validateBackupStructure(array $data): bool
    {
        $requiredKeys = ['backup_version', 'created_at', 'user'];

        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create zip backup with attachments
     *
     * @param User $user
     * @param string $jsonData
     * @param array $attachments
     * @param string $filename
     * @return string
     */
    protected function createZipBackup(User $user, string $jsonData, array $attachments, string $filename): string
    {
        $zip = new ZipArchive();
        $tempPath = storage_path('app/temp/' . $filename);

        if ($zip->open($tempPath, ZipArchive::CREATE) === true) {
            // Add JSON data
            $zip->addFromString('backup.json', $jsonData);

            // Add attachments
            foreach ($attachments as $attachment) {
                if (Storage::exists($attachment['path'])) {
                    $zip->addFromString(
                        'attachments/' . $attachment['filename'],
                        Storage::get($attachment['path'])
                    );
                }
            }

            $zip->close();

            // Move to permanent location
            $finalPath = "{$this->backupPath}/{$user->id}/{$filename}";
            Storage::put($finalPath, file_get_contents($tempPath));

            // Clean temp file
            unlink($tempPath);

            return $finalPath;
        }

        throw new \Exception('Failed to create zip backup');
    }

    /**
     * Extract zip backup
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function extractZipBackup(UploadedFile $file): string
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getPathname()) === true) {
            $content = $zip->getFromName('backup.json');
            $zip->close();

            if ($content === false) {
                throw new \Exception('Backup file not found in zip archive');
            }

            return $content;
        }

        throw new \Exception('Failed to open zip file');
    }

    /**
     * Collect user attachments
     *
     * @param User $user
     * @return array
     */
    protected function collectAttachments(User $user): array
    {
        $attachments = [];

        // Collect transaction attachments
        foreach ($user->transactions()->whereNotNull('attachment')->get() as $transaction) {
            if ($transaction->attachment && Storage::exists($transaction->attachment)) {
                $attachments[] = [
                    'type' => 'transaction',
                    'id' => $transaction->id,
                    'path' => $transaction->attachment,
                    'filename' => basename($transaction->attachment),
                ];
            }
        }

        return $attachments;
    }

    /**
     * Clean old backups
     *
     * @param User $user
     * @return void
     */
    protected function cleanOldBackups(User $user): void
    {
        $cutoffDate = now()->subDays($this->backupRetentionDays);
        $userBackupPath = "{$this->backupPath}/{$user->id}";

        if (Storage::exists($userBackupPath)) {
            $files = Storage::files($userBackupPath);

            foreach ($files as $file) {
                if (Storage::lastModified($file) < $cutoffDate->timestamp) {
                    Storage::delete($file);
                    Log::info("Deleted old backup: {$file}");
                }
            }
        }
    }

    /**
     * Log backup activity
     *
     * @param User $user
     * @param string $action
     * @param string $filename
     * @return void
     */
    protected function logBackupActivity(User $user, string $action, string $filename): void
    {
        Log::info("Backup {$action} for user {$user->id}: {$filename}");

        // Optionally store in database
        DB::table('backup_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'filename' => $filename,
            'created_at' => now(),
        ]);
    }
}
