<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChartOfAccountWithBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Contoh data COA dengan saldo dan history
        $coaData = [
            [
                'code' => '1111.01',
                'name' => 'KAS BESAR',
                'type' => 'Asset',
                'opening_balance' => 10000000.00,
                'debit' => 5000000.00,
                'credit' => 2000000.00,
                'ending_balance' => 13000000.00,
                'is_active' => true,
            ],
            [
                'code' => '1112.01',
                'name' => 'BANK BCA',
                'type' => 'Asset',
                'opening_balance' => 50000000.00,
                'debit' => 10000000.00,
                'credit' => 5000000.00,
                'ending_balance' => 55000000.00,
                'is_active' => true,
            ],
            [
                'code' => '1121.01',
                'name' => 'PIUTANG TOKOPEDIA',
                'type' => 'Asset',
                'opening_balance' => 0.00,
                'debit' => 25000000.00,
                'credit' => 15000000.00,
                'ending_balance' => 10000000.00,
                'is_active' => true,
            ],
            [
                'code' => '2110',
                'name' => 'HUTANG DAGANG',
                'type' => 'Liability',
                'opening_balance' => 20000000.00,
                'debit' => 5000000.00,
                'credit' => 15000000.00,
                'ending_balance' => 10000000.00,
                'is_active' => true,
            ],
            [
                'code' => '4100',
                'name' => 'PENDAPATAN PENJUALAN',
                'type' => 'Revenue',
                'opening_balance' => 0.00,
                'debit' => 0.00,
                'credit' => 75000000.00,
                'ending_balance' => -75000000.00,
                'is_active' => true,
            ],
            [
                'code' => '5100',
                'name' => 'HARGA POKOK PENJUALAN',
                'type' => 'Expense',
                'opening_balance' => 0.00,
                'debit' => 30000000.00,
                'credit' => 0.00,
                'ending_balance' => 30000000.00,
                'is_active' => true,
            ],
        ];

        foreach ($coaData as $data) {
            ChartOfAccount::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('COA dengan saldo berhasil dibuat!');
    }
}