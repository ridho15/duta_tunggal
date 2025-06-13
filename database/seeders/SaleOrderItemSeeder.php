<?php

namespace Database\Seeders;

use App\Models\SaleOrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SaleOrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SaleOrderItem::factory()->count(100)->create();
    }
}
