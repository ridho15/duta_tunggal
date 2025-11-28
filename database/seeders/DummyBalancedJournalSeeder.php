<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

class DummyBalancedJournalSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure essential COAs exist
        $cash = ChartOfAccount::firstOrCreate(
            ['code' => '1111.01'],
            ['name' => 'KAS BESAR', 'type' => 'Asset', 'is_active' => true]
        );
        $equity = ChartOfAccount::firstOrCreate(
            ['code' => '3100'],
            ['name' => 'MODAL DISETOR', 'type' => 'Equity', 'is_active' => true]
        );
        $revenue = ChartOfAccount::firstOrCreate(
            ['code' => '4100'],
            ['name' => 'PENDAPATAN PENJUALAN', 'type' => 'Revenue', 'is_active' => true]
        );
        $expense = ChartOfAccount::firstOrCreate(
            ['code' => '5100'],
            ['name' => 'HARGA POKOK PENJUALAN', 'type' => 'Expense', 'is_active' => true]
        );

        $today = now()->toDateString();

        // 1) Inject opening via equity: Debit Cash, Credit Equity (balanced)
        JournalEntry::create([
            'coa_id' => $cash->id,
            'date' => $today,
            'reference' => 'OPEN-001',
            'description' => 'Setoran modal awal',
            'debit' => 5000000,
            'credit' => 0,
            'journal_type' => 'opening',
            'source_type' => 'Seeder',
            'source_id' => 1,
        ]);
        JournalEntry::create([
            'coa_id' => $equity->id,
            'date' => $today,
            'reference' => 'OPEN-001',
            'description' => 'Setoran modal awal',
            'debit' => 0,
            'credit' => 5000000,
            'journal_type' => 'opening',
            'source_type' => 'Seeder',
            'source_id' => 1,
        ]);

        // 2) Simulate a sale paid cash: Debit Cash, Credit Revenue (balanced)
        JournalEntry::create([
            'coa_id' => $cash->id,
            'date' => $today,
            'reference' => 'SALE-001',
            'description' => 'Penjualan tunai',
            'debit' => 1900000,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => 'Seeder',
            'source_id' => 2,
        ]);
        JournalEntry::create([
            'coa_id' => $revenue->id,
            'date' => $today,
            'reference' => 'SALE-001',
            'description' => 'Penjualan tunai',
            'debit' => 0,
            'credit' => 1900000,
            'journal_type' => 'sales',
            'source_type' => 'Seeder',
            'source_id' => 2,
        ]);

        // 3) Simulate cost: Debit Expense, Credit Cash (balanced)
        JournalEntry::create([
            'coa_id' => $expense->id,
            'date' => $today,
            'reference' => 'COST-001',
            'description' => 'Biaya operasional',
            'debit' => 400000,
            'credit' => 0,
            'journal_type' => 'expense',
            'source_type' => 'Seeder',
            'source_id' => 3,
        ]);
        JournalEntry::create([
            'coa_id' => $cash->id,
            'date' => $today,
            'reference' => 'COST-001',
            'description' => 'Biaya operasional',
            'debit' => 0,
            'credit' => 400000,
            'journal_type' => 'expense',
            'source_type' => 'Seeder',
            'source_id' => 3,
        ]);
    }
}
