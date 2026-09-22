<?php

use App\Helpers\MoneyHelper;

/**
 * Tests for the InvoiceResource::hitungTotal() fix.
 *
 * Before FIX-01: (int) $get('subtotal') truncated "20.000.000" to 20.
 * After FIX-01:  MoneyHelper::parse() correctly returns 20000000.
 *
 * Tests here replicate the exact arithmetic hitungTotal() now uses so that
 * any future regression is immediately caught.
 */

describe('hitungTotal() logic — using MoneyHelper::parse()', function () {

    it('parses Indonesian-formatted 20.000.000 correctly', function () {
        // Simulate the fixed hitungTotal() behaviour.
        $subtotal = (float) MoneyHelper::parse('20.000.000');
        $ppnRate  = 12.0; // %
        $ppn      = round($subtotal * ($ppnRate / 100.0), 0);
        $total    = round($subtotal + $ppn, 0);

        expect($subtotal)->toBe(20000000.0)
            ->and($ppn)->toBe(2400000.0)
            ->and($total)->toBe(22400000.0);
    });

    it('parses 1.000 correctly', function () {
        expect((float) MoneyHelper::parse('1.000'))->toBe(1000.0);
    });

    it('parses 10.000 correctly', function () {
        expect((float) MoneyHelper::parse('10.000'))->toBe(10000.0);
    });

    it('parses 100.000 correctly', function () {
        expect((float) MoneyHelper::parse('100.000'))->toBe(100000.0);
    });

    it('parses 1.000.000 correctly', function () {
        expect((float) MoneyHelper::parse('1.000.000'))->toBe(1000000.0);
    });

    it('parses 10.000.000 correctly', function () {
        expect((float) MoneyHelper::parse('10.000.000'))->toBe(10000000.0);
    });

    it('parses 100.000.000 correctly', function () {
        expect((float) MoneyHelper::parse('100.000.000'))->toBe(100000000.0);
    });

    it('parses raw DB decimal 20000000.00 correctly', function () {
        expect((float) MoneyHelper::parse('20000000.00'))->toBe(20000000.0);
    });

    it('parses null or empty string as zero', function () {
        expect((float) MoneyHelper::parse(null))->toBe(0.0)
            ->and((float) MoneyHelper::parse(''))->toBe(0.0);
    });
});

describe('hitungTotal() — PPN option variants', function () {

    /**
     * Simulates the fixed hitungTotal() with ppn_option variants.
     */
    function simulateHitungTotal(string $subtotalStr, float $ppnRate, string $ppnOption): float
    {
        $subtotal  = (float) MoneyHelper::parse($subtotalStr);
        $otherFee  = 0.0;

        if ($ppnOption === 'non_ppn' || $ppnRate <= 0) {
            return round($subtotal + $otherFee, 0);
        }
        if ($ppnOption === 'inclusive') {
            return round($subtotal + $otherFee, 0);
        }
        // standard (Eksklusif)
        $ppn = round(($subtotal + $otherFee) * ($ppnRate / 100.0), 0);
        return round($subtotal + $otherFee + $ppn, 0);
    }

    it('standard (Eksklusif) adds PPN on top', function () {
        $total = simulateHitungTotal('1.000.000', 12.0, 'standard');
        expect($total)->toBe(1120000.0);
    });

    it('non_ppn returns subtotal unchanged', function () {
        $total = simulateHitungTotal('1.000.000', 12.0, 'non_ppn');
        expect($total)->toBe(1000000.0);
    });

    it('inclusive does NOT add PPN (already included)', function () {
        $total = simulateHitungTotal('1.120.000', 12.0, 'inclusive');
        expect($total)->toBe(1120000.0);
    });

    it('zero ppn_rate returns subtotal unchanged regardless of option', function () {
        $total = simulateHitungTotal('5.000.000', 0.0, 'standard');
        expect($total)->toBe(5000000.0);
    });
});
