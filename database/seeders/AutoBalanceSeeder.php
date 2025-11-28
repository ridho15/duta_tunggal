<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

class AutoBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $asOf = now()->endOfDay();

        $calc = function (ChartOfAccount $coa) use ($asOf) {
            $q = JournalEntry::where('coa_id', $coa->id)->where('date', '<=', $asOf);
            $debit = (float) (clone $q)->sum('debit');
            $credit = (float) (clone $q)->sum('credit');
            $opening = (float) ($coa->opening_balance ?? 0);
            return match ($coa->type) {
                'Asset', 'Expense' => $opening + $debit - $credit,
                'Contra Asset', 'Liability', 'Equity', 'Revenue' => $opening - $debit + $credit,
                default => $opening + $debit - $credit,
            };
        };

        $assets = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->get();
        $liabs = ChartOfAccount::where('type', 'Liability')->get();
        $eqs = ChartOfAccount::where('type', 'Equity')->get();

        $assetTotal = $assets->sum(fn($c) => $calc($c));
        $liabTotal = $liabs->sum(fn($c) => $calc($c));
        $equityAccountsTotal = $eqs->sum(fn($c) => $calc($c));

        // retained earnings from journals
        $revIds = ChartOfAccount::where('type', 'Revenue')->pluck('id');
        $expIds = ChartOfAccount::where('type', 'Expense')->pluck('id');
        $revQ = JournalEntry::whereIn('coa_id', $revIds)->where('date', '<=', $asOf);
        $expQ = JournalEntry::whereIn('coa_id', $expIds)->where('date', '<=', $asOf);
        $retained = (float) (clone $revQ)->sum('credit') - (float) (clone $revQ)->sum('debit')
                  - ((float) (clone $expQ)->sum('debit') - (float) (clone $expQ)->sum('credit'));

        $current = 0.0; // not separated currently

        $rhs = $liabTotal + $equityAccountsTotal + $retained + $current;
        $diff = $assetTotal - $rhs; // want to make this 0 by tweaking 3199 opening

        if (abs($diff) < 0.01) {
            $this->command?->info('Already balanced; no change.');
            return;
        }

        $offset = ChartOfAccount::firstOrCreate(
            ['code' => '3199'],
            ['name' => 'SALDO AWAL PENYEIMBANG', 'type' => 'Equity', 'is_active' => true]
        );
        $before = (float) ($offset->opening_balance ?? 0);
        // For Equity: formula is opening - debit + credit. Adding to opening increases RHS directly.
        $offset->opening_balance = $before + $diff;
        $offset->save();

        $this->command?->info('Adjusted 3199 opening from '.number_format($before,2).' to '.number_format($offset->opening_balance,2).' (delta '.number_format($diff,2).')');
    }
}
