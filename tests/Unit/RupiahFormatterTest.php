<?php

use App\Helpers\MoneyHelper;

/**
 * RupiahFormatterTest
 *
 * Unit tests for the MoneyHelper Rupiah formatter.
 * Ensures consistent Indonesian Rupiah format across the entire ERP.
 *
 * Format rules:
 *  - Prefix:    "Rp " (space after Rp)
 *  - Thousands: "." (dot)
 *  - Decimal:   "," (comma) — omitted by default
 *  - Negative:  "-Rp 1.000"
 *  - Null/empty: "Rp 0"
 */

describe('MoneyHelper::rupiah()', function () {

    // ---------------------------------------------------------------
    // Basic formatting
    // ---------------------------------------------------------------

    it('formats zero as Rp 0', function () {
        expect(MoneyHelper::rupiah(0))->toBe('Rp 0');
    });

    it('formats 1000 as Rp 1.000', function () {
        expect(MoneyHelper::rupiah(1000))->toBe('Rp 1.000');
    });

    it('formats 10000 as Rp 10.000', function () {
        expect(MoneyHelper::rupiah(10000))->toBe('Rp 10.000');
    });

    it('formats 100000 as Rp 100.000', function () {
        expect(MoneyHelper::rupiah(100000))->toBe('Rp 100.000');
    });

    it('formats 1000000 as Rp 1.000.000', function () {
        expect(MoneyHelper::rupiah(1000000))->toBe('Rp 1.000.000');
    });

    it('formats 10500000 as Rp 10.500.000', function () {
        expect(MoneyHelper::rupiah(10500000))->toBe('Rp 10.500.000');
    });

    it('formats 1000000000 as Rp 1.000.000.000', function () {
        expect(MoneyHelper::rupiah(1000000000))->toBe('Rp 1.000.000.000');
    });

    // ---------------------------------------------------------------
    // String input (user-typed values & DB strings)
    // ---------------------------------------------------------------

    it('accepts string "1000000" and formats as Rp 1.000.000', function () {
        expect(MoneyHelper::rupiah('1000000'))->toBe('Rp 1.000.000');
    });

    it('accepts string "0" and formats as Rp 0', function () {
        expect(MoneyHelper::rupiah('0'))->toBe('Rp 0');
    });

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    it('handles null as Rp 0', function () {
        expect(MoneyHelper::rupiah(null))->toBe('Rp 0');
    });

    it('handles empty string as Rp 0', function () {
        expect(MoneyHelper::rupiah(''))->toBe('Rp 0');
    });

    it('rounds float to nearest integer (no decimals)', function () {
        expect(MoneyHelper::rupiah(1500.50))->toBe('Rp 1.501');
        expect(MoneyHelper::rupiah(1500.49))->toBe('Rp 1.500');
    });

    it('formats negative values with minus prefix -Rp', function () {
        expect(MoneyHelper::rupiah(-5000))->toBe('-Rp 5.000');
        expect(MoneyHelper::rupiah(-1000000))->toBe('-Rp 1.000.000');
    });

    // ---------------------------------------------------------------
    // Format correctness: no USD-style (comma thousands, period decimal)
    // ---------------------------------------------------------------

    it('does NOT contain a comma as thousands separator', function () {
        // Ensure IDR format (dot thousands) not USD format (comma thousands)
        $result = MoneyHelper::rupiah(1000000);
        expect($result)->not->toContain('1,000,000');
        expect($result)->toContain('1.000.000');
    });

    it('prefix is always "Rp " with trailing space, no dot, no uppercase RP', function () {
        $result = MoneyHelper::rupiah(500);
        expect($result)->toStartWith('Rp ');
        expect($result)->not->toStartWith('RP ');
        expect($result)->not->toStartWith('Rp.');
    });

    it('has no decimal separator for whole numbers', function () {
        $result = MoneyHelper::rupiah(1500);
        // Should not end with ,00 or .00
        expect($result)->not->toContain(',00');
        expect($result)->not->toContain('.00');
        expect($result)->toBe('Rp 1.500');
    });
});

describe('MoneyHelper::rupiahDecimal()', function () {

    it('formats 1500.75 with 2 decimals as Rp 1.500,75', function () {
        expect(MoneyHelper::rupiahDecimal(1500.75, 2))->toBe('Rp 1.500,75');
    });

    it('handles null as Rp 0', function () {
        expect(MoneyHelper::rupiahDecimal(null))->toBe('Rp 0');
    });

    it('uses comma as decimal separator (Indonesian format)', function () {
        $result = MoneyHelper::rupiahDecimal(1234567.89);
        expect($result)->toContain(',89');
        expect($result)->not->toContain('.89');
    });
});

describe('MoneyHelper::parse()', function () {

    it('parses "1.000.000" to float 1000000.0', function () {
        expect(MoneyHelper::parse('1.000.000'))->toBe(1000000.0);
    });

    it('parses "1.500,75" to float 1500.75', function () {
        expect(MoneyHelper::parse('1.500,75'))->toBe(1500.75);
    });

    it('parses "Rp 1.000.000" to float 1000000.0', function () {
        expect(MoneyHelper::parse('Rp 1.000.000'))->toBe(1000000.0);
    });

    it('parses plain "1000000" string to 1000000.0', function () {
        expect(MoneyHelper::parse('1000000'))->toBe(1000000.0);
    });

    it('parses empty string to 0.0', function () {
        expect(MoneyHelper::parse(''))->toBe(0.0);
    });

    it('parses null to 0.0', function () {
        expect(MoneyHelper::parse(null))->toBe(0.0);
    });

    it('round-trips: format → parse → format gives same result', function () {
        $original = 5750000;
        $formatted = MoneyHelper::rupiah($original);           // "Rp 5.750.000"
        $parsed    = MoneyHelper::parse($formatted);           // 5750000.0
        $reformatted = MoneyHelper::rupiah($parsed);           // "Rp 5.750.000"

        expect($reformatted)->toBe($formatted);
        expect($parsed)->toBe((float) $original);
    });
});
