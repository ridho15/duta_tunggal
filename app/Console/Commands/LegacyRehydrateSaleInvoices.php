<?php

namespace App\Console\Commands;

use App\Services\LegacySaleInvoiceRehydrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyRehydrateSaleInvoices extends Command
{
    protected $signature = 'legacy:rehydrate-sale-invoices
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal document_date (Y-m-d)}
        {--to= : Tanggal akhir document_date (Y-m-d)}
        {--limit=0 : Batasi jumlah dokumen untuk validasi cepat}
        {--created-by= : User ID pembuat dokumen hasil rehidrasi}
        {--inventory-cabang-id=2 : Target cabang untuk source inventory}
        {--inventory-cab-cabang-id=3 : Target cabang untuk source inventory_cab}
        {--chunk-size=500 : Jumlah dokumen per batch untuk menghemat memori}
        {--execute : Tulis hasil rehidrasi ke invoices, account_receivables, customer_receipts}';

    protected $description = 'Rehidrasi invoice penjualan legacy dari arsip ke invoices, account_receivables, dan customer_receipts secara idempotent';

    public function handle(LegacySaleInvoiceRehydrationService $service): int
    {
        $execute = (bool) $this->option('execute');
        $this->info('Legacy sale invoice rehydration — mode: ' . ($execute ? 'EXECUTE' : 'dry-run'));
        $this->info('Menghitung total dokumen...');

        $progressCount = 0;

        try {
            $summary = $service->rehydrate([
                'source'                    => $this->option('source'),
                'from'                      => $this->option('from'),
                'to'                        => $this->option('to'),
                'limit'                     => $this->option('limit'),
                'created_by'                => $this->option('created-by'),
                'inventory_cabang_id'       => $this->option('inventory-cabang-id'),
                'inventory_cab_cabang_id'   => $this->option('inventory-cab-cabang-id'),
                'chunk_size'                => $this->option('chunk-size'),
                'execute'                   => $execute,
                'on_progress'               => function (int $processed) use (&$progressCount) {
                    $progressCount = $processed;
                    if ($processed % 1000 === 0) {
                        $this->output->write("  {$processed} dokumen diproses...\n");
                    }
                },
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Legacy sale invoice rehydration summary');
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
            ['Source', 'Cabang', 'Documents', 'Processed', 'Invoices', 'AR Records', 'Receipts', 'Skip: No SO', 'Skip: Duplicate'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['cabang_id'],
                $row['documents'],
                $row['processed'],
                $row['invoices_created'],
                $row['ar_created'],
                $row['receipts_created'],
                $row['skipped_no_so'],
                $row['skipped_duplicate'],
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
