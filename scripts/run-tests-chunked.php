<?php

/**
 * Jalankan seluruh suite Pest dalam potongan berkas (proses terpisah per potongan) lalu gabungkan hasilnya.
 *
 * Kenapa terpotong: satu proses untuk ±350 berkas menghabiskan memori (kebocoran nyata di suite) dan crash sebelum selesai;
 * potongan kecil selesai tuntas dan tetap bisa dibandingkan dengan baseline.
 *
 *   php scripts/run-tests-chunked.php                                   (semua, potongan 30 berkas)
 *   php scripts/run-tests-chunked.php --size=20 --dirs=tests/Feature
 *   php scripts/run-tests-chunked.php --compare=tests/baseline-failures.txt
 *   php scripts/run-tests-chunked.php --write-baseline=tests/baseline-failures.txt
 *   php scripts/run-tests-chunked.php --db=duta_tunggal_lain_test        (DB uji lain, mis. untuk run paralel)
 *
 * Opsi:
 *   --size=N              berkas per potongan (default 30)
 *   --dirs=a,b            folder uji (default tests/Unit,tests/Feature)
 *   --out=DIR             folder keluaran (default storage/test-runs/<waktu>)
 *   --compare=FILE        bandingkan dengan baseline; exit 1 bila ada kegagalan BARU atau crash
 *   --write-baseline=FILE tulis daftar gagal hasil run ini sebagai baseline
 *   --db=NAMA_test        setel DB_DATABASE proses anak (WAJIB berakhiran _test)
 *   --only=SUBSTR         hanya berkas yang path-nya memuat teks ini (untuk uji cepat)
 *
 * JANGAN menjalankan dua runner pada database uji yang sama (RefreshDatabase menjalankan migrate:fresh).
 */

require_once __DIR__.'/lib/TestFailureTools.php';

$root = dirname(__DIR__);
chdir($root);

$options = [];
foreach (array_slice($_SERVER['argv'], 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $options[$m[1]] = $m[2] ?? '1';
    }
}

$size = max(1, (int) ($options['size'] ?? 30));
$dirs = array_filter(array_map('trim', explode(',', $options['dirs'] ?? 'tests/Unit,tests/Feature')));
$out = $options['out'] ?? ($root.'/storage/test-runs/'.date('Ymd-His'));
$db = $options['db'] ?? null;
$only = $options['only'] ?? null;

if ($db !== null && ! str_ends_with($db, '_test')) {
    fwrite(STDERR, "--db harus berakhiran _test (penjaga keamanan tests/TestCase.php). Diberikan: {$db}\n");
    exit(2);
}

if (! is_dir($out)) {
    mkdir($out, 0775, true);
}

// Daftar berkas uji (terurut → potongan deterministik)
$files = [];
foreach ($dirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
}
sort($files, SORT_STRING);

if ($only !== null) {
    $files = array_values(array_filter($files, fn ($f) => str_contains($f, $only)));
}

if ($files === []) {
    fwrite(STDERR, "Tidak ada berkas uji ditemukan.\n");
    exit(2);
}

$env = getenv();
if ($db !== null) {
    $env['DB_DATABASE'] = $db;
}

/** Jalankan pest untuk sekumpulan berkas → [exitCode, path XML]. */
$runPest = function (array $group, string $tag) use ($out, $env): array {
    $xml = "{$out}/{$tag}.xml";
    $log = "{$out}/{$tag}.txt";
    @unlink($xml);

    $command = array_merge(
        [PHP_BINARY, '-d', 'memory_limit=-1', 'vendor/bin/pest', '--log-junit='.$xml],
        $group
    );

    $process = proc_open(
        $command,
        [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
        $pipes,
        null,
        $env
    );

    $exit = is_resource($process) ? proc_close($process) : 255;

    return [$exit, $xml];
};

$started = microtime(true);
echo "Membersihkan cache konfigurasi…\n";
passthru(escapeshellarg(PHP_BINARY).' artisan config:clear --ansi', $clearExit);

$chunks = TestFailureTools::chunk($files, $size);
$allResults = [];
$crashes = [];

echo sprintf("%d berkas dalam %d potongan (ukuran %d)%s → %s\n\n", count($files), count($chunks), $size, $db ? " · DB {$db}" : '', $out);

foreach ($chunks as $index => $group) {
    $tag = sprintf('chunk-%02d', $index + 1);
    $t0 = microtime(true);
    [$exit, $xml] = $runPest($group, $tag);

    $results = is_file($xml) ? TestFailureTools::parseJunit((string) file_get_contents($xml)) : [];

    // Potongan tanpa hasil yang dapat dibaca = crash (mis. kehabisan memori) → isolasi per berkas.
    if ($results === []) {
        echo sprintf("  [%s] TANPA HASIL (exit %d) — mengisolasi per berkas…\n", $tag, $exit);
        foreach ($group as $i => $file) {
            [$fileExit, $fileXml] = $runPest([$file], sprintf('%s-file-%02d', $tag, $i + 1));
            $fileResults = is_file($fileXml) ? TestFailureTools::parseJunit((string) file_get_contents($fileXml)) : [];
            if ($fileResults === []) {
                $crashes[] = $file;
                $allResults[] = ['name' => "CRASH :: {$file}", 'status' => 'error'];
            } else {
                $allResults = array_merge($allResults, $fileResults);
            }
        }
    } else {
        $allResults = array_merge($allResults, $results);
    }

    $failed = count(TestFailureTools::failedNames($results));
    echo sprintf("  [%s] %d berkas · %d tes · %d gagal · %.0f dtk\n", $tag, count($group), count($results), $failed, microtime(true) - $t0);
}

$failedNames = TestFailureTools::failedNames($allResults);
$counts = array_count_values(array_column($allResults, 'status'));
$duration = microtime(true) - $started;

file_put_contents($out.'/failures.txt', implode("\n", $failedNames).($failedNames ? "\n" : ''));
file_put_contents($out.'/summary.json', json_encode([
    'files' => count($files),
    'chunks' => count($chunks),
    'tests' => count($allResults),
    'passed' => $counts['passed'] ?? 0,
    'failed' => ($counts['failed'] ?? 0) + ($counts['error'] ?? 0),
    'skipped' => $counts['skipped'] ?? 0,
    'crashes' => $crashes,
    'seconds' => round($duration),
    'php' => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo sprintf(
    "\nSelesai dalam %.1f menit: %d tes · lolos %d · gagal %d · dilewati %d · crash %d\nDaftar gagal: %s/failures.txt\n",
    $duration / 60,
    count($allResults),
    $counts['passed'] ?? 0,
    count($failedNames),
    $counts['skipped'] ?? 0,
    count($crashes),
    $out
);

if (isset($options['write-baseline'])) {
    $commit = trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null'));
    TestFailureTools::writeNamesFile($options['write-baseline'], $failedNames, [
        'Baseline tes gagal (dibuat oleh scripts/run-tests-chunked.php)',
        'commit: '.($commit ?: '-').' · tanggal: '.date('Y-m-d').' · PHP '.PHP_VERSION,
        'Format: Kelas :: nama tes. Perbarui HANYA lewat commit terpisah yang menjelaskan alasannya.',
    ]);
    echo "Baseline ditulis: {$options['write-baseline']}\n";
}

$exitCode = $crashes === [] ? 0 : 1;

if (isset($options['compare'])) {
    passthru(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/compare-test-failures.php').' '
        .escapeshellarg($options['compare']).' '.escapeshellarg($out.'/failures.txt'),
        $compareExit
    );
    $exitCode = max($exitCode, (int) $compareExit);
}

exit($exitCode);
