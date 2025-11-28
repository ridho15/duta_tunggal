<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class BukuBesarTestSeeder extends Seeder
{
    /**
     * Seed Buku Besar test data with various journal entries
     * 
     * This seeder creates:
     * - Chart of Accounts (via ChartOfAccountSeeder if needed)
     * - Journal entries across different dates for testing ledger
     * - Entries for multiple COAs to test Select2 search/filtering
     */
    public function run(): void
    {
        // Ensure COA exists first
        $this->call(ChartOfAccountSeeder::class);

        // Clear existing test journal entries (optional - comment out if you want to keep existing)
        // JournalEntry::whereIn('journal_type', ['test', 'demo'])->delete();

        // Get some key accounts for testing
        $kasAccount = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR
        $bankBcaAccount = ChartOfAccount::where('code', '1112.01')->first(); // BANK BCA
        $bankMandiriAccount = ChartOfAccount::where('code', '1112.02')->first(); // BANK MANDIRI
        $piutangAccount = ChartOfAccount::where('code', '1120')->first(); // PIUTANG DAGANG
        $penjualanAccount = ChartOfAccount::where('code', '4100')->first(); // PENJUALAN BARANG DAGANGAN
        $hutangAccount = ChartOfAccount::where('code', '2110')->first(); // HUTANG DAGANG
        $pembelianAccount = ChartOfAccount::where('code', '5100')->first(); // HPP
        $biayaGajiAccount = ChartOfAccount::where('code', '6110.01')->first(); // BIAYA GAJI
        $biayaListrikAccount = ChartOfAccount::where('code', '6130.02')->first(); // BIAYA LISTRIK

        if (!$kasAccount || !$bankBcaAccount || !$bankMandiriAccount || !$piutangAccount || !$penjualanAccount) {
            $this->command->error('Required Chart of Accounts not found. Please run ChartOfAccountSeeder first.');
            return;
        }

        // Use fallback for expense accounts if they don't exist
        if (!$biayaGajiAccount) {
            $biayaGajiAccount = ChartOfAccount::where('type', 'Expense')->first();
        }
        if (!$biayaListrikAccount) {
            $biayaListrikAccount = ChartOfAccount::where('type', 'Expense')->skip(1)->first();
        }

        $this->command->info('Creating Buku Besar test journal entries...');

        // Generate test data for the last 3 months
        $startDate = Carbon::now()->subMonths(3)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // ===========================
        // 1. CASH TRANSACTIONS (KAS BESAR)
        // ===========================
        $this->createJournalPair(
            $kasAccount,
            $penjualanAccount,
            Carbon::now()->subDays(45),
            'INV-2024-001',
            'Penerimaan kas dari penjualan tunai',
            5000000
        );

        $this->createJournalPair(
            $kasAccount,
            $penjualanAccount,
            Carbon::now()->subDays(30),
            'INV-2024-002',
            'Penerimaan kas dari penjualan tunai',
            3500000
        );

        $this->createJournalPair(
            $kasAccount,
            $penjualanAccount,
            Carbon::now()->subDays(15),
            'INV-2024-003',
            'Penerimaan kas dari penjualan tunai',
            7200000
        );

        $this->createJournalPair(
            $biayaGajiAccount,
            $kasAccount,
            Carbon::now()->subDays(10),
            'PAY-2024-001',
            'Pembayaran gaji karyawan',
            15000000
        );

        // ===========================
        // 2. BANK BCA TRANSACTIONS
        // ===========================
        $this->createJournalPair(
            $bankBcaAccount,
            $piutangAccount,
            Carbon::now()->subDays(50),
            'TRF-2024-001',
            'Transfer dari customer PT ABC',
            25000000
        );

        $this->createJournalPair(
            $bankBcaAccount,
            $penjualanAccount,
            Carbon::now()->subDays(40),
            'TRF-2024-002',
            'Transfer penjualan online',
            12000000
        );

        $this->createJournalPair(
            $hutangAccount,
            $bankBcaAccount,
            Carbon::now()->subDays(35),
            'PAY-2024-002',
            'Pembayaran hutang supplier',
            18000000
        );

        $this->createJournalPair(
            $bankBcaAccount,
            $piutangAccount,
            Carbon::now()->subDays(20),
            'TRF-2024-003',
            'Pelunasan piutang customer',
            8500000
        );

        $this->createJournalPair(
            $biayaListrikAccount,
            $bankBcaAccount,
            Carbon::now()->subDays(7),
            'PAY-2024-003',
            'Pembayaran listrik bulanan',
            2500000
        );

        // ===========================
        // 3. BANK MANDIRI TRANSACTIONS
        // ===========================
        $this->createJournalPair(
            $bankMandiriAccount,
            $penjualanAccount,
            Carbon::now()->subDays(60),
            'TRF-2024-004',
            'Penerimaan pembayaran export',
            45000000
        );

        $this->createJournalPair(
            $bankMandiriAccount,
            $piutangAccount,
            Carbon::now()->subDays(25),
            'TRF-2024-005',
            'Transfer dari distributor',
            32000000
        );

        $this->createJournalPair(
            $pembelianAccount,
            $bankMandiriAccount,
            Carbon::now()->subDays(18),
            'PAY-2024-004',
            'Pembelian bahan baku',
            28000000
        );

        // ===========================
        // 4. PIUTANG TRANSACTIONS
        // ===========================
        $this->createJournalPair(
            $piutangAccount,
            $penjualanAccount,
            Carbon::now()->subDays(55),
            'INV-2024-004',
            'Penjualan kredit kepada PT XYZ',
            42000000
        );

        $this->createJournalPair(
            $piutangAccount,
            $penjualanAccount,
            Carbon::now()->subDays(42),
            'INV-2024-005',
            'Penjualan kredit kepada CV Maju',
            28000000
        );

        $this->createJournalPair(
            $piutangAccount,
            $penjualanAccount,
            Carbon::now()->subDays(28),
            'INV-2024-006',
            'Penjualan kredit kepada UD Berkah',
            15500000
        );

        // ===========================
        // 5. HUTANG TRANSACTIONS
        // ===========================
        $this->createJournalPair(
            $pembelianAccount,
            $hutangAccount,
            Carbon::now()->subDays(65),
            'PO-2024-001',
            'Pembelian barang dari supplier A',
            35000000
        );

        $this->createJournalPair(
            $pembelianAccount,
            $hutangAccount,
            Carbon::now()->subDays(48),
            'PO-2024-002',
            'Pembelian barang dari supplier B',
            22000000
        );

        // ===========================
        // 6. CURRENT MONTH TRANSACTIONS (for testing date filters)
        // ===========================
        $this->createJournalPair(
            $kasAccount,
            $penjualanAccount,
            Carbon::now()->subDays(5),
            'INV-2024-007',
            'Penjualan tunai minggu ini',
            6800000
        );

        $this->createJournalPair(
            $bankBcaAccount,
            $penjualanAccount,
            Carbon::now()->subDays(3),
            'TRF-2024-006',
            'Transfer penjualan terbaru',
            9200000
        );

        $this->createJournalPair(
            $kasAccount,
            $penjualanAccount,
            Carbon::now()->subDays(1),
            'INV-2024-008',
            'Penjualan hari ini',
            4500000
        );

        // ===========================
        // 7. INTER-ACCOUNT TRANSFERS
        // ===========================
        $this->createJournalPair(
            $bankBcaAccount,
            $kasAccount,
            Carbon::now()->subDays(22),
            'TRF-INT-001',
            'Transfer kas ke bank BCA',
            10000000
        );

        $this->createJournalPair(
            $bankMandiriAccount,
            $bankBcaAccount,
            Carbon::now()->subDays(12),
            'TRF-INT-002',
            'Transfer antar bank',
            15000000
        );

        $totalEntries = JournalEntry::where('journal_type', 'test')->count();
        $this->command->info("✅ Created {$totalEntries} test journal entries across multiple accounts");
        $this->command->info('   Accounts with test data:');
        $this->command->info("   - KAS BESAR (1111.01)");
        $this->command->info("   - BANK BCA (1112.01)");
        $this->command->info("   - BANK MANDIRI (1112.02)");
        $this->command->info("   - PIUTANG DAGANG (1120)");
        $this->command->info("   - HUTANG DAGANG (2110)");
        $this->command->info("   - PENJUALAN (4100)");
        $this->command->info("   - HPP (5100)");
        
        if ($biayaGajiAccount) {
            $this->command->info("   - BIAYA GAJI ({$biayaGajiAccount->code})");
        }
        if ($biayaListrikAccount) {
            $this->command->info("   - BIAYA LISTRIK ({$biayaListrikAccount->code})");
        }

        $this->command->info('');
        $this->command->info('🎯 You can now test Buku Besar page at: http://localhost:8004/buku-besar-page');
        $this->command->info('   - Use Select2 to search for any account above');
        $this->command->info('   - Adjust date range to see filtered transactions');
        $this->command->info('   - Ledger will show opening balance, transactions, and running balance');
    }

    /**
     * Helper: Create a pair of journal entries (debit + credit)
     */
    private function createJournalPair(
        ChartOfAccount $debitAccount,
        ChartOfAccount $creditAccount,
        Carbon $date,
        string $reference,
        string $description,
        float $amount
    ): void {
        // Debit entry
        JournalEntry::create([
            'coa_id' => $debitAccount->id,
            'date' => $date->format('Y-m-d'),
            'reference' => $reference,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount', // Use a valid model class
            'source_id' => $debitAccount->id, // Use the account ID as dummy source
        ]);

        // Credit entry
        JournalEntry::create([
            'coa_id' => $creditAccount->id,
            'date' => $date->format('Y-m-d'),
            'reference' => $reference,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'journal_type' => 'test',
            'source_type' => 'App\\Models\\ChartOfAccount', // Use a valid model class
            'source_id' => $creditAccount->id, // Use the account ID as dummy source
        ]);
    }
}
