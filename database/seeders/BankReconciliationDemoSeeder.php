<?php

namespace Database\Seeders;

use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class BankReconciliationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('bank_reconciliations')) {
            Log::warning('[BankReconciliationDemoSeeder] Skipping seeder because table bank_reconciliations does not exist. Run migrations first.');
            return;
        }

        // Find a demo bank account created by CashBankDemoSeeder, or create one.
        $bankA = ChartOfAccount::firstOrCreate(
            ['code' => '1112.01'],
            ['name' => 'Bank A', 'type' => 'Asset', 'is_active' => true]
        );

        // Create a reconciliation batch for current month for Bank A
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        BankReconciliation::firstOrCreate(
            [
                'coa_id' => $bankA->id,
                'period_start' => $start,
                'period_end' => $end,
            ],
            [
                'statement_ending_balance' => 2000000, // contoh saldo rekening koran
                'book_balance' => 0,
                'difference' => 0,
                'reference' => 'SEED-RECON',
                'notes' => 'Demo rekonsiliasi bulan berjalan',
                'status' => 'open',
            ]
        );
    }
}
