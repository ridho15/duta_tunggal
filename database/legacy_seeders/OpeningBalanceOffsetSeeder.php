<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;

class OpeningBalanceOffsetSeeder extends Seeder
{
    public function run(): void
    {
        $coas = ChartOfAccount::all(['id','code','name','type','opening_balance']);
        $sum = 0.0;
        foreach ($coas as $coa) {
            $opening = (float) ($coa->opening_balance ?? 0);
            if ($opening == 0.0) continue;
            // Sign per normal balance, to produce a net that should be zero when balanced
            $sign = match ($coa->type) {
                'Asset', 'Expense' => +1,
                'Contra Asset', 'Liability', 'Equity', 'Revenue' => -1,
                default => +1,
            };
            $sum += $sign * $opening;
        }

        // If already balanced, nothing to do
        if (abs($sum) < 0.01) {
            $this->command?->info('Opening balances already balanced; no offset needed.');
            return;
        }

        // Create/update an Equity account to offset opening balances
        $offset = ChartOfAccount::firstOrCreate(
            ['code' => '3199'],
            [
                'name' => 'SALDO AWAL PENYEIMBANG',
                'type' => 'Equity',
                'is_active' => true,
            ]
        );

        $offset->opening_balance = $sum; // can be positive or negative
        $offset->save();

        $this->command?->info('Opening Balance Offset set on COA 3199 with opening_balance = ' . number_format($sum, 2));
    }
}
