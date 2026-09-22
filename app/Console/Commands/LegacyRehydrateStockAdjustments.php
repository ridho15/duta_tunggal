<?php

namespace App\Console\Commands;

use App\Services\LegacyStockAdjustmentRehydrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyRehydrateStockAdjustments extends Command
{
    protected $signature = 'legacy:rehydrate-stock-adjustments
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal document_date (Y-m-d)}
        {--to= : Tanggal akhir document_date (Y-m-d)}
        {--limit=0 : Batasi jumlah dokumen untuk validasi cepat}
        {--created-by= : User ID pembuat dokumen hasil rehidrasi}
        {--inventory-warehouse-id=2 : Target warehouse untuk source inventory}
        {--inventory-cab-warehouse-id=3 : Target warehouse untuk source inventory_cab}
        {--chunk-size=1000 : Jumlah baris per batch untuk menghemat memori}
        {--execute : Tulis hasil rehidrasi ke stock_adjustments dan stock_adjustment_items}';

    protected $description = 'Rehidrasi stock adjustment legacy dari arsip ke stock_adjustments dan stock_adjustment_items secara idempotent (referensi historis saja)';

    public function handle(LegacyStockAdjustmentRehydrationService $service): int
    {
        $execute = (bool) $this->option('execute');
        $this->info('Legacy stock adjustment rehydration — mode: ' . ($execute ? 'EXECUTE' : 'dry-run'));

        $progressCount = 0;

        try {
            $summary = $service->rehydrate([
                'source'                       => $this->option('source'),
                'from'                         => $this->option('from'),
                'to'                           => $this->option('to'),
                'limit'                        => $this->option('limit'),
                'created_by'                   => $this->option('created-by'),
                'inventory_warehouse_id'       => $this->option('inventory-warehouse-id'),
                'inventory_cab_warehouse_id'   => $this->option('inventory-cab-warehouse-id'),
                'chunk_size'                   => $this->option('chunk-size'),
                'execute'                      => $execute,
                'on_progress'                  => function (int $processed) use (&$progressCount) {
                    $progressCount = $processed;
                    if ($processed % 1000 === 0) {
                        $this->output->write("  {$processed} baris diproses...\n");
                    }
                },
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Legacy stock adjustment rehydration summary');
        $this->table(
            ['Field', 'Value'],
            [
                ['mode', $summary['mode']],
                ['sources', implode(', ', $summary['sources'])],
                ['date_from', $summary['date_from'] ?: '-'],
                ['date_to', $summary['date_to'] ?: '-'],
                ['limit', (string) $summary['limit']],
            ]
        );

        $this->table(
            ['Source', 'Warehouse', 'Documents', 'Processed', 'Created', 'Skip: Duplicate', 'Skip: No Product'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['warehouse_id'],
                $row['documents'],
                $row['processed'],
                $row['created'],
                $row['skipped_duplicate'],
                $row['skipped_no_product'],
            ], $summary['rows'])
        );

        $this->newLine();
        $this->warn('Notes');
        foreach ($summary['notes'] as $note) {
            $this->line('- ' . $note);
        }

        return self::SUCCESS;
    }
}
