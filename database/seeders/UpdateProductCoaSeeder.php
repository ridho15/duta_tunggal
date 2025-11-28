<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdateProductCoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get COA IDs
        $temporaryProcurementCoaId = ChartOfAccount::where('code', '1400.01')->value('id');
        $unbilledPurchaseCoaId = ChartOfAccount::where('code', '2190.10')->value('id');

        if (!$temporaryProcurementCoaId || !$unbilledPurchaseCoaId) {
            $this->command->error('Required COA not found. Please run ChartOfAccountSeeder first.');
            return;
        }

        // Update all products that don't have these COA configured
        $updated = Product::whereNull('temporary_procurement_coa_id')
            ->orWhereNull('unbilled_purchase_coa_id')
            ->update([
                'temporary_procurement_coa_id' => $temporaryProcurementCoaId,
                'unbilled_purchase_coa_id' => $unbilledPurchaseCoaId,
            ]);

        $this->command->info("Updated COA configuration for {$updated} products.");
    }
}