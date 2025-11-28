<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssetCoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            // Aset COA
            [
                'code' => '1210.01',
                'name' => 'PERALATAN KANTOR (OE)',
                'type' => 'Asset',
                'is_active' => true,
            ],
            [
                'code' => '1210.02',
                'name' => 'PERLENGKAPAN KANTOR (FF)',
                'type' => 'Asset',
                'is_active' => true,
            ],
            [
                'code' => '1210.03',
                'name' => 'KENDARAAN',
                'type' => 'Asset',
                'is_active' => true,
            ],
            [
                'code' => '1210.04',
                'name' => 'BANGUNAN',
                'type' => 'Asset',
                'is_active' => true,
            ],
            
            // Akumulasi Penyusutan COA
            [
                'code' => '1220.01',
                'name' => 'AKUMULASI BIAYA PENYUSUTAN PERALATAN KANTOR (OE)',
                'type' => 'Contra Asset',
                'is_active' => true,
            ],
            [
                'code' => '1220.02',
                'name' => 'AKUMULASI BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (FF)',
                'type' => 'Contra Asset',
                'is_active' => true,
            ],
            [
                'code' => '1220.03',
                'name' => 'AKUMULASI BIAYA PENYUSUTAN KENDARAAN',
                'type' => 'Contra Asset',
                'is_active' => true,
            ],
            [
                'code' => '1220.04',
                'name' => 'AKUMULASI BIAYA PENYUSUTAN BANGUNAN',
                'type' => 'Contra Asset',
                'is_active' => true,
            ],
            
            // Beban Penyusutan COA
            [
                'code' => '6311',
                'name' => 'BIAYA PENYUSUTAN PERALATAN KANTOR (OE)',
                'type' => 'Expense',
                'is_active' => true,
            ],
            [
                'code' => '6312',
                'name' => 'BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (FF)',
                'type' => 'Expense',
                'is_active' => true,
            ],
            [
                'code' => '6313',
                'name' => 'BIAYA PENYUSUTAN KENDARAAN',
                'type' => 'Expense',
                'is_active' => true,
            ],
            [
                'code' => '6314',
                'name' => 'BIAYA PENYUSUTAN BANGUNAN',
                'type' => 'Expense',
                'is_active' => true,
            ],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }

        $this->command->info('COA untuk Aset Tetap berhasil dibuat!');
    }
}