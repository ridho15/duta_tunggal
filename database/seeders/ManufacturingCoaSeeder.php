<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ManufacturingCoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'code' => '1140',
                'name' => 'PERSEDIAAN',
                'type' => 'Asset',
                'description' => 'Akun induk untuk semua jenis persediaan',
            ],
            [
                'code' => '1140.01',
                'name' => 'Persediaan Bahan Baku',
                'type' => 'Asset',
                'description' => 'Persediaan bahan baku yang digunakan untuk produksi',
            ],
            [
                'code' => '1140.02',
                'name' => 'Persediaan Barang Produksi',
                'type' => 'Asset',
                'description' => 'Persediaan hasil produksi yang mengikuti COA inventory pada product manufacture',
            ],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }

        $this->command->info('Manufacturing COA created successfully!');
    }
}
