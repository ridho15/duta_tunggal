<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Ringkas log profiler (storage/logs/perf-*.log) per aksi: jumlah, rata-rata/p95/maks ms, rata-rata query,
 * dan SQL berulang teratas (indikasi N+1). Dasar memilih target optimasi secara objektif (T7).
 */
class PerfReport extends Command
{
    protected $signature = 'perf:report
        {--since=1d : Rentang waktu ke belakang: 30m, 2h, 1d, atau tanggal (2026-09-20)}
        {--top=20 : Jumlah aksi teratas (diurutkan menurut p95 ms)}
        {--action= : Hanya aksi yang mengandung teks ini}
        {--dir= : Folder log (default storage/logs)}';

    protected $description = 'Ringkas log profiler request (PERF_PROFILE=true) per aksi';

    public function handle(): int
    {
        $since = $this->parseSince((string) $this->option('since'));
        if ($since === null) {
            $this->error('Format --since tidak dikenali. Contoh: 30m, 2h, 1d, 2026-09-20');

            return self::FAILURE;
        }

        $dir = (string) ($this->option('dir') ?: storage_path('logs'));
        $entries = $this->readEntries($dir, $since);

        if ($entries === []) {
            $this->warn('Tidak ada data profiler sejak '.$since->toDateTimeString().'. Pastikan PERF_PROFILE=true, cache konfigurasi dibersihkan, dan aksi sudah dijalankan.');

            return self::SUCCESS;
        }

        $filter = mb_strtolower((string) $this->option('action'));
        $groups = [];
        foreach ($entries as $entry) {
            $key = self::actionKey($entry);
            if ($filter !== '' && ! str_contains(mb_strtolower($key), $filter)) {
                continue;
            }
            $groups[$key][] = $entry;
        }

        $rows = [];
        foreach ($groups as $key => $items) {
            $ms = array_column($items, 'ms');
            sort($ms);
            $rows[] = [
                'aksi' => $key,
                'n' => count($items),
                'avg' => round(array_sum($ms) / count($ms)),
                'p95' => round($ms[(int) max(0, ceil(0.95 * count($ms)) - 1)]),
                'max' => round(max($ms)),
                'q' => round(array_sum(array_column($items, 'queries')) / count($items)),
                'db' => round(array_sum(array_column($items, 'db_ms')) / count($items)),
                'dup' => $this->topDuplicate($items),
            ];
        }

        usort($rows, fn ($a, $b) => $b['p95'] <=> $a['p95']);
        $rows = array_slice($rows, 0, max(1, (int) $this->option('top')));

        $this->info(sprintf('%d request tercatat sejak %s — %d aksi berbeda', count($entries), $since->toDateTimeString(), count($groups)));
        $this->table(
            ['Aksi', 'Jml', 'Rata2 ms', 'p95 ms', 'Maks ms', 'Rata2 query', 'Rata2 DB ms', 'SQL berulang teratas'],
            array_map(fn ($r) => [$r['aksi'], $r['n'], $r['avg'], $r['p95'], $r['max'], $r['q'], $r['db'], $r['dup']], $rows)
        );

        return self::SUCCESS;
    }

    /** Kunci pengelompokan: komponen::metode(arg)[@aksi] untuk Livewire; selain itu route/URL. */
    public static function actionKey(array $entry): string
    {
        if (! empty($entry['component'])) {
            $call = $entry['calls'][0] ?? '(render)';

            return $entry['component'].'::'.$call.(! empty($entry['action']) ? ' @'.$entry['action'] : '');
        }

        return ($entry['method'] ?? 'GET').' '.($entry['route'] ?? '?');
    }

    private function topDuplicate(array $items): string
    {
        $counts = [];
        foreach ($items as $item) {
            foreach ($item['duplicates'] ?? [] as $duplicate) {
                $counts[$duplicate['sql']] = max($counts[$duplicate['sql']] ?? 0, $duplicate['count']);
            }
        }
        if ($counts === []) {
            return '–';
        }
        arsort($counts);
        $sql = array_key_first($counts);

        return $counts[$sql].'× '.mb_substr($sql, 0, 90);
    }

    /** @return array<int, array<string, mixed>> */
    private function readEntries(string $dir, Carbon $since): array
    {
        $entries = [];

        foreach (File::glob(rtrim($dir, '/').'/perf*.log') as $file) {
            foreach (File::lines($file) as $line) {
                $pos = strpos($line, 'perf {');
                $end = strrpos($line, '}');
                if ($pos === false || $end === false || $end < $pos) {
                    continue;
                }
                // JSON dipotong pada '}' terakhir: mengabaikan sisa baris Monolog (mis. " []" untuk extra kosong).
                $entry = json_decode(substr($line, $pos + 5, $end - ($pos + 5) + 1), true);
                if (! is_array($entry) || ! isset($entry['ts'], $entry['ms'])) {
                    continue;
                }
                if (Carbon::parse($entry['ts'])->lt($since)) {
                    continue;
                }
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function parseSince(string $value): ?Carbon
    {
        $value = trim($value);

        if (preg_match('/^(\d+)([mhd])$/i', $value, $m)) {
            $amount = (int) $m[1];

            return match (strtolower($m[2])) {
                'm' => now()->subMinutes($amount),
                'h' => now()->subHours($amount),
                default => now()->subDays($amount),
            };
        }

        try {
            return $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
