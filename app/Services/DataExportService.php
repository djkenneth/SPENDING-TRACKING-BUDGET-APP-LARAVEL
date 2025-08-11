<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\IOFactory;
use League\Csv\Reader;
use League\Csv\Writer;
use Carbon\Carbon;

class DataExportService
{
    protected array $exportableEntities = [
        'accounts',
        'transactions',
        'categories',
        'budgets',
        'goals',
        'bills',
        'debts',
    ];

    /**
     * Export user data in specified format
     *
     * @param User $user
     * @param string $format
     * @param array $include
     * @param array|null $dateRange
     * @return array
     */
    public function exportUserData(User $user, string $format, array $include = [], array|null $dateRange = null): array
    {
        try {
            // Validate included entities
            $include = array_intersect($include, $this->exportableEntities);

            if (empty($include)) {
                $include = ['transactions', 'accounts', 'categories'];
            }

            // Collect data
            $data = $this->collectDataForExport($user, $include, $dateRange);

            // Export based on format
            switch ($format) {
                case 'json':
                    return $this->exportAsJson($data, $user);

                case 'csv':
                    return $this->exportAsCsv($data, $user);

                case 'xlsx':
                    return $this->exportAsXlsx($data, $user);

                default:
                    throw new \Exception("Unsupported export format: {$format}");
            }
        } catch (\Exception $e) {
            Log::error("Export failed for user {$user->id}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Import user data from file
     *
     * @param User $user
     * @param UploadedFile $file
     * @param string $format
     * @param array $options
     * @return array
     */
    public function importUserData(User $user, UploadedFile $file, string $format, array $options = []): array
    {
        try {
            $mapping = $options['mapping'] ?? [];
            $skipDuplicates = $options['skip_duplicates'] ?? true;
            $dryRun = $options['dry_run'] ?? false;

            // Parse file based on format
            $data = match($format) {
                'json' => $this->parseJsonFile($file),
                'csv' => $this->parseCsvFile($file, $mapping),
                'xlsx' => $this->parseXlsxFile($file, $mapping),
                default => throw new \Exception("Unsupported import format: {$format}"),
            };

            // Validate data
            $validation = $this->validateImportData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validation['errors'],
                ];
            }

            // Dry run - return preview
            if ($dryRun) {
                return [
                    'success' => true,
                    'preview' => $this->generateImportPreview($data),
                ];
            }

            // Import data
            $result = $this->importData($user, $data, $skipDuplicates);

            return [
                'success' => true,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ];
        } catch (\Exception $e) {
            Log::error("Import failed for user {$user->id}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect data for export
     *
     * @param User $user
     * @param array $include
     * @param array|null $dateRange
     * @return array
     */
    protected function collectDataForExport(User $user, array $include, ?array $dateRange): array
    {
        $data = [];

        // Export accounts
        if (in_array('accounts', $include)) {
            $data['accounts'] = $user->accounts()
                ->select('name', 'type', 'balance', 'currency', 'bank_name', 'account_number', 'notes')
                ->get()
                ->toArray();
        }

        // Export categories
        if (in_array('categories', $include)) {
            $data['categories'] = $user->categories()
                ->with('parent:id,name')
                ->select('name', 'type', 'parent_id', 'color', 'icon', 'budget_amount')
                ->get()
                ->map(function ($category) {
                    $item = $category->toArray();
                    $item['parent_name'] = $category->parent ? $category->parent->name : null;
                    unset($item['parent'], $item['parent_id']);
                    return $item;
                })
                ->toArray();
        }

        // Export transactions
        if (in_array('transactions', $include)) {
            $query = $user->transactions()
                ->with(['account:id,name', 'category:id,name'])
                ->select('description', 'amount', 'type', 'date', 'account_id', 'category_id',
                        'notes', 'is_recurring', 'is_cleared', 'reference_number');

            if ($dateRange) {
                $query->whereBetween('date', [$dateRange['from'], $dateRange['to']]);
            }

            $data['transactions'] = $query->get()
                ->map(function ($transaction) {
                    $item = $transaction->toArray();
                    $item['account_name'] = $transaction->account ? $transaction->account->name : null;
                    $item['category_name'] = $transaction->category ? $transaction->category->name : null;
                    unset($item['account'], $item['category'], $item['account_id'], $item['category_id']);
                    return $item;
                })
                ->toArray();
        }

        // Export budgets
        if (in_array('budgets', $include)) {
            $query = $user->budgets()
                ->with('items.category:id,name')
                ->select('name', 'period', 'start_date', 'end_date', 'total_amount');

            if ($dateRange) {
                $query->where(function ($q) use ($dateRange) {
                    $q->whereBetween('start_date', [$dateRange['from'], $dateRange['to']])
                      ->orWhereBetween('end_date', [$dateRange['from'], $dateRange['to']]);
                });
            }

            $data['budgets'] = $query->get()
                ->map(function ($budget) {
                    $item = $budget->toArray();
                    $item['items'] = collect($budget->items)->map(function ($budgetItem) {
                        return [
                            'category' => $budgetItem->category ? $budgetItem->category->name : null,
                            'amount' => $budgetItem->amount,
                            'spent' => $budgetItem->spent,
                        ];
                    })->toArray();
                    return $item;
                })
                ->toArray();
        }

        // Export goals
        if (in_array('goals', $include)) {
            $data['goals'] = $user->goals()
                ->select('name', 'target_amount', 'current_amount', 'target_date', 'status', 'notes')
                ->get()
                ->toArray();
        }

        // Export bills
        if (in_array('bills', $include)) {
            $data['bills'] = $user->bills()
                ->with('category:id,name')
                ->select('name', 'amount', 'due_date', 'frequency', 'category_id', 'status', 'notes')
                ->get()
                ->map(function ($bill) {
                    $item = $bill->toArray();
                    $item['category_name'] = $bill->category ? $bill->category->name : null;
                    unset($item['category'], $item['category_id']);
                    return $item;
                })
                ->toArray();
        }

        // Export debts
        if (in_array('debts', $include)) {
            $data['debts'] = $user->debts()
                ->select('name', 'type', 'total_amount', 'remaining_amount', 'interest_rate',
                        'minimum_payment', 'due_date', 'status', 'notes')
                ->get()
                ->toArray();
        }

        return $data;
    }

    /**
     * Export data as JSON
     *
     * @param array $data
     * @param User $user
     * @return array
     */
    protected function exportAsJson(array $data, User $user): array
    {
        $filename = "export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . ".json";

        $jsonData = [
            'export_version' => '1.0',
            'exported_at' => now()->toISOString(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'data' => $data,
        ];

        return [
            'success' => true,
            'data' => $jsonData,
            'filename' => $filename,
        ];
    }

    /**
     * Export data as CSV
     *
     * @param array $data
     * @param User $user
     * @return array
     */
    protected function exportAsCsv(array $data, User $user): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $tempPath = storage_path("app/temp/export_{$user->id}_{$timestamp}");

        // Create temp directory
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        $files = [];

        foreach ($data as $entity => $records) {
            if (empty($records)) {
                continue;
            }

            $filename = "{$entity}.csv";
            $filepath = "{$tempPath}/{$filename}";

            $csv = Writer::createFromPath($filepath, 'w+');

            // Add headers
            if (count($records) > 0) {
                $csv->insertOne(array_keys($records[0]));
                $csv->insertAll($records);
            }

            $files[] = $filename;
        }

        // Create zip if multiple files
        if (count($files) > 1) {
            $zipFilename = "export_{$user->id}_{$timestamp}.zip";
            $zipPath = storage_path("app/temp/{$zipFilename}");

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                foreach ($files as $file) {
                    $zip->addFile("{$tempPath}/{$file}", $file);
                }
                $zip->close();
            }

            // Clean up temp files
            array_map('unlink', glob("{$tempPath}/*"));
            rmdir($tempPath);

            return [
                'success' => true,
                'path' => $zipPath,
                'filename' => $zipFilename,
            ];
        } else {
            // Single file
            $singleFile = "{$tempPath}/{$files[0]}";
            $finalPath = storage_path("app/temp/export_{$user->id}_{$timestamp}.csv");
            rename($singleFile, $finalPath);
            rmdir($tempPath);

            return [
                'success' => true,
                'path' => $finalPath,
                'filename' => "export_{$user->id}_{$timestamp}.csv",
            ];
        }
    }

    /**
     * Export data as XLSX
     *
     * @param array $data
     * @param User $user
     * @return array
     */
    protected function exportAsXlsx(array $data, User $user): array
    {
        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        foreach ($data as $entity => $records) {
            if (empty($records)) {
                continue;
            }

            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }

            $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
            $sheet->setTitle(ucfirst($entity));

            // Add headers
            if (count($records) > 0) {
                $headers = array_keys($records[0]);
                $sheet->fromArray($headers, null, 'A1');

                // Style headers
                $headerStyle = [
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ];
                $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

                // Add data
                $sheet->fromArray($records, null, 'A2');

                // Auto-size columns
                foreach (range('A', $sheet->getHighestColumn()) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            $sheetIndex++;
        }

        // Save file
        $filename = "export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . ".xlsx";
        $path = storage_path("app/temp/{$filename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return [
            'success' => true,
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * Parse JSON file
     *
     * @param UploadedFile $file
     * @return array
     */
    protected function parseJsonFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON file: ' . json_last_error_msg());
        }

        // Extract data from export format
        if (isset($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Parse CSV file
     *
     * @param UploadedFile $file
     * @param array $mapping
     * @return array
     */
    protected function parseCsvFile(UploadedFile $file, array $mapping = []): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            // Apply field mapping if provided
            if (!empty($mapping)) {
                $mappedRecord = [];
                foreach ($mapping as $sourceField => $targetField) {
                    if (isset($record[$sourceField])) {
                        $mappedRecord[$targetField] = $record[$sourceField];
                    }
                }
                $records[] = $mappedRecord;
            } else {
                $records[] = $record;
            }
        }

        // Determine entity type from filename or content
        $entityType = $this->detectEntityType($file->getClientOriginalName(), $records);

        return [$entityType => $records];
    }

    /**
     * Parse XLSX file
     *
     * @param UploadedFile $file
     * @param array $mapping
     * @return array
     */
    protected function parseXlsxFile(UploadedFile $file, array $mapping = []): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $data = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $sheetData = $sheet->toArray();

            if (empty($sheetData)) {
                continue;
            }

            // First row as headers
            $headers = array_shift($sheetData);
            $records = [];

            foreach ($sheetData as $row) {
                $record = array_combine($headers, $row);

                // Apply field mapping if provided
                if (!empty($mapping[$sheetName])) {
                    $mappedRecord = [];
                    foreach ($mapping[$sheetName] as $sourceField => $targetField) {
                        if (isset($record[$sourceField])) {
                            $mappedRecord[$targetField] = $record[$sourceField];
                        }
                    }
                    $records[] = $mappedRecord;
                } else {
                    $records[] = $record;
                }
            }

            $entityType = strtolower($sheetName);
            $data[$entityType] = $records;
        }

        return $data;
    }

    /**
     * Detect entity type from filename or content
     *
     * @param string $filename
     * @param array $records
     * @return string
     */
    protected function detectEntityType(string $filename, array $records): string
    {
        // Check filename
        foreach ($this->exportableEntities as $entity) {
            if (stripos($filename, $entity) !== false) {
                return $entity;
            }
        }

        // Check content structure
        if (!empty($records)) {
            $firstRecord = $records[0];
            $fields = array_keys($firstRecord);

            // Transaction detection
            if (array_intersect($fields, ['amount', 'date', 'description', 'type'])) {
                return 'transactions';
            }

            // Account detection
            if (array_intersect($fields, ['balance', 'account_number', 'bank_name'])) {
                return 'accounts';
            }

            // Category detection
            if (array_intersect($fields, ['budget_amount', 'parent_name'])) {
                return 'categories';
            }
        }

        return 'transactions'; // Default
    }

    /**
     * Validate import data
     *
     * @param array $data
     * @return array
     */
    protected function validateImportData(array $data): array
    {
        $errors = [];
        $valid = true;

        foreach ($data as $entity => $records) {
            if (!in_array($entity, $this->exportableEntities)) {
                $errors[] = "Unknown entity type: {$entity}";
                $valid = false;
                continue;
            }

            foreach ($records as $index => $record) {
                $validation = $this->validateRecord($entity, $record, $index);
                if (!$validation['valid']) {
                    $errors = array_merge($errors, $validation['errors']);
                    $valid = false;
                }
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Validate individual record
     *
     * @param string $entity
     * @param array $record
     * @param int $index
     * @return array
     */
    protected function validateRecord(string $entity, array $record, int $index): array
    {
        $errors = [];
        $valid = true;

        switch ($entity) {
            case 'transactions':
                if (!isset($record['amount']) || !is_numeric($record['amount'])) {
                    $errors[] = "Transaction {$index}: Invalid amount";
                    $valid = false;
                }
                if (!isset($record['date']) || !strtotime($record['date'])) {
                    $errors[] = "Transaction {$index}: Invalid date";
                    $valid = false;
                }
                if (!isset($record['type']) || !in_array($record['type'], ['income', 'expense'])) {
                    $errors[] = "Transaction {$index}: Invalid type";
                    $valid = false;
                }
                break;

            case 'accounts':
                if (!isset($record['name']) || empty($record['name'])) {
                    $errors[] = "Account {$index}: Name is required";
                    $valid = false;
                }
                if (!isset($record['type']) || empty($record['type'])) {
                    $errors[] = "Account {$index}: Type is required";
                    $valid = false;
                }
                break;

            case 'categories':
                if (!isset($record['name']) || empty($record['name'])) {
                    $errors[] = "Category {$index}: Name is required";
                    $valid = false;
                }
                if (!isset($record['type']) || !in_array($record['type'], ['income', 'expense'])) {
                    $errors[] = "Category {$index}: Invalid type";
                    $valid = false;
                }
                break;
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Generate import preview
     *
     * @param array $data
     * @return array
     */
    protected function generateImportPreview(array $data): array
    {
        $preview = [];

        foreach ($data as $entity => $records) {
            $preview[$entity] = [
                'total_count' => count($records),
                'sample' => array_slice($records, 0, 5),
                'fields' => !empty($records) ? array_keys($records[0]) : [],
            ];
        }

        return $preview;
    }

    /**
     * Import data into database
     *
     * @param User $user
     * @param array $data
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importData(User $user, array $data, bool $skipDuplicates = true): array
    {
        $result = [
            'imported' => [],
            'skipped' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            // Import in correct order to handle dependencies
            $importOrder = ['accounts', 'categories', 'transactions', 'budgets', 'goals', 'bills', 'debts'];

            foreach ($importOrder as $entity) {
                if (!isset($data[$entity])) {
                    continue;
                }

                $entityResult = $this->importEntity($user, $entity, $data[$entity], $skipDuplicates);

                $result['imported'][$entity] = $entityResult['imported'];
                $result['skipped'][$entity] = $entityResult['skipped'];

                if (!empty($entityResult['errors'])) {
                    $result['errors'][$entity] = $entityResult['errors'];
                }
            }

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();

            $result['errors'][] = $e->getMessage();
            throw $e;
        }
    }

    /**
     * Import specific entity type
     *
     * @param User $user
     * @param string $entity
     * @param array $records
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importEntity(User $user, string $entity, array $records, bool $skipDuplicates): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            try {
                $result = match($entity) {
                    'transactions' => $this->importTransaction($user, $record, $skipDuplicates),
                    'accounts' => $this->importAccount($user, $record, $skipDuplicates),
                    'categories' => $this->importCategory($user, $record, $skipDuplicates),
                    'budgets' => $this->importBudget($user, $record, $skipDuplicates),
                    'goals' => $this->importGoal($user, $record, $skipDuplicates),
                    'bills' => $this->importBill($user, $record, $skipDuplicates),
                    'debts' => $this->importDebt($user, $record, $skipDuplicates),
                    default => ['status' => 'skipped'],
                };

                if ($result['status'] === 'imported') {
                    $imported++;
                } elseif ($result['status'] === 'skipped') {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = "Record {$index}: " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import transaction
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importTransaction(User $user, array $record, bool $skipDuplicates): array
    {
        // Find or create account
        $account = null;
        if (isset($record['account_name'])) {
            $account = $user->accounts()->where('name', $record['account_name'])->first();
        }

        if (!$account) {
            $account = $user->accounts()->first();
        }

        // Find or create category
        $category = null;
        if (isset($record['category_name'])) {
            $category = $user->categories()
                ->where('name', $record['category_name'])
                ->where('type', $record['type'] ?? 'expense')
                ->first();

            if (!$category) {
                $category = $user->categories()->create([
                    'name' => $record['category_name'],
                    'type' => $record['type'] ?? 'expense',
                    'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
                    'icon' => 'folder',
                ]);
            }
        }

        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->transactions()
                ->where('amount', $record['amount'])
                ->where('date', $record['date'])
                ->where('description', $record['description'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Create transaction
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $category ? $category->id : null,
            'description' => $record['description'],
            'amount' => abs($record['amount']),
            'type' => $record['type'] ?? 'expense',
            'date' => Carbon::parse($record['date'])->format('Y-m-d'),
            'notes' => $record['notes'] ?? null,
            'reference_number' => $record['reference_number'] ?? null,
            'is_recurring' => $record['is_recurring'] ?? false,
            'is_cleared' => $record['is_cleared'] ?? true,
        ]);

        return ['status' => 'imported'];
    }

    /**
     * Import account
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importAccount(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->accounts()
                ->where('name', $record['name'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Create account
        $user->accounts()->create([
            'name' => $record['name'],
            'type' => $record['type'],
            'balance' => $record['balance'] ?? 0,
            'currency' => $record['currency'] ?? $user->currency,
            'bank_name' => $record['bank_name'] ?? null,
            'account_number' => $record['account_number'] ?? null,
            'notes' => $record['notes'] ?? null,
            'color' => $record['color'] ?? '#' . dechex(rand(0x000000, 0xFFFFFF)),
            'icon' => $record['icon'] ?? 'account_balance',
            'is_active' => true,
        ]);

        return ['status' => 'imported'];
    }

    /**
     * Import category
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importCategory(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->categories()
                ->where('name', $record['name'])
                ->where('type', $record['type'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Find parent category if specified
        $parentId = null;
        if (isset($record['parent_name']) && !empty($record['parent_name'])) {
            $parent = $user->categories()
                ->where('name', $record['parent_name'])
                ->where('type', $record['type'])
                ->first();

            if ($parent) {
                $parentId = $parent->id;
            }
        }

        // Create category
        $user->categories()->create([
            'name' => $record['name'],
            'type' => $record['type'],
            'parent_id' => $parentId,
            'color' => $record['color'] ?? '#' . dechex(rand(0x000000, 0xFFFFFF)),
            'icon' => $record['icon'] ?? 'folder',
            'budget_amount' => $record['budget_amount'] ?? null,
        ]);

        return ['status' => 'imported'];
    }

    /**
     * Import budget
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importBudget(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->budgets()
                ->where('name', $record['name'])
                ->where('start_date', $record['start_date'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Create budget
        $budget = $user->budgets()->create([
            'name' => $record['name'],
            'period' => $record['period'] ?? 'monthly',
            'start_date' => Carbon::parse($record['start_date'])->format('Y-m-d'),
            'end_date' => Carbon::parse($record['end_date'])->format('Y-m-d'),
            'total_amount' => $record['total_amount'] ?? 0,
            'is_active' => true,
        ]);

        // Create budget items if provided
        if (isset($record['items']) && is_array($record['items'])) {
            foreach ($record['items'] as $item) {
                $category = $user->categories()
                    ->where('name', $item['category'])
                    ->first();

                if ($category) {
                    $budget->items()->create([
                        'category_id' => $category->id,
                        'amount' => $item['amount'] ?? 0,
                        'spent' => 0,
                    ]);
                }
            }
        }

        return ['status' => 'imported'];
    }

    /**
     * Import goal
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importGoal(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->goals()
                ->where('name', $record['name'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Create goal
        $user->goals()->create([
            'name' => $record['name'],
            'target_amount' => $record['target_amount'],
            'current_amount' => $record['current_amount'] ?? 0,
            'target_date' => isset($record['target_date']) ? Carbon::parse($record['target_date'])->format('Y-m-d') : null,
            'status' => $record['status'] ?? 'active',
            'notes' => $record['notes'] ?? null,
            'color' => $record['color'] ?? '#' . dechex(rand(0x000000, 0xFFFFFF)),
            'icon' => $record['icon'] ?? 'flag',
        ]);

        return ['status' => 'imported'];
    }

    /**
     * Import bill
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importBill(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->bills()
                ->where('name', $record['name'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Find or create category
        $category = null;
        if (isset($record['category_name'])) {
            $category = $user->categories()
                ->where('name', $record['category_name'])
                ->where('type', 'expense')
                ->first();

            if (!$category) {
                $category = $user->categories()->create([
                    'name' => $record['category_name'],
                    'type' => 'expense',
                    'color' => '#' . dechex(rand(0x000000, 0xFFFFFF)),
                    'icon' => 'receipt',
                ]);
            }
        }

        // Create bill
        $user->bills()->create([
            'category_id' => $category ? $category->id : $user->categories()->where('type', 'expense')->first()->id,
            'name' => $record['name'],
            'amount' => $record['amount'],
            'due_date' => Carbon::parse($record['due_date'])->format('Y-m-d'),
            'frequency' => $record['frequency'] ?? 'monthly',
            'status' => $record['status'] ?? 'active',
            'notes' => $record['notes'] ?? null,
            'reminder_days' => $record['reminder_days'] ?? 3,
            'is_recurring' => true,
            'color' => $record['color'] ?? '#' . dechex(rand(0x000000, 0xFFFFFF)),
            'icon' => $record['icon'] ?? 'receipt',
        ]);

        return ['status' => 'imported'];
    }

    /**
     * Import debt
     *
     * @param User $user
     * @param array $record
     * @param bool $skipDuplicates
     * @return array
     */
    protected function importDebt(User $user, array $record, bool $skipDuplicates): array
    {
        // Check for duplicate
        if ($skipDuplicates) {
            $exists = $user->debts()
                ->where('name', $record['name'])
                ->exists();

            if ($exists) {
                return ['status' => 'skipped'];
            }
        }

        // Create debt
        $user->debts()->create([
            'name' => $record['name'],
            'type' => $record['type'] ?? 'loan',
            'total_amount' => $record['total_amount'],
            'remaining_amount' => $record['remaining_amount'] ?? $record['total_amount'],
            'interest_rate' => $record['interest_rate'] ?? 0,
            'minimum_payment' => $record['minimum_payment'] ?? 0,
            'due_date' => isset($record['due_date']) ? Carbon::parse($record['due_date'])->format('Y-m-d') : null,
            'status' => $record['status'] ?? 'active',
            'notes' => $record['notes'] ?? null,
        ]);

        return ['status' => 'imported'];
    }
}
