<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1-1001', 'name' => 'Kas', 'type' => 'Asset', 'level' => 1],
            ['code' => '1-1002', 'name' => 'Bank', 'type' => 'Asset', 'level' => 1],
            ['code' => '1-2001', 'name' => 'Piutang Usaha', 'type' => 'Asset', 'level' => 1],
            ['code' => '2-1001', 'name' => 'Utang Usaha', 'type' => 'Liability', 'level' => 1],
            ['code' => '2-2001', 'name' => 'PPN Keluaran', 'type' => 'Liability', 'level' => 1],
            ['code' => '5-1001', 'name' => 'Biaya Admin Bank', 'type' => 'Expense', 'level' => 1],
            ['code' => '4-1001', 'name' => 'Penjualan', 'type' => 'Revenue', 'level' => 1],
        ];

        foreach ($accounts as $data) {
            ChartOfAccount::create($data);
        }
    }
}
