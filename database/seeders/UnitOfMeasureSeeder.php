<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'abbreviation' => 'pcs'],
            ['name' => 'Kilogram', 'abbreviation' => 'kg'],
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['name' => 'Liter', 'abbreviation' => 'l'],
            ['name' => 'Meter', 'abbreviation' => 'm'],
            ['name' => 'Dozen', 'abbreviation' => 'doz'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::firstOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
