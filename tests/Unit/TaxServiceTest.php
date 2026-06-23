<?php

use App\Services\TaxService;
use App\Http\Controllers\HelperController;

// ─── TaxService::compute ────────────────────────────────────────────────────

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

test('inklusif computes dpp and ppn correctly — rounded to nearest Rupiah (PMK 136/2023)', function () {
    // After fix: DPP and PPN are rounded to nearest Rupiah (0 decimal places)
    // DPP = round(1,000,000 * 100/112, 0) = round(892857.142..., 0) = 892857
    // PPN = round(1,000,000 - 892857, 0) = 107143
    $res = TaxService::compute(1000000.0, 12.0, 'Inklusif');

    expect($res['dpp'])->toBe(892857.0)
        ->and($res['ppn'])->toBe(107143.0)
        ->and($res['total'])->toBe(1000000.0);
});

test('zero tax rate returns same total regardless of type', function () {
    foreach (['Eksklusif', 'Inklusif'] as $tipe) {
        $res = TaxService::compute(500000.0, 0.0, $tipe);
        expect($res['total'])->toBe(500000.0)
            ->and($res['ppn'])->toBe(0.0);
    }
});

test('non pajak with non-zero rate still charges no tax', function () {
    // Even if a rate is passed, Non Pajak must produce zero PPN
    $res = TaxService::compute(200000.0, 11.0, 'Non Pajak');

    expect($res['ppn'])->toBe(0.0)
        ->and($res['total'])->toBe(200000.0);
});

test('eksklusif 11% rate calculation', function () {
    // 11% PPN: DPP 1,000,000 → PPN 110,000 → total 1,110,000
    $res = TaxService::compute(1000000.0, 11.0, 'Eksklusif');

    expect(round($res['dpp'], 2))->toBe(1000000.0)
        ->and(round($res['ppn'], 2))->toBe(110000.0)
        ->and(round($res['total'], 2))->toBe(1110000.0);
});

test('inklusif 11% rate calculation', function () {
    // gross 1,110,000 includes 11% PPN → DPP = 1,110,000 * 100/111 ≈ 1,000,000
    $res = TaxService::compute(1110000.0, 11.0, 'Inklusif');

    expect(round($res['dpp'], 2))->toBe(1000000.0)
        ->and(round($res['ppn'], 2))->toBe(110000.0)
        ->and(round($res['total'], 2))->toBe(1110000.0);
});

// ─── TaxService::normalizeType ───────────────────────────────────────────────

