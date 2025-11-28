<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\AuditInventoryConsistency;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule purchase return automation
Schedule::command('purchase:automate-return')
    ->dailyAt('08:00')
    ->name('purchase-return-automation')
    ->withoutOverlapping()
    ->runInBackground();

// Optionally, schedule periodic inventory audit (commented out by default)
// Schedule::command('audit:inventory-consistency')
//     ->dailyAt('03:00')
//     ->name('inventory-consistency-audit')
//     ->withoutOverlapping()
//     ->runInBackground();
