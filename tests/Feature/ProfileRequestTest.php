<?php

/**
 * T1.6 — profiler request (default mati) + `perf:report`.
 * Yang dijamin: mati = tidak ada efek; aktif = satu baris JSON lengkap untuk request lambat/banyak query, TANPA nilai binding;
 * payload Livewire diurai (komponen, metode, aksi) tanpa membocorkan argumen; laporan mengagregasi per aksi.
 */

use App\Support\Perf\ProfileRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function perfSetup(array $perf = []): string
{
    $file = sys_get_temp_dir().'/perf-test-'.uniqid().'.log';

    config(array_merge([
        'logging.channels.perf' => ['driver' => 'single', 'path' => $file, 'level' => 'info', 'replace_placeholders' => true],
        'perf.enabled' => true, 'perf.min_ms' => 600000, 'perf.min_queries' => 40, 'perf.sample' => 1.0, 'perf.duplicate_threshold' => 10,
    ], collect($perf)->mapWithKeys(fn ($v, $k) => ["perf.{$k}" => $v])->all()));
    Log::forgetChannel('perf');

    Route::middleware('web')->get('/__perf/queries/{n}', function (int $n) {
        for ($i = 0; $i < $n; $i++) {
            DB::select('select ? as rahasia', ['rahasia@contoh.test']);
        }

        return 'ok';
    });
    Route::middleware('web')->post('/__perf/livewire', fn () => response()->json(['ok' => true]));

    return $file;
}

/** Baris JSON profiler pertama pada berkas log (atau null). */
function perfEntries(string $file): array
{
    if (! is_file($file)) {
        return [];
    }

    $entries = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $pos = strpos($line, 'perf {');
        if ($pos !== false) {
            $entries[] = json_decode(substr($line, $pos + 5), true);
        }
    }

    return $entries;
}

it('DEFAULT MATI: tanpa PERF_PROFILE tidak ada log, tidak ada perekaman', function () {
    $file = perfSetup(['enabled' => false]);

    $this->get('/__perf/queries/60')->assertOk();

    expect(config('perf.enabled'))->toBeFalse()
        ->and(app(ProfileRecorder::class)->isRunning())->toBeFalse()
        ->and(perfEntries($file))->toBe([]);
});

it('config bawaan: profiler nonaktif', function () {
    // Nilai default berkas config (bukan yang disetel tes lain)
    $defaults = require config_path('perf.php');

    expect($defaults['enabled'])->toBeFalse();
});

it('aktif: request dengan banyak query dicatat sebagai satu baris JSON lengkap, dengan SQL berulang terdeteksi', function () {
    $file = perfSetup();

    $this->get('/__perf/queries/45')->assertOk();

    $entries = perfEntries($file);

    expect($entries)->toHaveCount(1);

    $entry = $entries[0];
    expect($entry)->toHaveKeys(['ts', 'route', 'method', 'status', 'ms', 'queries', 'db_ms', 'peak_mb', 'user_id', 'cabang_id', 'component', 'calls', 'action', 'slow', 'duplicates'])
        ->and($entry['route'])->toBe('/__perf/queries/45')
        ->and($entry['method'])->toBe('GET')
        ->and($entry['status'])->toBe(200)
        ->and($entry['queries'])->toBeGreaterThanOrEqual(45)
        ->and($entry['slow'])->not->toBeEmpty()->and(count($entry['slow']))->toBeLessThanOrEqual(5)
        ->and($entry['duplicates'][0]['count'])->toBeGreaterThanOrEqual(45)
        ->and($entry['duplicates'][0]['sql'])->toContain('select ? as rahasia');
});

it('request di bawah ambang tidak dicatat', function () {
    $file = perfSetup();

    $this->get('/__perf/queries/3')->assertOk();

    expect(perfEntries($file))->toBe([]);
});

it('request lambat (di atas min_ms) dicatat walau query sedikit', function () {
    $file = perfSetup(['min_ms' => 0, 'min_queries' => 1000]);

    $this->get('/__perf/queries/2')->assertOk();

    expect(perfEntries($file))->toHaveCount(1);
});

it('nilai binding TIDAK pernah masuk log', function () {
    $file = perfSetup();

    $this->get('/__perf/queries/45')->assertOk();

    expect(file_get_contents($file))->not->toContain('rahasia@contoh.test');
});

it('sample 0 tidak memprofil apa pun', function () {
    $file = perfSetup(['sample' => 0.0]);

    $this->get('/__perf/queries/60')->assertOk();

    expect(perfEntries($file))->toBe([]);
});

