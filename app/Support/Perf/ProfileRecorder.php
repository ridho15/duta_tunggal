<?php

namespace App\Support\Perf;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mengumpulkan ukuran satu request (query, waktu, memori) untuk profiler (T1.6).
 * Singleton per aplikasi; listener DB dipasang SEKALI dan hanya merekam saat `running`.
 */
class ProfileRecorder
{
    private bool $running = false;

    private bool $listening = false;

    private int $startedAt = 0;

    /** @var array<int, array{sql: string, ms: float}> */
    private array $queries = [];

    public function start(): void
    {
        $this->queries = [];
        $this->startedAt = hrtime(true);
        $this->running = true;

        if (! $this->listening) {
            $this->listening = true;
            DB::listen(function (QueryExecuted $query) {
                if ($this->running) {
                    $this->queries[] = ['sql' => $query->sql, 'ms' => (float) $query->time];
                }
            });
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Selesaikan pengukuran. Mengembalikan baris log, atau null bila di bawah ambang (tidak perlu dicatat).
     *
     * @return array<string, mixed>|null
     */
    public function finish(Request $request, int $status, ?int $minMs = null, ?int $minQueries = null, ?int $duplicateThreshold = null): ?array
    {
        $this->running = false;

        $ms = (hrtime(true) - $this->startedAt) / 1_000_000;
        $count = count($this->queries);

        if ($ms < ($minMs ?? (int) config('perf.min_ms', 300)) && $count < ($minQueries ?? (int) config('perf.min_queries', 40))) {
            return null;
        }

        $livewire = self::describeLivewire($request->input('components'));
        $user = Auth::user();

        return [
            'ts' => now()->toIso8601String(),
            'route' => $request->route()?->getName() ?: '/'.ltrim($request->path(), '/'),
            'method' => $request->method(),
            'status' => $status,
            'ms' => round($ms, 1),
            'queries' => $count,
            'db_ms' => round(array_sum(array_column($this->queries, 'ms')), 1),
            'peak_mb' => round(memory_get_peak_usage(true) / 1_048_576, 1),
            'user_id' => $user?->getAuthIdentifier(),
            'cabang_id' => $user->cabang_id ?? null,
            'component' => $livewire['component'],
            'calls' => $livewire['calls'],
            'action' => $livewire['action'],
            'slow' => $this->slowest(5),
            'duplicates' => $this->duplicates($duplicateThreshold ?? (int) config('perf.duplicate_threshold', 10), 5),
        ];
    }

    /** @return array<int, array{ms: float, sql: string}> */
    private function slowest(int $limit): array
    {
        $queries = $this->queries;
        usort($queries, fn ($a, $b) => $b['ms'] <=> $a['ms']);

        return array_map(
            fn ($q) => ['ms' => round($q['ms'], 1), 'sql' => self::truncate(self::normalizeSql($q['sql']), 200)],
            array_slice($queries, 0, $limit)
        );
    }

    /** @return array<int, array{count: int, sql: string}> */
    private function duplicates(int $threshold, int $limit): array
    {
        $counts = [];
        foreach ($this->queries as $query) {
            $key = self::normalizeSql($query['sql']);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);

        $result = [];
        foreach ($counts as $sql => $count) {
            if ($count < $threshold || count($result) >= $limit) {
                break;
            }
            $result[] = ['count' => $count, 'sql' => self::truncate($sql, 200)];
        }

        return $result;
    }

    /** Samakan bentuk SQL agar pengulangan terdeteksi: rapikan spasi, ringkas daftar `in (?, ?, ?)`. */
    public static function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        return preg_replace('/\((\?(?:, ?\?)+)\)/', '(?)', $sql) ?? $sql;
    }

    private static function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value;
    }

    /**
     * Urai payload Livewire (`components[]`) menjadi nama komponen, metode yang dipanggil, dan nama aksi Filament yang sedang terbuka.
     * Argumen aksi TIDAK dicatat (bisa memuat data); hanya argumen pertama bila berupa teks pendek (mis. nama aksi).
     *
     * @return array{component: string|null, calls: array<int, string>, action: string|null}
     */
    public static function describeLivewire(mixed $components): array
    {
        $empty = ['component' => null, 'calls' => [], 'action' => null];

        if (! is_array($components) || $components === []) {
            return $empty;
        }

        $component = null;
        $calls = [];
        $action = null;

        foreach ($components as $item) {
            if (! is_array($item)) {
                continue;
            }

            $snapshot = is_string($item['snapshot'] ?? null) ? json_decode($item['snapshot'], true) : null;
            if (is_array($snapshot)) {
                $component ??= $snapshot['memo']['name'] ?? null;
                $action ??= self::firstMountedAction($snapshot['data'] ?? []);
            }

            foreach ($item['calls'] ?? [] as $call) {
                $method = is_string($call['method'] ?? null) ? $call['method'] : null;
                if ($method === null) {
                    continue;
                }
                $first = $call['params'][0] ?? null;
                $calls[] = is_string($first) && $first !== '' && mb_strlen($first) <= 60 && ! str_contains($first, '{')
                    ? "{$method}({$first})"
                    : $method;
            }
        }

        return ['component' => $component, 'calls' => $calls, 'action' => $action];
    }

    /** Cari nama aksi Filament yang terpasang (mountedActions / mountedTableActions) di data snapshot Livewire. */
    private static function firstMountedAction(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array($key, ['mountedActions', 'mountedTableActions', 'mountedInfolistActions'], true)) {
                $found = self::firstString($value);
                if ($found !== null) {
                    return $found;
                }
            } elseif (is_array($value) && ($found = self::firstMountedAction($value)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function firstString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value !== '' && mb_strlen($value) <= 60 ? $value : null;
        }

        if (is_array($value)) {
            foreach ($value as $inner) {
                if (($found = self::firstString($inner)) !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
