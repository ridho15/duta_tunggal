<?php

namespace App\Console\Commands;

use App\Services\LegacyQuotationRehydrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyRehydrateQuotations extends Command
{
    protected $signature = 'legacy:rehydrate-quotations
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal document_date (Y-m-d)}
        {--to= : Tanggal akhir document_date (Y-m-d)}
        {--limit=0 : Batasi jumlah quotation document untuk validasi cepat}
        {--created-by= : User ID pembuat dokumen hasil rehidrasi}
        {--inventory-cabang-id=2 : Target cabang untuk source inventory}
        {--inventory-cab-cabang-id=3 : Target cabang untuk source inventory_cab}
        {--execute : Tulis hasil rehidrasi ke quotations dan quotation_items}';

    protected $description = 'Rehidrasi histori quotation legacy dari arsip ke quotations aktif secara idempotent';

    public function handle(LegacyQuotationRehydrationService $service): int
    {
        try {
            $summary = $service->rehydrate([
                'source' => $this->option('source'),
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'limit' => $this->option('limit'),
                'created_by' => $this->option('created-by'),
                'inventory_cabang_id' => $this->option('inventory-cabang-id'),
                'inventory_cab_cabang_id' => $this->option('inventory-cab-cabang-id'),
                'execute' => (bool) $this->option('execute'),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Legacy quotation rehydration summary');
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
            ['Source', 'Cabang', 'Documents', 'Processed', 'Upserted', 'Missing Customer', 'Without Items', 'Missing Products', 'Auto Created Customers'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['target_cabang_id'],
                $row['documents'],
                $row['processed'],
                $row['upserted'],
                $row['skipped_missing_customer'],
                $row['skipped_without_items'],
                $row['missing_products'],
                $row['auto_created_customers'],
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