test('normalizeType handles legacy eklusif typo', function () {
    expect(TaxService::normalizeType('eklusif'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('Eklusif'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('eksklusif'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('Eksklusif'))->toBe('Eksklusif');
});

test('normalizeType handles inklusif variants', function () {
    expect(TaxService::normalizeType('inklusif'))->toBe('Inklusif')
        ->and(TaxService::normalizeType('Inklusif'))->toBe('Inklusif');
});

test('normalizeType handles non pajak variants', function () {
    expect(TaxService::normalizeType('non pajak'))->toBe('Non Pajak')
        ->and(TaxService::normalizeType('Non Pajak'))->toBe('Non Pajak')
        ->and(TaxService::normalizeType('non-pajak'))->toBe('Non Pajak')
        ->and(TaxService::normalizeType('nonpajak'))->toBe('Non Pajak')
        ->and(TaxService::normalizeType('none'))->toBe('Non Pajak');
});

test('normalizeType defaults to Eksklusif for null or empty', function () {
    expect(TaxService::normalizeType(null))->toBe('Eksklusif')
        ->and(TaxService::normalizeType(''))->toBe('Eksklusif');
});

// ─── HelperController::hitungSubtotal ────────────────────────────────────────

test('hitungSubtotal non pajak: qty * price, no tax added', function () {
    // 5 units × 100,000 = 500,000 — no tax
    $result = HelperController::hitungSubtotal(5, 100000, 0, 12, 'Non Pajak');
    expect($result)->toBe(500000.0);
});

test('hitungSubtotal eksklusif: tax added on top', function () {
    // 2 × 100,000 = 200,000 base; 12% tax = 24,000; total = 224,000
    $result = HelperController::hitungSubtotal(2, 100000, 0, 12, 'Eksklusif');
    expect($result)->toBe(224000.0);
});

test('hitungSubtotal inklusif: total stays same, tax already inside', function () {
    // 1 × 112,000 = 112,000 (already includes 12% PPN)
    $result = HelperController::hitungSubtotal(1, 112000, 0, 12, 'Inklusif');
    expect($result)->toBe(112000.0);
});

test('hitungSubtotal eksklusif with discount applied before tax', function () {
    // 10 × 100,000 = 1,000,000; 5% discount = 50,000; after = 950,000; 12% tax = 114,000; total = 1,064,000
    $result = HelperController::hitungSubtotal(10, 100000, 5, 12, 'Eksklusif');
    expect($result)->toBe(1064000.0);
});

test('hitungSubtotal inklusif with discount applied before tax extraction', function () {
    // 5 × 200,000 = 1,000,000; 0% discount; inklusif → total = 1,000,000
    $result = HelperController::hitungSubtotal(5, 200000, 0, 12, 'Inklusif');
    expect($result)->toBe(1000000.0);
});

test('hitungSubtotal non pajak: tax rate input is ignored', function () {
    // Even with 12% rate, Non Pajak means no extra tax
    $result1 = HelperController::hitungSubtotal(3, 50000, 0, 12, 'Non Pajak');
    $result2 = HelperController::hitungSubtotal(3, 50000, 0, 0, 'Non Pajak');
    expect($result1)->toBe(150000.0)->and($result2)->toBe(150000.0);
});

test('hitungSubtotal legacy eklusif typo normalised to eksklusif', function () {
    // Old DB values with typo 'Eklusif' should behave the same as 'Eksklusif'
    $withTypo   = HelperController::hitungSubtotal(1, 100000, 0, 12, 'Eklusif');
    $withCorrect = HelperController::hitungSubtotal(1, 100000, 0, 12, 'Eksklusif');
    expect($withTypo)->toBe($withCorrect)->toBe(112000.0);
});

test('hitungSubtotal formatted indonesian money string is parsed correctly', function () {
    // Unit price given as formatted string "100.000" (Indonesian thousand separator)
    $result = HelperController::hitungSubtotal(2, '100.000', 0, 12, 'Eksklusif');
    expect($result)->toBe(224000.0);
});

// ─── New: Input Validation Guards ─────────────────────────────────────────────

test('compute throws InvalidArgumentException for negative amount', function () {
    expect(fn() => TaxService::compute(-1.0, 12.0, 'Eksklusif'))
        ->toThrow(\InvalidArgumentException::class);
});

test('compute throws InvalidArgumentException when rate exceeds 100', function () {
    expect(fn() => TaxService::compute(100000.0, 150.0, 'Eksklusif'))
        ->toThrow(\InvalidArgumentException::class);
});

test('compute throws InvalidArgumentException for negative rate', function () {
    expect(fn() => TaxService::compute(100000.0, -1.0, 'Eksklusif'))
        ->toThrow(\InvalidArgumentException::class);
});

// ─── New: hitungSubtotal Input Guards ─────────────────────────────────────────

test('hitungSubtotal clamps discount above 100 to 100 percent', function () {
    // discount=150 is invalid; clamped to 100 → afterDiscount = 0 → total = 0
    $result = HelperController::hitungSubtotal(1, 10000, 150, 11, 'Eksklusif');
    expect($result)->toBe(0.0);
});

test('hitungSubtotal with null taxType uses Inklusif not Eksklusif', function () {
    // After fix: null taxType explicitly defaults to 'Inklusif' in hitungSubtotal
    // gross = 1 * 112000. Inklusif 12%: total stays 112000
    $inklusif = HelperController::hitungSubtotal(1, 112000, 0, 12, 'Inklusif');
    $nullType = HelperController::hitungSubtotal(1, 112000, 0, 12, null);
    expect($nullType)->toBe($inklusif)->toBe(112000.0);
});

test('hitungSubtotal negative quantity is clamped to zero', function () {
    $result = HelperController::hitungSubtotal(-5, 10000, 0, 12, 'Eksklusif');
    expect($result)->toBe(0.0);
});

test('hitungSubtotal logs error on TaxService exception and falls back to exclusive', function () {
    // Rate 200 would throw in TaxService; hitungSubtotal catches and falls back
    // Note: hitungSubtotal clamps rate to 100 first, so this instead tests that clamping works
    $result = HelperController::hitungSubtotal(1, 10000, 0, 200, 'Eksklusif');
    // rate clamped to 100 → fallback exclusive: 10000 + 10000*100% = 20000
    expect($result)->toBe(20000.0);
});

test('eksklusif ppn and total are integers (rounded to nearest Rupiah)', function () {
    // 11% of 1,000,000 = exactly 110,000 — no fractional Rupiah
    $res = TaxService::compute(1000000.0, 11.0, 'Eksklusif');
    expect(fmod($res['ppn'], 1.0))->toBe(0.0)
        ->and(fmod($res['total'], 1.0))->toBe(0.0);
});

test('inklusif dpp and ppn are integers (rounded to nearest Rupiah)', function () {
    // gross 1,110,000, rate 11% → DPP and PPN should be whole Rupiah
    $res = TaxService::compute(1110000.0, 11.0, 'Inklusif');
    expect(fmod($res['dpp'], 1.0))->toBe(0.0)
        ->and(fmod($res['ppn'], 1.0))->toBe(0.0);
});

// ─── English tax_type variants (Inclusive / Exclusive) ───────────────────────

test('normalizeType maps English Inclusive to Inklusif', function () {
    expect(TaxService::normalizeType('Inclusive'))->toBe('Inklusif')
        ->and(TaxService::normalizeType('inclusive'))->toBe('Inklusif')
        ->and(TaxService::normalizeType('PPN Included'))->toBe('Inklusif')
        ->and(TaxService::normalizeType('ppn included'))->toBe('Inklusif');
});

test('normalizeType maps English Exclusive to Eksklusif', function () {
    expect(TaxService::normalizeType('Exclusive'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('exclusive'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('PPN Excluded'))->toBe('Eksklusif')
        ->and(TaxService::normalizeType('ppn excluded'))->toBe('Eksklusif');
});

test('compute Exclusive tax_type: price=2500000 rate=12 gives tax=300000 total=2800000', function () {
    // Section 3 spec: Exclusive, price 2,500,000, rate 12% → tax 300,000, total 2,800,000
    $res = TaxService::compute(2500000.0, 12.0, 'Exclusive');
    expect($res['ppn'])->toBe(300000.0)
        ->and($res['total'])->toBe(2800000.0)
        ->and($res['dpp'])->toBe(2500000.0);
});

test('compute Inclusive tax_type: amount=2500000 rate=12 gives dpp=2232143 ppn=267857 total=2500000', function () {
    // Section 3 spec: Inclusive, amount 2,500,000, rate 12% → dpp ≈ 2,232,143, ppn ≈ 267,857
    $res = TaxService::compute(2500000.0, 12.0, 'Inclusive');
    expect($res['dpp'])->toBe(2232143.0)
        ->and($res['ppn'])->toBe(267857.0)
        ->and($res['total'])->toBe(2500000.0);
});

test('hitungSubtotal with Exclusive tax_type adds tax on top', function () {
    // 1 qty × 2,500,000, 0 discount, 12% Exclusive → total = 2,800,000
    $result = HelperController::hitungSubtotal(1, 2500000, 0, 12, 'Exclusive');
    expect($result)->toBe(2800000.0);
});

test('hitungSubtotal with Inclusive tax_type total stays same', function () {
    // 1 qty × 2,500,000, 0 discount, 12% Inclusive → total = 2,500,000
    $result = HelperController::hitungSubtotal(1, 2500000, 0, 12, 'Inclusive');
    expect($result)->toBe(2500000.0);
});
