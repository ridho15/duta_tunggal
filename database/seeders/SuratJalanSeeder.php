<?php

namespace Database\Seeders;

use App\Models\SuratJalan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuratJalanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SuratJalan::factory()->count(10)->create();
    }
}
