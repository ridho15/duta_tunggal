<?php

use App\Support\MemoryLimit;

afterEach(function () {
    if (isset($this->originalLimit)) {
        ini_set('memory_limit', $this->originalLimit);
    }
});

it('toBytes membaca notasi php.ini', function (string $value, int $bytes) {
    expect(MemoryLimit::toBytes($value))->toBe($bytes);
})->with([
    ['-1', -1],
    ['', -1],
    ['512M', 512 * 1024 * 1024],
    ['1G', 1024 * 1024 * 1024],
    ['256K', 256 * 1024],
    ['134217728', 134217728],
    ['512m', 512 * 1024 * 1024],
]);

it('raiseTo tidak pernah menurunkan batas tak terbatas (-1)', function () {
    $this->originalLimit = ini_get('memory_limit');
    ini_set('memory_limit', '-1');

    MemoryLimit::raiseTo('512M');

    expect(ini_get('memory_limit'))->toBe('-1');
});

it('raiseTo tidak menurunkan batas yang lebih besar dari target', function () {
    $this->originalLimit = ini_get('memory_limit');
    ini_set('memory_limit', '2048M');

    MemoryLimit::raiseTo('512M');

    expect(ini_get('memory_limit'))->toBe('2048M');
});

it('raiseTo menaikkan batas yang lebih rendah dari target', function () {
    $this->originalLimit = ini_get('memory_limit');
    ini_set('memory_limit', '256M');

    MemoryLimit::raiseTo('512M');

    expect(ini_get('memory_limit'))->toBe('512M');
});
