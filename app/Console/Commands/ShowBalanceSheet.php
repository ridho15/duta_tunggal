<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BalanceSheetService;

class ShowBalanceSheet extends Command
{
    protected $signature = 'ledger:show-balance-sheet';
    protected $description = 'Generate balance sheet snapshot and show totals';

    public function handle()
    {
        $service = new BalanceSheetService();
        $snapshot = $service->generate();

        $this->info('As of: ' . ($snapshot['as_of'] ?? now()->toDateString()));
        $this->info('Total Assets: ' . number_format($snapshot['total_assets'], 2));
        $this->info('Total Liabilities & Equity: ' . number_format($snapshot['total_liabilities_and_equity'], 2));
        $this->info('Is Balanced: ' . ($snapshot['is_balanced'] ? 'YES' : 'NO'));
        $this->info('Difference: ' . number_format($snapshot['difference'], 2));

        return 0;
    }
}
