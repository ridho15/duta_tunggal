<?php

use App\Models\ChartOfAccount;
use App\Services\BalanceSheetService;
use Database\Seeders\FinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('finance seeder produces a balanced balance sheet', function () {
    $this->seed(FinanceSeeder::class);

    $balanceSheet = app(BalanceSheetService::class)->generate();

    expect($balanceSheet['is_balanced'])->toBeTrue()
        ->and(abs($balanceSheet['difference']))->toBeLessThan(0.01)
        ->and(ChartOfAccount::where('code', '3199')->exists())->toBeTrue();
});