<?php

namespace App\Console\Commands;

use App\Services\DocumentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * T4.1 — laporan penomoran dokumen penjualan (HANYA INFORMASI, tidak mengoreksi): nomor ganda, celah pada urutan format baru,
 * dan urutan `document_sequences` yang tertinggal dari nomor terbesar (perlu documents:seed-sequences).
 */
class CheckDocumentNumbering extends Command
{
    protected $signature = 'documents:check-numbering';

    protected $description = 'Laporkan nomor dokumen ganda, celah urutan, dan urutan yang tertinggal (hanya membaca)';

    public function handle(): int
    {
        $duplicates = [];
        $gaps = [];
        $behind = [];
        $legacy = [];

        foreach (DocumentNumberService::TYPES as $type => $definition) {
            $numbers = DB::table($definition['table'])->whereNotNull($definition['column'])->pluck($definition['column']);

            foreach ($numbers->countBy()->filter(fn ($n) => $n > 1) as $number => $count) {
                $duplicates[] = [$definition['label'], $number, $count];
            }

            $groups = [];
            $newFormat = 0;
            foreach ($numbers->unique() as $number) {
                if (! preg_match('/^(?<prefix>[A-Z]+(?:-[A-Z]+)*)-(?<cabang>[A-Z0-9]+)-(?<period>\d{4})-(?<seq>\d{4})$/', (string) $number, $m)) {
                    continue;
                }
                $newFormat++;
                $groups["{$m['prefix']}|{$m['cabang']}|{$m['period']}"][] = (int) $m['seq'];
            }
            $legacy[] = [$definition['label'], $numbers->unique()->count() - $newFormat, $newFormat];

            foreach ($groups as $key => $seqs) {
                sort($seqs);
                $missing = array_diff(range(1, max($seqs)), $seqs);
                if ($missing !== []) {
                    $gaps[] = [$definition['label'], $key, implode(',', array_slice($missing, 0, 10)).(count($missing) > 10 ? ',…' : '')];
                }
                [, $cabangCode, $period] = explode('|', $key);
                $current = (int) DB::table('document_sequences')->where(['type' => $type, 'period' => $period])->max('last_number');
                if ($current < max($seqs)) {
                    $behind[] = [$definition['label'], $key, max($seqs), $current];
                }
            }
        }

        $this->info('PEMERIKSAAN PENOMORAN DOKUMEN (hanya informasi)');
        $this->table(['Dokumen', 'Format lama', 'Format baru'], $legacy);
        $this->line('Nomor GANDA: '.count($duplicates));
        $this->line('Kelompok dengan celah urutan: '.count($gaps));
        $this->line('Kelompok dengan urutan tertinggal: '.count($behind));
        if ($duplicates !== []) {
            $this->warn('Nomor ganda:');
            $this->table(['Dokumen', 'Nomor', 'Jumlah'], $duplicates);
        }
        if ($gaps !== []) {
            $this->warn('Celah pada urutan (nomor dibatalkan/dihapus wajar; tinjau bila banyak):');
            $this->table(['Dokumen', 'Prefiks|Cabang|Periode', 'Nomor hilang'], $gaps);
        }
        if ($behind !== []) {
            $this->warn('Urutan tertinggal dari nomor terbesar — jalankan php artisan documents:seed-sequences:');
            $this->table(['Dokumen', 'Prefiks|Cabang|Periode', 'Terbesar', 'Urutan'], $behind);
        }

        return self::SUCCESS;
    }
}
