<?php

/**
 * Profiler request (T1.6) — alat ukur untuk menemukan aksi lambat SEBELUM dioptimasi.
 *
 * DEFAULT MATI. Aktifkan sementara di UAT dengan PERF_PROFILE=true, lakukan aksi yang terasa lambat, lalu jalankan
 *   php artisan perf:report --since=1d
 * Tidak menjalankan aksi apa pun sendiri — hanya mencatat request yang melewati ambang ke storage/logs/perf-*.log
 * (SQL dicatat TANPA nilai binding agar data tidak bocor).
 */
return [

    'enabled' => (bool) env('PERF_PROFILE', false),

    // Catat hanya request yang durasinya >= min_ms ATAU jumlah query >= min_queries
    'min_ms' => (int) env('PERF_MIN_MS', 300),
    'min_queries' => (int) env('PERF_MIN_QUERIES', 40),

    // Proporsi request yang diprofil (1.0 = semua)
    'sample' => (float) env('PERF_SAMPLE', 1.0),

    // SQL identik (setelah normalisasi) berulang >= nilai ini dianggap indikasi N+1
    'duplicate_threshold' => (int) env('PERF_DUPLICATE_THRESHOLD', 10),

    'channel' => env('PERF_CHANNEL', 'perf'),
];
