<?php

namespace App\Support;

/**
 * Mencari KANDIDAT customer ganda (heuristik, hasilnya untuk ditinjau manusia — bukan keputusan otomatis).
 * Logika murni (tanpa DB) agar mudah diuji: menerima baris customer, mengembalikan grup + alasan.
 *
 * Alasan grup: nama sama (setelah normalisasi), NPWP/NIK sama, telepon sama, nama mirip.
 */
class CustomerDuplicateFinder
{
    /** Bentuk badan usaha yang dibuang saat membandingkan nama ("PT Daya Teknik" = "Daya Teknik, PT"). */
    private const LEGAL_FORMS = ['pt', 'cv', 'ud', 'pd', 'tbk', 'persero', 'koperasi', 'kop', 'fa', 'firma', 'yayasan'];

    /** Nomor yang dipakai lebih dari ini customer dianggap nomor bersama/dummy dan tidak dijadikan bukti duplikat. */
    private const MAX_SHARED_PHONE = 4;

    public static function normalizeName(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return '';
        }

        $name = strtr($name, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
        // "P.T." → "pt" (titik di antara huruf tunggal), lalu tanda baca lain menjadi spasi
        $name = preg_replace('/\b([a-z])\.(?=[a-z]\b)/u', '$1', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name;

        $tokens = array_values(array_filter(explode(' ', $name), fn ($t) => $t !== '' && ! in_array($t, self::LEGAL_FORMS, true)));

        return implode(' ', $tokens);
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    /** Kunci telepon: 9 digit terakhir tanpa awalan 0/62 (memadankan 0812…, +62812…, 62812…). */
    public static function phoneKey(?string $value): ?string
    {
        $digits = self::digits($value);
        $digits = preg_replace('/^(62|0)+/', '', $digits) ?? $digits;

        return strlen($digits) >= 8 ? substr($digits, -9) : null;
    }

    /** Kemiripan 0..1 — yang terbaik antara urutan asli dan urutan kata terurut ("daya teknik" ≈ "teknik daya"). */
    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        $sorted = function (string $value): string {
            $tokens = explode(' ', $value);
            sort($tokens);

            return implode(' ', $tokens);
        };

        similar_text($a, $b, $plain);
        similar_text($sorted($a), $sorted($b), $reordered);

        return max($plain, $reordered) / 100;
    }

    /**
     * @param  array<int, array<string, mixed>>  $customers  minimal: id, name; opsional: perusahaan, nik_npwp, phone, telephone
     * @return array<int, array{ids: array<int, int>, reasons: array<int, string>}> grup dengan >= 2 anggota, terurut menurut id terkecil
     */
    public function groups(array $customers, float $minScore = 0.88): array
    {
        $parent = [];
        $edges = [];   // [idA, idB, alasan]

        foreach ($customers as $row) {
            $parent[(int) $row['id']] = (int) $row['id'];
        }

        // (a) nama sama — nama maupun perusahaan
        $byName = [];
        foreach ($customers as $row) {
            foreach (array_unique(array_filter([self::normalizeName($row['name'] ?? null), self::normalizeName($row['perusahaan'] ?? null)], fn ($v) => strlen($v) >= 3)) as $key) {
                $byName[$key][] = (int) $row['id'];
            }
        }
        $exactPairs = [];
        foreach ($byName as $ids) {
            $ids = array_values(array_unique($ids));
            for ($i = 1; $i < count($ids); $i++) {
                $edges[] = [$ids[0], $ids[$i], 'nama sama'];
            }
            // Semua pasangan dalam kelompok "nama sama" tidak perlu dibandingkan lagi secara fuzzy.
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $exactPairs[min($ids[$i], $ids[$j]).'-'.max($ids[$i], $ids[$j])] = true;
                }
            }
        }

        // (b) NPWP/NIK sama (≥ 15 digit, bukan angka berulang)
        $byTaxId = [];
        foreach ($customers as $row) {
            $digits = self::digits($row['nik_npwp'] ?? null);
            if (strlen($digits) >= 15 && count(array_unique(str_split($digits))) > 2) {
                $byTaxId[$digits][] = (int) $row['id'];
            }
        }
        foreach ($byTaxId as $ids) {
            for ($i = 1; $i < count($ids); $i++) {
                $edges[] = [$ids[0], $ids[$i], 'NPWP/NIK sama'];
            }
        }

        // (c) telepon sama (nomor bersama oleh banyak customer diabaikan)
        $byPhone = [];
        foreach ($customers as $row) {
            foreach (array_unique(array_filter([self::phoneKey($row['phone'] ?? null), self::phoneKey($row['telephone'] ?? null)])) as $key) {
                $byPhone[$key][] = (int) $row['id'];
            }
        }
        foreach ($byPhone as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2 || count($ids) > self::MAX_SHARED_PHONE) {
                continue;
            }
            for ($i = 1; $i < count($ids); $i++) {
                $edges[] = [$ids[0], $ids[$i], 'telepon sama'];
            }
        }

        // (d) nama mirip — hanya dalam ember huruf awal yang sama (tidak membandingkan semua pasangan)
        $names = [];
        foreach ($customers as $row) {
            $normalized = self::normalizeName($row['name'] ?? null);
            if (strlen($normalized) >= 4) {
                $names[(int) $row['id']] = $normalized;
            }
        }
        $buckets = [];
        foreach ($names as $id => $name) {
            $tokens = explode(' ', $name);
            sort($tokens);
            $buckets['p:'.substr(str_replace(' ', '', $name), 0, 3)][] = $id;
            $buckets['s:'.substr(str_replace(' ', '', implode(' ', $tokens)), 0, 3)][] = $id;
        }
        $comparedPairs = [];
        foreach ($buckets as $ids) {
            $ids = array_values(array_unique($ids));
            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    $a = min($ids[$i], $ids[$j]);
                    $b = max($ids[$i], $ids[$j]);
                    $pair = "{$a}-{$b}";
                    if (isset($comparedPairs[$pair]) || isset($exactPairs[$pair])) {
                        continue;
                    }
                    $comparedPairs[$pair] = true;

                    $score = self::similarity($names[$a], $names[$b]);
                    if ($score >= $minScore) {
                        $edges[] = [$a, $b, 'nama mirip ('.round($score * 100).'%)'];
                    }
                }
            }
        }

        // Union-find
        $find = function (int $id) use (&$parent, &$find): int {
            while ($parent[$id] !== $id) {
                $parent[$id] = $parent[$parent[$id]];
                $id = $parent[$id];
            }

            return $id;
        };
        foreach ($edges as [$a, $b]) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[max($ra, $rb)] = min($ra, $rb);
            }
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find($id)]['ids'][] = $id;
        }
        foreach ($edges as [$a, $b, $reason]) {
            $groups[$find($a)]['reasons'][$reason] = true;
        }

        $result = [];
        foreach ($groups as $group) {
            if (count($group['ids']) < 2) {
                continue;
            }
            sort($group['ids']);
            $result[] = ['ids' => $group['ids'], 'reasons' => array_keys($group['reasons'] ?? [])];
        }

        usort($result, fn ($x, $y) => $x['ids'][0] <=> $y['ids'][0]);

        return $result;
    }
}
