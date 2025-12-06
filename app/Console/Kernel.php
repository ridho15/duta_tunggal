<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\TestVoucherApprove::class,
        \App\Console\Commands\PostInvoiceToLedger::class,
        \App\Console\Commands\ShowBalanceSheet::class,
        \App\Console\Commands\ShowJournalEntriesForInvoice::class,
        \App\Console\Commands\ShowJournalEntriesForPayment::class,
        \App\Console\Commands\ListRecommendedCoa::class,
        \App\Console\Commands\GenerateMonthlyDepreciation::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // No scheduled depreciation - manual calculation only
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}
