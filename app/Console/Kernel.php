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
        \App\Console\Commands\CheckOverdueInvoicesCommand::class,
        \App\Console\Commands\SyncProductInventoryCoaCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Tidak dipakai: bootstrap/app.php Laravel 12 hanya memuat jadwal dari routes/console.php.
        // Daftarkan jadwal baru di sana (invoices:check-overdue sudah dipindahkan ke routes/console.php).
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
