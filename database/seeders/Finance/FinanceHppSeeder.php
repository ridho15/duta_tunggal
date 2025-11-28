<?php

namespace Database\Seeders\Finance;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceHppSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $start = Carbon::now()->startOfYear();
        $warehouse = $this->context->ensureWarehouse();
        $rak = $this->context->ensureRak();
        [, , $rawMaterial] = $this->context->getSeedProductSet();

        $stockMovements = [
            [
                'reference' => 'RM-PO-202501',
                'type' => 'purchase_in',
                'quantity' => 500,
                'value' => 92500000,
                'date' => $start->copy()->addMonths(1),
                'notes' => 'Penerimaan bahan baku utama',
            ],
            [
                'reference' => 'RM-MFG-202503',
                'type' => 'manufacture_out',
                'quantity' => 320,
                'value' => 59000000,
                'date' => $start->copy()->addMonths(3),
                'notes' => 'Pemakaian bahan baku untuk produksi',
            ],
            [
                'reference' => 'RM-ADJ-202505',
                'type' => 'adjustment_in',
                'quantity' => 20,
                'value' => 3500000,
                'date' => $start->copy()->addMonths(5),
                'notes' => 'Penyesuaian stok hasil audit',
            ],
        ];

        foreach ($stockMovements as $movement) {
            StockMovement::updateOrCreate(
                ['reference_id' => $movement['reference']],
                [
                    'product_id' => $rawMaterial->id,
                    'warehouse_id' => $warehouse->id,
                    'rak_id' => $rak->id,
                    'quantity' => $movement['quantity'],
                    'value' => $movement['value'],
                    'type' => $movement['type'],
                    'date' => $movement['date']->toDateString(),
                    'notes' => $movement['notes'],
                ]
            );
        }

        $journalRows = [
            ['reference' => 'JE-HPP-5110', 'coa' => '5110', 'date' => Carbon::now()->subDays(50), 'debit' => 98000000, 'credit' => 0, 'description' => 'Pembelian bahan baku periode berjalan'],
            ['reference' => 'JE-HPP-5110', 'coa' => '2110', 'date' => Carbon::now()->subDays(50), 'debit' => 0, 'credit' => 98000000, 'description' => 'Hutang pembelian bahan baku'],
            ['reference' => 'JE-HPP-5120', 'coa' => '5120', 'date' => Carbon::now()->subDays(40), 'debit' => 42000000, 'credit' => 0, 'description' => 'Biaya tenaga kerja langsung'],
            ['reference' => 'JE-HPP-5120', 'coa' => '2110', 'date' => Carbon::now()->subDays(40), 'debit' => 0, 'credit' => 42000000, 'description' => 'Hutang biaya tenaga kerja'],
            ['reference' => 'JE-HPP-5130', 'coa' => '5130', 'date' => Carbon::now()->subDays(35), 'debit' => 11200000, 'credit' => 0, 'description' => 'Biaya listrik pabrik'],
            ['reference' => 'JE-HPP-5130', 'coa' => '2110', 'date' => Carbon::now()->subDays(35), 'debit' => 0, 'credit' => 11200000, 'description' => 'Hutang biaya listrik'],
            ['reference' => 'JE-HPP-5140', 'coa' => '5140', 'date' => Carbon::now()->subDays(32), 'debit' => 8400000, 'credit' => 0, 'description' => 'Penyusutan mesin produksi'],
            ['reference' => 'JE-HPP-5140', 'coa' => '2110', 'date' => Carbon::now()->subDays(32), 'debit' => 0, 'credit' => 8400000, 'description' => 'Hutang penyusutan mesin'],
            ['reference' => 'JE-HPP-5150', 'coa' => '5150', 'date' => Carbon::now()->subDays(28), 'debit' => 6200000, 'credit' => 0, 'description' => 'Perawatan mesin produksi'],
            ['reference' => 'JE-HPP-5150', 'coa' => '2110', 'date' => Carbon::now()->subDays(28), 'debit' => 0, 'credit' => 6200000, 'description' => 'Hutang perawatan mesin'],
            ['reference' => 'JE-HPP-1140-ADJ', 'coa' => '1140.01', 'date' => Carbon::now()->subDays(20), 'debit' => 0, 'credit' => 5500000, 'description' => 'Pemakaian bahan baku ke produksi'],
            ['reference' => 'JE-HPP-1140-ADJ', 'coa' => '5110', 'date' => Carbon::now()->subDays(20), 'debit' => 5500000, 'credit' => 0, 'description' => 'Biaya bahan baku terpakai'],
            ['reference' => 'JE-HPP-1150', 'coa' => '1150', 'date' => Carbon::now()->subDays(18), 'debit' => 3200000, 'credit' => 0, 'description' => 'Penambahan barang dalam proses'],
            ['reference' => 'JE-HPP-1150', 'coa' => '2110', 'date' => Carbon::now()->subDays(18), 'debit' => 0, 'credit' => 3200000, 'description' => 'Hutang penambahan barang dalam proses'],
        ];

        foreach ($journalRows as $row) {
            $this->context->recordJournalEntry($row['reference'], $row['coa'], $row['date'], $row['debit'], $row['credit'], $row['description']);
        }
    }
}
