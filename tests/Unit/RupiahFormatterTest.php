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
 *  - Decimal:   "," (comma)
 *  - Negative:  "-Rp 1.000,00"
 *  - Null/empty: "Rp 0,00"
 */

describe('MoneyHelper::rupiah()', function () {

    // ---------------------------------------------------------------
    // Basic formatting
    // ---------------------------------------------------------------

    it('formats zero as Rp 0,00', function () {
        expect(MoneyHelper::rupiah(0))->toBe('Rp 0,00');
    });

    it('formats 1000 as Rp 1.000,00', function () {
        expect(MoneyHelper::rupiah(1000))->toBe('Rp 1.000,00');
    });

    it('formats 10000 as Rp 10.000,00', function () {
        expect(MoneyHelper::rupiah(10000))->toBe('Rp 10.000,00');
    });

    it('formats 100000 as Rp 100.000,00', function () {
        expect(MoneyHelper::rupiah(100000))->toBe('Rp 100.000,00');
    });

    it('formats 1000000 as Rp 1.000.000,00', function () {
        expect(MoneyHelper::rupiah(1000000))->toBe('Rp 1.000.000,00');
    });

    it('formats 10500000 as Rp 10.500.000,00', function () {
        expect(MoneyHelper::rupiah(10500000))->toBe('Rp 10.500.000,00');
    });

    it('formats 1000000000 as Rp 1.000.000.000,00', function () {
        expect(MoneyHelper::rupiah(1000000000))->toBe('Rp 1.000.000.000,00');
    });

    // ---------------------------------------------------------------
    // String input (user-typed values & DB strings)
    // ---------------------------------------------------------------

    it('accepts string "1000000" and formats as Rp 1.000.000,00', function () {
        expect(MoneyHelper::rupiah('1000000'))->toBe('Rp 1.000.000,00');
    });

    it('accepts string "0" and formats as Rp 0,00', function () {
        expect(MoneyHelper::rupiah('0'))->toBe('Rp 0,00');
    });

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    it('handles null as Rp 0,00', function () {
        expect(MoneyHelper::rupiah(null))->toBe('Rp 0,00');
    });

    it('handles empty string as Rp 0,00', function () {
        expect(MoneyHelper::rupiah(''))->toBe('Rp 0,00');
    });

    it('keeps two decimal places for floats', function () {
        expect(MoneyHelper::rupiah(1500.50))->toBe('Rp 1.500,50');
        expect(MoneyHelper::rupiah(1500.49))->toBe('Rp 1.500,49');
    });

    it('formats negative values with minus prefix -Rp', function () {
        expect(MoneyHelper::rupiah(-5000))->toBe('-Rp 5.000,00');
        expect(MoneyHelper::rupiah(-1000000))->toBe('-Rp 1.000.000,00');
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

    it('keeps decimal separator for whole numbers', function () {
        $result = MoneyHelper::rupiah(1500);
        expect($result)->toContain(',00');
        expect($result)->not->toContain('.00');
        expect($result)->toBe('Rp 1.500,00');
    });
});

describe('MoneyHelper::rupiahDecimal()', function () {

    it('formats 1500.75 with 2 decimals as Rp 1.500,75', function () {
        expect(MoneyHelper::rupiahDecimal(1500.75, 2))->toBe('Rp 1.500,75');
    });

    it('handles null as Rp 0,00', function () {
        expect(MoneyHelper::rupiahDecimal(null))->toBe('Rp 0,00');
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
        $formatted = MoneyHelper::rupiah($original);           // "Rp 5.750.000,00"
        $parsed    = MoneyHelper::parse($formatted);           // 5750000.0
        $reformatted = MoneyHelper::rupiah($parsed);           // "Rp 5.750.000,00"

        expect($reformatted)->toBe($formatted);
        expect($parsed)->toBe((float) $original);
    });
});

describe('MoneyHelper::safeParse()', function () {

    it('returns numeric integers and floats without changing value', function () {
        expect(MoneyHelper::safeParse(0))->toBe(0.0)
            ->and(MoneyHelper::safeParse(1250000))->toBe(1250000.0)
            ->and(MoneyHelper::safeParse(1250000.75))->toBe(1250000.75);
    });

    it('returns unformatted numeric strings without re-parsing them', function () {
        expect(MoneyHelper::safeParse('1250000'))->toBe(1250000.0)
            ->and(MoneyHelper::safeParse('1250000.75'))->toBe(1250000.75)
            ->and(MoneyHelper::safeParse('1e6'))->toBe(1000000.0);
    });

    it('parses formatted Indonesian values safely', function () {
        expect(MoneyHelper::safeParse('1.000.000'))->toBe(1000000.0)
            ->and(MoneyHelper::safeParse('1.000.000,75'))->toBe(1000000.75)
            ->and(MoneyHelper::safeParse('92.551'))->toBe(92551.0)
            ->and(MoneyHelper::safeParse('92.551,00'))->toBe(92551.0)
            ->and(MoneyHelper::safeParse('92550.52'))->toBe(92550.52)
            ->and(MoneyHelper::safeParse('Rp 27.500'))->toBe(27500.0)
            ->and(MoneyHelper::safeParse('Rp 27.500,50'))->toBe(27500.5);
    });

    it('keeps ambiguous numeric-looking states stable for finance workflows', function () {
        expect(MoneyHelper::safeParse('4.2510666666'))->toBe(4.2510666666)
            ->and(MoneyHelper::safeParse('0,5'))->toBe(0.5)
            ->and(MoneyHelper::safeParse('0001250'))->toBe(1250.0);
    });

    it('returns 0.0 for null, empty string, and dash', function () {
        expect(MoneyHelper::safeParse(null))->toBe(0.0)
            ->and(MoneyHelper::safeParse(''))->toBe(0.0)
            ->and(MoneyHelper::safeParse('-'))->toBe(0.0);
    });
});
