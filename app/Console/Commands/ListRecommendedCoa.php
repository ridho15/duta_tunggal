<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChartOfAccount;

class ListRecommendedCoa extends Command
{
    protected $signature = 'ledger:list-coa';
    protected $description = 'Show recommended COA mappings (inventory, AP, VAT, cash/bank)';

    public function handle()
    {
        $codes = [
            '1140.01' => 'Inventory (Persediaan Barang Dagangan)',
            '1170.06' => 'PPN Masukan (Input VAT)',
            '2110' => 'Hutang Dagang (Accounts Payable)',
            '1111' => 'Kas',
            '1112.01' => 'Bank BCA (example bank)',
            '1112.03' => 'BANK PANIN (IDR) - 2197',
            '1112.04' => 'BANK PANIN (IDR) - 2297',
        ];

        $rows = [];
        foreach ($codes as $code => $desc) {
            $coa = ChartOfAccount::where('code', $code)->first();
            $rows[] = [
                'code' => $code,
                'id' => $coa ? $coa->id : 'MISSING',
                'name' => $coa ? $coa->name : $desc,
                'type' => $coa ? $coa->type : 'Unknown',
            ];
        }

        $this->table(['Code','ID','Name','Type'], $rows);
        $this->info("Recommended mapping summary:\n- Inventory -> 1140.01\n- Input VAT -> 1170.06\n- Accounts Payable -> 2110\n- Bank/Cash -> 1111 / 1112.*\n");

        return 0;
    }
}
