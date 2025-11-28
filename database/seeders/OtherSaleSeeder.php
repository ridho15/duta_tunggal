<?php

namespace Database\Seeders;

use App\Models\OtherSale;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Models\CashBankAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OtherSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding other sales data...');

        // Get required data
        $cabang = Cabang::first();
        $revenueCoa = ChartOfAccount::where('code', '7000.04')->first(); // PENDAPATAN LAINNYA
        $cashAccount = CashBankAccount::first();

        if (!$cabang || !$revenueCoa) {
            $this->command->warn('Required data not found. Skipping other sales seeding.');
            return;
        }

        // Sample building rental income data
        $otherSales = [
            [
                'reference_number' => 'OS-20241101-001',
                'transaction_date' => now()->subDays(30),
                'type' => 'building_rental',
                'description' => 'Pendapatan Sewa Gedung Bulan Oktober 2024 - Lantai 1',
                'amount' => 15000000,
                'coa_id' => $revenueCoa->id,
                'cash_bank_account_id' => $cashAccount?->id,
                'cabang_id' => $cabang->id,
                'status' => 'draft',
                'notes' => 'Pembayaran sewa gedung dari tenant ABC Corp untuk periode Oktober 2024',
                'created_by' => 1,
            ],
            [
                'reference_number' => 'OS-20241101-002',
                'transaction_date' => now()->subDays(15),
                'type' => 'building_rental',
                'description' => 'Pendapatan Sewa Gedung Bulan November 2024 - Lantai 2',
                'amount' => 12000000,
                'coa_id' => $revenueCoa->id,
                'cash_bank_account_id' => $cashAccount?->id,
                'cabang_id' => $cabang->id,
                'status' => 'draft',
                'notes' => 'Pembayaran sewa gedung dari tenant XYZ Ltd untuk periode November 2024',
                'created_by' => 1,
            ],
            [
                'reference_number' => 'OS-20241101-003',
                'transaction_date' => now()->subDays(5),
                'type' => 'other_income',
                'description' => 'Pendapatan Jasa Konsultasi IT',
                'amount' => 5000000,
                'coa_id' => $revenueCoa->id,
                'cash_bank_account_id' => $cashAccount?->id,
                'cabang_id' => $cabang->id,
                'status' => 'draft',
                'notes' => 'Pendapatan dari jasa konsultasi IT untuk klien external',
                'created_by' => 1,
            ],
        ];

        $created = 0;
        foreach ($otherSales as $saleData) {
            $sale = OtherSale::updateOrCreate(
                ['reference_number' => $saleData['reference_number']],
                $saleData
            );

            if ($sale->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("Successfully seeded other sales data. Created {$created} records.");
    }
}
