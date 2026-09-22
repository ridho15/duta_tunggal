<?php

namespace App\Console\Commands;

use App\Services\LegacyTransactionArchiveService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyImportTransactionArchive extends Command
{
    protected $signature = 'legacy:import-transaction-archive
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--only= : Filter group/table comma-separated, mis. sales,purchases,mutations,stockflows,adjustments}
        {--limit=0 : Batasi row per table untuk validasi cepat}
        {--chunk=250 : Jumlah row per batch upsert saat execute}
        {--truncate : Hapus arsip lama untuk source/table yang dipilih sebelum import ulang}
        {--execute : Jalankan import arsip ke database ERP. Tanpa flag ini command hanya dry-run}';

    protected $description = 'Arsipkan transaksi dan proses legacy inventory ke tabel ERP terpisah tanpa mem-posting ulang stok/jurnal live';

    public function handle(LegacyTransactionArchiveService $service): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        try {
            $summary = $service->import($this->option('source'), [
                'only' => $this->option('only'),
                'limit' => $this->option('limit'),
                'chunk' => $this->option('chunk'),
                'truncate' => (bool) $this->option('truncate'),
                'execute' => (bool) $this->option('execute'),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Legacy transaction archive summary');
        $this->table(
            ['Field', 'Value'],
            [
                ['mode', $summary['mode']],
                ['sources', implode(', ', $summary['sources'])],
                ['tables', implode(', ', $summary['tables'])],
                ['limit', (string) $summary['limit']],
                ['chunk', (string) $summary['chunk']],
            ]
        );

        $this->table(
            ['Source', 'Table', 'Group', 'Source Rows', 'Processed', 'Upserted', 'Skipped', 'Notes'],
            array_map(fn (array $row) => [
                $row['source'],
                $row['table'],
                $row['group'],
                $row['source_rows'],
                $row['processed_rows'],
                $row['upserted_rows'],
                $row['skipped'],
                $row['notes'],
            ], $summary['rows'])
        );

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