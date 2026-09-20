<?php

/**
 * Alat bersama untuk runner suite terpotong (scripts/run-tests-chunked.php) dan pembanding
 * (scripts/compare-test-failures.php). Tanpa dependensi framework agar dapat dipakai dari CLI biasa dan dari tes.
 */
final class TestFailureTools
{
    /**
     * Baca JUnit XML (format PHPUnit/Pest) → daftar tes dengan status.
     *
     * @return array<int, array{name: string, status: string}> status: passed|failed|error|skipped
     */
    public static function parseJunit(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return [];
        }

        $results = [];
        foreach ($document->xpath('//testcase') ?: [] as $case) {
            $class = (string) ($case['class'] ?? '');
            if ($class === '') {
                $class = str_replace('.', '\\', (string) ($case['classname'] ?? ''));
            }

            $status = 'passed';
            if (isset($case->failure)) {
                $status = 'failed';
            } elseif (isset($case->error)) {
                $status = 'error';
            } elseif (isset($case->skipped)) {
                $status = 'skipped';
            }

            $results[] = ['name' => self::testName($class, (string) $case['name']), 'status' => $status];
        }

        return $results;
    }

    public static function testName(string $class, string $name): string
    {
        return trim(($class !== '' ? $class : '(tanpa kelas)').' :: '.$name);
    }

    /**
     * @param  array<int, array{name: string, status: string}>  $results
     * @return array<int, string> nama tes gagal/error, unik & terurut
     */
    public static function failedNames(array $results): array
    {
        $names = [];
        foreach ($results as $result) {
            if (in_array($result['status'], ['failed', 'error'], true)) {
                $names[$result['name']] = true;
            }
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param  array<int, string>  $baseline
     * @param  array<int, string>  $current
     * @return array{new: array<int, string>, fixed: array<int, string>, still: array<int, string>}
     */
    public static function diff(array $baseline, array $current): array
    {
        return [
            'new' => array_values(array_diff($current, $baseline)),
            'fixed' => array_values(array_diff($baseline, $current)),
            'still' => array_values(array_intersect($baseline, $current)),
        ];
    }

    /** @return array<int, string> nama tes (baris kosong dan komentar `#` diabaikan) */
    public static function readNamesFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        $names = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $names[$line] = true;
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     * @param  array<int, string>  $header  baris komentar (tanpa awalan #)
     */
    public static function writeNamesFile(string $path, array $names, array $header = []): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $lines = array_map(fn (string $h) => '# '.$h, $header);
        sort($names, SORT_STRING);

        file_put_contents($path, implode("\n", array_merge($lines, $names))."\n");
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, array<int, string>>
     */
    public static function chunk(array $files, int $size): array
    {
        return array_values(array_chunk($files, max(1, $size)));
    }
}
