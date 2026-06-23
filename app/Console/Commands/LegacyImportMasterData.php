<?php

namespace App\Console\Commands;

use App\Services\LegacyInventoryMigrationService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyImportMasterData extends Command
{
    protected $signature = 'legacy:import-master-data
        {source : inventory atau inventory_cab}
        {--only= : Langkah comma-separated: categories,uoms,customers,suppliers,products,stocks}
        {--key-prefix= : Prefix kode agar import aman tanpa menimpa data yang sudah ada}
        {--cabang_id= : Target cabang ERP}
        {--warehouse_id= : Target warehouse ERP untuk opening stock}
        {--limit=0 : Batasi row per langkah untuk validasi cepat}
        {--execute : Jalankan upsert ke database ERP. Tanpa flag ini command hanya dry-run}';

    protected $description = 'Import master data legacy inventory ke database ERP dengan mode dry-run sebagai default';

    public function handle(LegacyInventoryMigrationService $service): int
    {
        try {
            $summary = $service->importMasterData($this->argument('source'), [
                'only' => $this->option('only'),
                'key_prefix' => $this->option('key-prefix'),
                'cabang_id' => $this->option('cabang_id'),
                'warehouse_id' => $this->option('warehouse_id'),
                'limit' => $this->option('limit'),
                'execute' => (bool) $this->option('execute'),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Legacy import summary');
        $this->table(
            ['Field', 'Value'],
            [
                ['source', $summary['source']['label']],
                ['mode', $summary['mode']],
                ['steps', implode(', ', $summary['steps'])],
                ['cabang_id', $summary['cabang_id'] ?: '-'],
                ['warehouse_id', $summary['warehouse_id'] ?: '-'],
            ]
        );

        $rows = [];
        foreach ($summary['entities'] as $entity => $stats) {
            $rows[] = [
                $entity,
                $stats['source_rows'] ?? 0,
                $stats['created'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['unchanged'] ?? 0,
                $stats['skipped'] ?? 0,
            ];
        }

        $this->table(['Entity', 'Source Rows', 'Created', 'Updated', 'Unchanged', 'Skipped'], $rows);

        if ($summary['notes']) {
            $this->newLine();
            $this->warn('Notes');
            foreach ($summary['notes'] as $note) {
                $this->line('- ' . $note);
            }
        }

        return self::SUCCESS;
    }
}