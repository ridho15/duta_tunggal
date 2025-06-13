<?php

namespace Database\Seeders;

use App\Models\QuotationItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuotationItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        QuotationItem::factory()->count(100)->create();
    }
}
