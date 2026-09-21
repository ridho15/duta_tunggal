<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\AuditInventoryConsistency;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('asset:depreciate --force')
    ->monthlyOn((int) config('asset.depreciation.monthly_day', 1), '01:00')
    ->name('asset-monthly-depreciation')
    ->withoutOverlapping()
    ->runInBackground();

// Tandai quotation Approved yang melewati Valid Until sebagai Kedaluwarsa.
// Didaftarkan di sini (bukan app/Console/Kernel.php): bootstrap/app.php Laravel 12 tidak memakai
// Kernel::schedule(), hanya routes/console.php. Guard tanggal di server tetap berlaku bila job belum jalan.
Schedule::command('quotations:expire')
    ->dailyAt('00:10')
    ->name('quotations-expire')
    ->withoutOverlapping()
    ->runInBackground();

// T2.5 (D21): isi ulang reservasi SO backorder saat stok masuk (FIFO menurut waktu approve). Tidak berbuat apa-apa bila flag
// sales.stock.reserve_on_so_approve mati; aman dijalankan berulang (idempoten).
Schedule::command('sales:top-up-reservations')
    ->everyThirtyMinutes()
    ->name('sales-top-up-reservations')
    ->withoutOverlapping()
    ->runInBackground();

// Purchase return automation removed - now handled manually or through UI triggers
// Schedule::command('purchase:automate-return')
//     ->dailyAt('08:00')
//     ->name('purchase-return-automation')
//     ->withoutOverlapping()
//     ->runInBackground();

// Optionally, schedule periodic inventory audit (commented out by default)
// Schedule::command('audit:inventory-consistency')
//     ->dailyAt('03:00')
//     ->name('inventory-consistency-audit')
//     ->withoutOverlapping()
//     ->runInBackground();
