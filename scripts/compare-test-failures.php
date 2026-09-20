<?php

/**
 * Bandingkan daftar tes gagal dengan baseline.
 *
 *   php scripts/compare-test-failures.php tests/baseline-failures.txt storage/test-runs/<run>/failures.txt
 *   php scripts/compare-test-failures.php tests/baseline-failures.txt storage/test-runs/<run>      (folder run)
 *
 * Exit code 1 bila ada kegagalan BARU (atau crash yang tercatat sebagai "CRASH :: …").
 */

require_once __DIR__.'/lib/TestFailureTools.php';

$args = array_slice($_SERVER['argv'], 1);

if (count($args) !== 2) {
    fwrite(STDERR, "Pemakaian: php scripts/compare-test-failures.php <baseline> <failures.txt|folder-run>\n");
    exit(2);
}

[$baselinePath, $currentPath] = $args;

if (is_dir($currentPath)) {
    $currentPath = rtrim($currentPath, '/').'/failures.txt';
}

try {
    $baseline = TestFailureTools::readNamesFile($baselinePath);
    $current = TestFailureTools::readNamesFile($currentPath);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(2);
}

$diff = TestFailureTools::diff($baseline, $current);

echo sprintf(
    "Baseline: %d gagal · Sekarang: %d gagal · BARU: %d · Diperbaiki: %d · Tetap gagal: %d\n",
    count($baseline),
    count($current),
    count($diff['new']),
    count($diff['fixed']),
    count($diff['still'])
);

if ($diff['new'] !== []) {
    echo "\n== KEGAGALAN BARU (tidak ada di baseline) ==\n";
    foreach ($diff['new'] as $name) {
        echo "  - {$name}\n";
    }
}

if ($diff['fixed'] !== []) {
    echo "\n== Diperbaiki (ada di baseline, kini lolos) ==\n";
    foreach ($diff['fixed'] as $name) {
        echo "  + {$name}\n";
    }
}

exit($diff['new'] === [] ? 0 : 1);
