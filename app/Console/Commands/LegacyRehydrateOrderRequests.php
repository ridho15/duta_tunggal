<?php

namespace App\Console\Commands;

use App\Services\LegacyOrderRequestRehydrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyRehydrateOrderRequests extends Command
{
    protected $signature = 'legacy:rehydrate-order-requests
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal request_date (Y-m-d)}
        {--to= : Tanggal akhir request_date (Y-m-d)}
        {--limit=0 : Batasi jumlah request untuk validasi cepat}
        {--created-by= : User ID pembuat dokumen hasil rehidrasi}
        {--inventory-warehouse-id=2 : Target warehouse untuk source inventory}
        {--inventory-cab-warehouse-id=3 : Target warehouse untuk source inventory_cab}
        {--chunk-size=500 : Jumlah dokumen per batch untuk menghemat memori}
        {--execute : Tulis hasil rehidrasi ke order_requests dan order_request_items}';

    protected $description = 'Rehidrasi legacy request barang dari database inventory dan inventory_cab ke order_requests dan order_request_items';

    public function handle(LegacyOrderRequestRehydrationService $service): int
    {
        $execute = (bool) $this->option('execute');
        $this->info('Legacy order request rehydration — mode: ' . ($execute ? 'EXECUTE' : 'dry-run'));

        try {
            $summary = $service->rehydrate([
                'source' => $this->option('source'),
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'limit' => $this->option('limit'),
                'created_by' => $this->option('created-by'),
                'inventory_warehouse_id' => $this->option('inventory-warehouse-id'),
                'inventory_cab_warehouse_id' => $this->option('inventory-cab-warehouse-id'),
                'chunk_size' => $this->option('chunk-size'),
                'execute' => $execute,
                'on_progress' => function (int $processed): void {
                    if ($processed % 500 === 0) {
                        $this->output->write("  {$processed} request diproses...\n");
                    }
                },
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Legacy order request rehydration summary');
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
            ['Source', 'Warehouse', 'Cabang', 'Documents', 'Processed', 'Requests', 'Items', 'Skip: Duplicate', 'Skip: No Items', 'Skip: No Product'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['warehouse_id'],
                $row['cabang_id'],
                $row['documents'],
                $row['processed'],
                $row['requests_created'],
                $row['items_created'],
                $row['skipped_duplicate'],
                $row['skipped_no_items'],
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
