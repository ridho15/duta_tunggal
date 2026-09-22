<?php

namespace App\Console\Commands;

use App\Services\LegacySalesOrderRehydrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyRehydrateSalesOrders extends Command
{
    protected $signature = 'legacy:rehydrate-sales-orders
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal document_date (Y-m-d)}
        {--to= : Tanggal akhir document_date (Y-m-d)}
        {--limit=0 : Batasi jumlah sales document untuk validasi cepat}
        {--created-by= : User ID pembuat dokumen hasil rehidrasi}
        {--inventory-cabang-id=2 : Target cabang untuk source inventory}
        {--inventory-warehouse-id=2 : Target warehouse untuk source inventory}
        {--inventory-cab-cabang-id=3 : Target cabang untuk source inventory_cab}
        {--inventory-cab-warehouse-id=3 : Target warehouse untuk source inventory_cab}
        {--chunk-size=500 : Jumlah dokumen per batch untuk menghemat memori}
        {--execute : Tulis hasil rehidrasi ke sale_orders dan sale_order_items}';

    protected $description = 'Rehidrasi histori sales legacy dari arsip ke sale_orders aktif secara idempotent';

    public function handle(LegacySalesOrderRehydrationService $service): int
    {
        $execute = (bool) $this->option('execute');
        $this->info('Legacy sales rehydration — mode: ' . ($execute ? 'EXECUTE' : 'dry-run'));
        $this->info('Menghitung total dokumen...');

        $progressCount = 0;
        $progressBar = null;

        try {
            $summary = $service->rehydrate([
                'source' => $this->option('source'),
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'limit' => $this->option('limit'),
                'created_by' => $this->option('created-by'),
                'inventory_cabang_id' => $this->option('inventory-cabang-id'),
                'inventory_warehouse_id' => $this->option('inventory-warehouse-id'),
                'inventory_cab_cabang_id' => $this->option('inventory-cab-cabang-id'),
                'inventory_cab_warehouse_id' => $this->option('inventory-cab-warehouse-id'),
                'chunk_size' => $this->option('chunk-size'),
                'execute' => $execute,
                'on_progress' => function (int $processed) use (&$progressCount, &$progressBar) {
                    $progressCount = $processed;
                    if ($progressBar === null) {
                        // Show a dot every 1000 for compact progress
                        if ($processed % 1000 === 0) {
                            $this->output->write("  {$processed} dokumen diproses...\n");
                        }
                    }
                },
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['mode', $summary['mode']],
                ['sources', implode(', ', $summary['sources'])],
                ['date_from', $summary['date_from'] ?: '-'],
                ['date_to', $summary['date_to'] ?: '-'],
                ['limit', (string) $summary['limit']],
                ['created_by', (string) $summary['created_by']],
            ]
        );

        $this->table(
            ['Source', 'Cabang', 'Warehouse', 'Documents', 'Processed', 'Upserted', 'Missing Customer', 'Without Items', 'Missing Products'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['target_cabang_id'],
                $row['target_warehouse_id'],
                $row['documents'],
                $row['processed'],
                $row['upserted'],
                $row['skipped_missing_customer'],
                $row['skipped_without_items'],
                $row['missing_products'],
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