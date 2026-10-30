<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ChartOfAccount;

class CashBankAccountsSeeder extends Seeder
{
    public function run()
    {
        // Try to auto-create cash/bank accounts by mapping ChartOfAccount entries
        $matches = ChartOfAccount::query()
            ->where('type', 'Asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%Kas%')
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('name', 'like', '%Cash%');
            })
            ->get();

        if ($matches->isEmpty()) {
            // Fallback: insert a basic sample account; adjust coa_id manually if needed
            DB::table('cash_bank_accounts')->insert([
                'name' => 'Kas Kecil - Kantor Pusat',
                'bank_name' => 'N/A',
                'account_number' => null,
                'coa_id' => null,
                'notes' => 'Contoh rekening kas kecil untuk development',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        foreach ($matches as $coa) {
            // skip if there's already an entry mapped to this coa
            $exists = DB::table('cash_bank_accounts')->where('coa_id', $coa->id)->exists();
            if ($exists) {
                continue;
            }

            DB::table('cash_bank_accounts')->insert([
                'name' => $coa->name,
                'bank_name' => str_contains($coa->name, 'Bank') ? $coa->name : null,
                'account_number' => null,
                'coa_id' => $coa->id,
                'notes' => 'Auto-created from ChartOfAccount seeder',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
