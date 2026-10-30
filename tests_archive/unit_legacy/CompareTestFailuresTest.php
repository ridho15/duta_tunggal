<?php

// Tes Unit tidak mem-boot Laravel: jangan memakai base_path().
require_once dirname(__DIR__, 2).'/scripts/lib/TestFailureTools.php';

const JUNIT_FIXTURE = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="CLI Arguments" tests="5">
    <testsuite name="Tests\Feature\ContohTest" file="tests/Feature/ContohTest.php" tests="5">
      <testcase name="it lolos" class="Tests\Feature\ContohTest" classname="Tests.Feature.ContohTest" assertions="1" time="0.1"/>
      <testcase name="it gagal assert" class="Tests\Feature\ContohTest" classname="Tests.Feature.ContohTest" assertions="1" time="0.1">
        <failure type="PHPUnit\Framework\ExpectationFailedException">Failed asserting that 1 is identical to 2.</failure>
      </testcase>
      <testcase name="it error" class="Tests\Feature\ContohTest" classname="Tests.Feature.ContohTest" assertions="0" time="0.1">
        <error type="RuntimeException">boom</error>
      </testcase>
      <testcase name="it dilewati" class="Tests\Feature\ContohTest" classname="Tests.Feature.ContohTest" assertions="0" time="0.1">
        <skipped/>
      </testcase>
      <testcase name="it gagal assert" class="Tests\Feature\ContohTest" classname="Tests.Feature.ContohTest" assertions="1" time="0.1">
        <failure type="PHPUnit\Framework\ExpectationFailedException">duplikat nama</failure>
      </testcase>
    </testsuite>
  </testsuite>
</testsuites>
XML;

it('parseJunit membaca status lolos/gagal/error/dilewati', function () {
    $results = TestFailureTools::parseJunit(JUNIT_FIXTURE);

    expect($results)->toHaveCount(5)
        ->and(array_column($results, 'status'))->toBe(['passed', 'failed', 'error', 'skipped', 'failed'])
        ->and($results[0]['name'])->toBe('Tests\Feature\ContohTest :: it lolos');
});

it('failedNames hanya memuat gagal dan error, unik dan terurut', function () {
    $names = TestFailureTools::failedNames(TestFailureTools::parseJunit(JUNIT_FIXTURE));

    expect($names)->toBe([
        'Tests\Feature\ContohTest :: it error',
        'Tests\Feature\ContohTest :: it gagal assert',
    ]);
});

it('parseJunit mengembalikan kosong untuk XML rusak atau kosong (dianggap crash oleh runner)', function () {
    expect(TestFailureTools::parseJunit(''))->toBe([])
        ->and(TestFailureTools::parseJunit('<testsuites><testsuite>'))->toBe([]);
});

it('diff memisahkan kegagalan baru, diperbaiki, dan tetap gagal', function () {
    $diff = TestFailureTools::diff(['A :: a', 'B :: b', 'C :: c'], ['B :: b', 'C :: c', 'D :: d']);

    expect($diff['new'])->toBe(['D :: d'])
        ->and($diff['fixed'])->toBe(['A :: a'])
        ->and($diff['still'])->toBe(['B :: b', 'C :: c']);
});

it('berkas baseline: tulis lalu baca kembali, komentar dan baris kosong diabaikan', function () {
    $path = sys_get_temp_dir().'/baseline-'.uniqid().'.txt';

    TestFailureTools::writeNamesFile($path, ['Z :: z', 'A :: a', 'A :: a'], ['judul', 'commit: abc']);

    expect(file_get_contents($path))->toContain('# judul')
        ->and(TestFailureTools::readNamesFile($path))->toBe(['A :: a', 'Z :: z']);

    unlink($path);
});

it('chunk membagi berkas per ukuran dan tidak pernah membuat potongan kosong', function () {
    expect(TestFailureTools::chunk(['a', 'b', 'c', 'd', 'e'], 2))->toBe([['a', 'b'], ['c', 'd'], ['e']])
        ->and(TestFailureTools::chunk([], 3))->toBe([])
        ->and(TestFailureTools::chunk(['a', 'b'], 0))->toBe([['a'], ['b']]);
});

it('compare-test-failures: exit 1 bila ada kegagalan baru, 0 bila tidak', function () {
    $dir = sys_get_temp_dir().'/cmp-'.uniqid();
    mkdir($dir);
    file_put_contents("{$dir}/baseline.txt", "# komentar\nA :: a\nB :: b\n");
    file_put_contents("{$dir}/sama.txt", "A :: a\n");
    file_put_contents("{$dir}/baru.txt", "A :: a\nC :: c\n");

    $script = escapeshellarg(dirname(__DIR__, 2).'/scripts/compare-test-failures.php');
    exec(PHP_BINARY." {$script} {$dir}/baseline.txt {$dir}/sama.txt 2>&1", $out1, $exit1);
    exec(PHP_BINARY." {$script} {$dir}/baseline.txt {$dir}/baru.txt 2>&1", $out2, $exit2);

    expect($exit1)->toBe(0)
        ->and(implode("\n", $out1))->toContain('BARU: 0')->toContain('Diperbaiki: 1')
        ->and($exit2)->toBe(1)
        ->and(implode("\n", $out2))->toContain('KEGAGALAN BARU')->toContain('C :: c');
});

it('runner terpotong: menjalankan sebuah berkas uji dan menulis failures.txt + summary.json', function () {
    $out = sys_get_temp_dir().'/run-'.uniqid();
    $script = escapeshellarg(dirname(__DIR__, 2).'/scripts/run-tests-chunked.php');

    exec(PHP_BINARY." {$script} --size=5 --dirs=tests/Unit --only=tests/Unit/MemoryLimitTest --out=".escapeshellarg($out).' 2>&1', $lines, $exit);

    $summary = json_decode((string) file_get_contents("{$out}/summary.json"), true);

    expect($exit)->toBe(0)
        ->and($summary['tests'])->toBe(10)
        ->and($summary['failed'])->toBe(0)
        ->and($summary['crashes'])->toBe([])
        ->and(trim((string) file_get_contents("{$out}/failures.txt")))->toBe('');
});

it('runner terpotong: menolak --db yang tidak berakhiran _test', function () {
    $script = escapeshellarg(dirname(__DIR__, 2).'/scripts/run-tests-chunked.php');

    exec(PHP_BINARY." {$script} --db=duta_tunggal 2>&1", $lines, $exit);

    expect($exit)->toBe(2)->and(implode("\n", $lines))->toContain('_test');
});