it('payload Livewire diurai: komponen, metode, dan aksi terbuka; argumen sensitif tidak dicatat', function () {
    $snapshot = json_encode([
        'data' => ['mountedTableActions' => [[['set_delivered', ['s' => 'arr']]]], 'tableSearch' => 'rahasia'],
        'memo' => ['name' => 'app.filament.resources.delivery-schedule-resource.pages.list-delivery-schedules'],
    ]);

    $described = ProfileRecorder::describeLivewire([[
        'snapshot' => $snapshot,
        'calls' => [
            ['method' => 'callMountedTableAction', 'params' => [['token' => 'rahasia-besar']]],
            ['method' => 'mountTableAction', 'params' => ['set_delivered', '12']],
            ['method' => '__dispatch', 'params' => [str_repeat('x', 200)]],
        ],
    ]]);

    expect($described['component'])->toBe('app.filament.resources.delivery-schedule-resource.pages.list-delivery-schedules')
        ->and($described['action'])->toBe('set_delivered')
        ->and($described['calls'])->toBe(['callMountedTableAction', 'mountTableAction(set_delivered)', '__dispatch'])
        ->and(json_encode($described))->not->toContain('rahasia');

    expect(ProfileRecorder::describeLivewire(null))->toBe(['component' => null, 'calls' => [], 'action' => null])
        ->and(ProfileRecorder::describeLivewire([['snapshot' => 'bukan-json']]))->toBe(['component' => null, 'calls' => [], 'action' => null]);
});

it('request Livewire tercatat dengan komponen dan metode (ambang 0 ms)', function () {
    $file = perfSetup(['min_ms' => 0]);

    $this->postJson('/__perf/livewire', ['components' => [[
        'snapshot' => json_encode(['data' => [], 'memo' => ['name' => 'app.contoh.komponen']]),
        'calls' => [['method' => 'callMountedAction', 'params' => []]],
    ]]])->assertOk();

    $entry = perfEntries($file)[0];

    expect($entry['component'])->toBe('app.contoh.komponen')->and($entry['calls'])->toBe(['callMountedAction']);
});

it('normalizeSql merapikan spasi dan meringkas daftar IN agar pengulangan terdeteksi', function () {
    expect(ProfileRecorder::normalizeSql("select *  from a\n where id in (?, ?, ?)"))->toBe('select * from a where id in (?)')
        ->and(ProfileRecorder::normalizeSql('select * from a where id = ?'))->toBe('select * from a where id = ?');
});

// ───────────────────────────── perf:report ─────────────────────────────

function perfLogDir(array $entries): string
{
    $dir = sys_get_temp_dir().'/perf-report-'.uniqid();
    mkdir($dir);
    $lines = array_map(fn (array $e) => '['.now()->toDateTimeString().'] testing.INFO: perf '.json_encode($e).' []', $entries);
    file_put_contents($dir.'/perf-'.now()->toDateString().'.log', implode("\n", $lines)."\nbaris lain bukan profiler\n");

    return $dir;
}

function perfEntry(array $override = []): array
{
    return array_merge([
        'ts' => now()->toIso8601String(), 'route' => 'livewire.update', 'method' => 'POST', 'status' => 200, 'ms' => 1000, 'queries' => 126, 'db_ms' => 300,
        'peak_mb' => 40, 'user_id' => 1, 'cabang_id' => 1, 'component' => 'app.delivery-schedules', 'calls' => ['callMountedTableAction'], 'action' => 'set_delivered',
        'slow' => [], 'duplicates' => [['count' => 40, 'sql' => 'select * from `stock_movements` where `id` = ?']],
    ], $override);
}

it('perf:report mengelompokkan per aksi dengan jumlah, rata-rata, p95, maks, dan SQL berulang', function () {
    $dir = perfLogDir([
        perfEntry(['ms' => 1000]), perfEntry(['ms' => 3000, 'queries' => 200]),
        perfEntry(['component' => 'app.sale-orders', 'calls' => ['mountAction'], 'action' => null, 'ms' => 400, 'queries' => 50, 'duplicates' => []]),
        perfEntry(['component' => null, 'route' => 'pdf-stream', 'method' => 'GET', 'ms' => 700]),
    ]);

    Artisan::call('perf:report', ['--dir' => $dir, '--since' => '1d']);
    $output = Artisan::output();

    expect($output)->toContain('4 request tercatat')
        ->toContain('app.delivery-schedules::callMountedTableAction @set_delivered')
        ->toContain('app.sale-orders::mountAction')
        ->toContain('GET pdf-stream')
        ->toContain('40× select * from `stock_movements`')
        ->toContain('3000');   // maks & p95 aksi terlambat

    // --action menyaring
    Artisan::call('perf:report', ['--dir' => $dir, '--since' => '1d', '--action' => 'sale-orders']);
    expect(Artisan::output())->toContain('app.sale-orders::mountAction')->not->toContain('delivery-schedules');

    File::deleteDirectory($dir);
});

it('perf:report: entri lama di luar --since diabaikan; tanpa data memberi petunjuk; format --since salah ditolak', function () {
    $dir = perfLogDir([perfEntry(['ts' => now()->subDays(3)->toIso8601String()])]);

    expect(Artisan::call('perf:report', ['--dir' => $dir, '--since' => '1d']))->toBe(0);
    expect(Artisan::output())->toContain('Tidak ada data profiler')->toContain('PERF_PROFILE=true');

    expect(Artisan::call('perf:report', ['--dir' => $dir, '--since' => 'kemarin-sore-lah']))->toBe(1);

    File::deleteDirectory($dir);
});

it('rute nyata aplikasi (halaman login panel) ikut terekam saat aktif dan tetap berfungsi normal', function () {
    $file = perfSetup(['min_ms' => 0]);

    $this->get('/admin/login')->assertOk();

    $entries = perfEntries($file);

    expect($entries)->not->toBeEmpty()
        ->and($entries[0]['status'])->toBe(200)
        ->and($entries[0]['route'])->toContain('login');
});
