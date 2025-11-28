<?php

namespace Database\Seeders;

use App\Models\JournalEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JournalEntryExampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // COA ID 1279 adalah KAS BESAR (1111.01)
        $coaId = 1279;

        // Contoh journal entries untuk KAS BESAR
        $journalEntries = [
            [
                'coa_id' => $coaId,
                'date' => '2025-10-01',
                'reference' => 'JE-2025-001',
                'description' => 'Penerimaan kas dari penjualan tunai',
                'debit' => 5000000.00,
                'credit' => 0.00,
                'journal_type' => 'sales',
                'source_type' => 'App\\Models\\Sale', // dummy
                'source_id' => 1,
            ],
            [
                'coa_id' => $coaId,
                'date' => '2025-10-02',
                'reference' => 'JE-2025-002',
                'description' => 'Pengeluaran kas untuk pembelian bahan baku',
                'debit' => 0.00,
                'credit' => 2000000.00,
                'journal_type' => 'purchase',
                'source_type' => 'App\\Models\\Purchase',
                'source_id' => 1,
            ],
            [
                'coa_id' => $coaId,
                'date' => '2025-10-03',
                'reference' => 'JE-2025-003',
                'description' => 'Penerimaan kas dari piutang',
                'debit' => 3000000.00,
                'credit' => 0.00,
                'journal_type' => 'collection',
                'source_type' => 'App\\Models\\Invoice',
                'source_id' => 1,
            ],
            [
                'coa_id' => $coaId,
                'date' => '2025-10-04',
                'reference' => 'JE-2025-004',
                'description' => 'Pengeluaran kas untuk biaya operasional',
                'debit' => 0.00,
                'credit' => 1500000.00,
                'journal_type' => 'expense',
                'source_type' => 'App\\Models\\Expense',
                'source_id' => 1,
            ],
            [
                'coa_id' => $coaId,
                'date' => '2025-10-05',
                'reference' => 'JE-2025-005',
                'description' => 'Penerimaan kas dari investasi',
                'debit' => 2500000.00,
                'credit' => 0.00,
                'journal_type' => 'investment',
                'source_type' => 'App\\Models\\Investment',
                'source_id' => 1,
            ],
        ];

        foreach ($journalEntries as $entry) {
            JournalEntry::create($entry);
        }

        $this->command->info('Journal entries contoh untuk COA KAS BESAR berhasil dibuat!');
        $this->command->info('Total entries: ' . count($journalEntries));
    }
}