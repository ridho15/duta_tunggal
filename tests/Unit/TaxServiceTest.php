<?php

use App\Services\TaxService;

test('non pajak returns same amount', function () {
    $res = TaxService::compute(100000.0, 12.0, 'Non Pajak');

    expect($res['dpp'])->toBe(100000.0)
        ->and($res['ppn'])->toBe(0.0)
        ->and($res['total'])->toBe(100000.0);
});

test('eksklusif calculates ppn and total', function () {
    $res = TaxService::compute(950000.0, 12.0, 'Eksklusif');

    expect(round($res['ppn'], 2))->toBe(114000.00)
        ->and(round($res['total'], 2))->toBe(1064000.00)
        ->and(round($res['dpp'], 2))->toBe(950000.00);
});

test('inklusif computes dpp and ppn correctly', function () {
    $res = TaxService::compute(1000000.0, 12.0, 'Inklusif');

    expect(round($res['dpp'], 2))->toBe(892857.14)
        ->and(round($res['ppn'], 2))->toBe(107142.86)
        ->and(round($res['total'], 2))->toBe(1000000.00);
});
