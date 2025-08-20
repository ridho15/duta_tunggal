<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule purchase return automation
Schedule::command('purchase:automate-return')
    ->dailyAt('08:00')
    ->name('purchase-return-automation')
    ->withoutOverlapping()
    ->runInBackground();
