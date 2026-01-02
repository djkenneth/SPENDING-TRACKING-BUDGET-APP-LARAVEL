<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here you may define all of the scheduled tasks for your application.
| These tasks will be run by the scheduler when the `schedule:run`
| command is invoked.
|
*/

// Process recurring transactions daily at 1:00 AM
Schedule::command('transactions:process-recurring')
    ->dailyAt('01:00')
    // ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Recurring transactions processed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Recurring transactions processing failed');
    });

// Alternative: Run every hour (for more frequent processing)
// Schedule::command('transactions:process-recurring')
//     ->hourly()
//     ->withoutOverlapping()
//     ->runInBackground();

// You can also add more scheduled tasks here:

// Check for overdue bills daily
// Schedule::command('bills:check-overdue')->dailyAt('08:00');

// Send budget alerts weekly
// Schedule::command('budgets:send-alerts')->weeklyOn(1, '09:00');

// Clean up old notifications monthly
// Schedule::command('notifications:cleanup')->monthly();
