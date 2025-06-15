<?php

namespace Database\Seeders;

use App\Models\OrderRequestItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderRequestItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrderRequestItem::factory()->count(100)->create();
    }
}
