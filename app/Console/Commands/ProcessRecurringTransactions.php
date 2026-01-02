<?php

namespace App\Console\Commands;

use App\Services\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:process-recurring
                            {--dry-run : Run without creating transactions}
                            {--user= : Process only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due recurring transactions and create actual transactions';

    /**
     * Execute the console command.
     */
    public function handle(TransactionService $transactionService): int
    {
        $this->info('Starting to process recurring transactions...');
        $startTime = now();

        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user');

        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode. No transactions will be created.');
        }

        try {
            if ($isDryRun) {
                $dueTransactions = $transactionService->getDueRecurringTransactions($userId);

                $this->info("Found {$dueTransactions->count()} recurring transactions due for processing:");

                foreach ($dueTransactions as $recurring) {
                    $this->line(sprintf(
                        '  - [ID: %d] %s: %s %.2f (%s) - Next: %s',
                        $recurring->id,
                        $recurring->name,
                        $recurring->type,
                        $recurring->amount,
                        $recurring->frequency,
                        $recurring->next_occurrence->format('Y-m-d')
                    ));
                }

                return Command::SUCCESS;
            }

            // Process the recurring transactions
            $result = $transactionService->processDueRecurringTransactions($userId);

            $processedCount = $result['processed'];
            $errors = $result['errors'];

            // Log results
            $duration = $startTime->diffInSeconds(now());

            $this->info("Processing completed in {$duration} seconds.");
            $this->info("Successfully processed: {$processedCount} transactions");

            if (count($errors) > 0) {
                $this->warn("Errors encountered: " . count($errors));

                foreach ($errors as $error) {
                    $this->error("  - Recurring ID {$error['recurring_id']}: {$error['error']}");

                    Log::error('Recurring transaction processing failed', [
                        'recurring_id' => $error['recurring_id'],
                        'error' => $error['error'],
                    ]);
                }
            }

            // Log summary
            Log::info('Recurring transactions processed', [
                'processed_count' => $processedCount,
                'error_count' => count($errors),
                'duration_seconds' => $duration,
            ]);

            return count($errors) > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");

            Log::error('Recurring transaction processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
