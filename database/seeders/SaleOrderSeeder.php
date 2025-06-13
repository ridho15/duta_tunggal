<?php

namespace Database\Seeders;

use App\Models\SaleOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SaleOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SaleOrder::factory()->count(20)->create();
    }
}
