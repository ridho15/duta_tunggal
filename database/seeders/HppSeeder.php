<?php

namespace Database\Seeders;

use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppOverheadItemPrefix;
use App\Models\Reports\HppPrefix;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HppSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // HPP Prefixes for different categories
        $prefixes = [
            // Raw Material Inventory
            ['category' => 'raw_material_inventory', 'prefix' => '1-101', 'sort_order' => 1],
            ['category' => 'raw_material_inventory', 'prefix' => '1-102', 'sort_order' => 2],

            // Raw Material Purchase
            ['category' => 'raw_material_purchase', 'prefix' => '5-101', 'sort_order' => 1],
            ['category' => 'raw_material_purchase', 'prefix' => '5-102', 'sort_order' => 2],

            // Direct Labor
            ['category' => 'direct_labor', 'prefix' => '6-201', 'sort_order' => 1],
            ['category' => 'direct_labor', 'prefix' => '6-202', 'sort_order' => 2],

            // WIP Inventory
            ['category' => 'wip_inventory', 'prefix' => '1-201', 'sort_order' => 1],
            ['category' => 'wip_inventory', 'prefix' => '1-202', 'sort_order' => 2],
        ];

        foreach ($prefixes as $prefix) {
            HppPrefix::create($prefix);
        }

        // HPP Overhead Items
        $overheadItems = [
            [
                'key' => 'factory_rent',
                'label' => 'Sewa Pabrik',
                'sort_order' => 1,
                'allocation_basis' => 'production_volume',
                'allocation_rate' => 0.1,
                'prefixes' => ['6-301', '6-302']
            ],
            [
                'key' => 'utilities',
                'label' => 'Utilitas (Listrik, Air, Gas)',
                'sort_order' => 2,
                'allocation_basis' => 'machine_hours',
                'allocation_rate' => 25.0,
                'prefixes' => ['6-303', '6-304']
            ],
            [
                'key' => 'factory_supplies',
                'label' => 'Perlengkapan Pabrik',
                'sort_order' => 3,
                'allocation_basis' => 'direct_material',
                'allocation_rate' => 0.05,
                'prefixes' => ['6-305']
            ],
            [
                'key' => 'maintenance',
                'label' => 'Perawatan dan Perbaikan',
                'sort_order' => 4,
                'allocation_basis' => 'machine_hours',
                'allocation_rate' => 15.0,
                'prefixes' => ['6-306', '6-307']
            ],
            [
                'key' => 'depreciation',
                'label' => 'Depresiasi Mesin dan Peralatan',
                'sort_order' => 5,
                'allocation_basis' => 'machine_hours',
                'allocation_rate' => 20.0,
                'prefixes' => ['6-308']
            ],
            [
                'key' => 'factory_insurance',
                'label' => 'Asuransi Pabrik',
                'sort_order' => 6,
                'allocation_basis' => 'production_volume',
                'allocation_rate' => 0.05,
                'prefixes' => ['6-309']
            ],
            [
                'key' => 'supervisor_salary',
                'label' => 'Gaji Supervisor',
                'sort_order' => 7,
                'allocation_basis' => 'direct_labor',
                'allocation_rate' => 0.15,
                'prefixes' => ['6-203', '6-204']
            ],
        ];

        foreach ($overheadItems as $itemData) {
            $prefixes = $itemData['prefixes'];
            unset($itemData['prefixes']);

            $item = HppOverheadItem::create($itemData);

            foreach ($prefixes as $prefix) {
                HppOverheadItemPrefix::create([
                    'overhead_item_id' => $item->id,
                    'prefix' => $prefix,
                ]);
            }
        }
    }
}
