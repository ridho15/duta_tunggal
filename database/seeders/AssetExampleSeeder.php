<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssetExampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get COA IDs
        $peralatanKantor = ChartOfAccount::where('code', '1210.01')->first();
        $akumPeralatanKantor = ChartOfAccount::where('code', '1220.01')->first();
        $bebanPeralatanKantor = ChartOfAccount::where('code', '6311')->first();
        
        $kendaraan = ChartOfAccount::where('code', '1210.03')->first();
        $akumKendaraan = ChartOfAccount::where('code', '1220.03')->first();
        $bebanKendaraan = ChartOfAccount::where('code', '6313')->first();
        
        $bangunan = ChartOfAccount::where('code', '1210.04')->first();
        $akumBangunan = ChartOfAccount::where('code', '1220.04')->first();
        $bebanBangunan = ChartOfAccount::where('code', '6314')->first();

        $assets = [
            [
                'name' => 'Komputer Desktop Dell OptiPlex',
                'purchase_date' => '2023-01-15',
                'usage_date' => '2023-01-20',
                'purchase_cost' => 15000000,
                'salvage_value' => 1000000,
                'useful_life_years' => 5,
                'asset_coa_id' => $peralatanKantor->id,
                'accumulated_depreciation_coa_id' => $akumPeralatanKantor->id,
                'depreciation_expense_coa_id' => $bebanPeralatanKantor->id,
                'status' => 'active',
                'notes' => 'Komputer untuk departemen IT',
            ],
            [
                'name' => 'Printer HP LaserJet Pro',
                'purchase_date' => '2023-03-10',
                'usage_date' => '2023-03-15',
                'purchase_cost' => 5000000,
                'salvage_value' => 500000,
                'useful_life_years' => 4,
                'asset_coa_id' => $peralatanKantor->id,
                'accumulated_depreciation_coa_id' => $akumPeralatanKantor->id,
                'depreciation_expense_coa_id' => $bebanPeralatanKantor->id,
                'status' => 'active',
                'notes' => 'Printer untuk kantor utama',
            ],
            [
                'name' => 'Mobil Toyota Avanza',
                'purchase_date' => '2022-06-01',
                'usage_date' => '2022-06-05',
                'purchase_cost' => 250000000,
                'salvage_value' => 50000000,
                'useful_life_years' => 8,
                'asset_coa_id' => $kendaraan->id,
                'accumulated_depreciation_coa_id' => $akumKendaraan->id,
                'depreciation_expense_coa_id' => $bebanKendaraan->id,
                'status' => 'active',
                'notes' => 'Kendaraan operasional kantor',
            ],
            [
                'name' => 'Gedung Kantor',
                'purchase_date' => '2020-01-01',
                'usage_date' => '2020-02-01',
                'purchase_cost' => 5000000000,
                'salvage_value' => 500000000,
                'useful_life_years' => 20,
                'asset_coa_id' => $bangunan->id,
                'accumulated_depreciation_coa_id' => $akumBangunan->id,
                'depreciation_expense_coa_id' => $bebanBangunan->id,
                'status' => 'active',
                'notes' => 'Gedung kantor pusat 3 lantai',
            ],
        ];

        foreach ($assets as $assetData) {
            $asset = Asset::firstOrCreate(
                ['name' => $assetData['name'], 'purchase_date' => $assetData['purchase_date']],
                $assetData
            );

            // Calculate depreciation only when a new asset was created by the seeder
            if ($asset->wasRecentlyCreated) {
                $asset->calculateDepreciation();
            }

            $this->command->info('Asset ensured: ' . $asset->name);
        }

        $this->command->info('Contoh data aset berhasil dibuat!');
    }
}