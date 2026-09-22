<?php

namespace App\Console\Commands;

use App\Services\LegacyTransactionArchiveService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class LegacyImportDeliveryHistory extends Command
{
    protected $signature = 'legacy:import-delivery-history
        {--source=* : inventory dan/atau inventory_cab. Omit untuk memproses keduanya}
        {--from= : Tanggal awal document_date (Y-m-d)}
        {--to= : Tanggal akhir document_date (Y-m-d)}
        {--limit=0 : Batasi row untuk validasi cepat}
        {--chunk=250 : Jumlah row per batch upsert saat execute}
        {--execute : Jalankan import arsip delivery history ke database ERP}';

    protected $description = 'Arsipkan delivery order dan surat jalan legacy ke legacy_transaction_archives tanpa mengubah source database';

    public function handle(LegacyTransactionArchiveService $service): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        try {
            $summary = $service->import($this->option('source'), [
                'only' => 'delivery_history',
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'limit' => $this->option('limit'),
                'chunk' => $this->option('chunk'),
                'execute' => (bool) $this->option('execute'),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Legacy delivery history archive summary');
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