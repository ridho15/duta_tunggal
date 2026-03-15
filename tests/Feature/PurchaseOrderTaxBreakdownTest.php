<?php

namespace Tests\Feature;

use App\Http\Controllers\HelperController;
use App\Services\TaxService;
use App\Helpers\MoneyHelper;
use Tests\TestCase;

/**
 * Tests for PurchaseOrderResource tax_breakdown Placeholder calculation consistency.
 * The Placeholder now delegates to TaxService::compute() — same path as hitungSubtotal().
 *
 * Verifies that displayed DPP / PPN / Total in the tax_breakdown Placeholder
 * always matches what hitungSubtotal() / TaxService::compute() returns.
 */
class PurchaseOrderTaxBreakdownTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helper: replicate the Placeholder content-function calculation logic
    // -----------------------------------------------------------------------
    private function computeBreakdown(float $qty, float $unitPrice, float $discount, float $taxRate, string $tipePajak): array
    {
        $gross     = $qty * $unitPrice;
        $discAmt   = $gross * $discount / 100;
        $afterDisc = $gross - $discAmt;

        if ($qty <= 0 || $unitPrice <= 0) {
            return ['dpp' => 0, 'ppn' => 0, 'total' => 0, 'empty' => true];
        }

        $taxResult      = TaxService::compute($afterDisc, $taxRate, $tipePajak);
        $normalizedType = TaxService::normalizeType($tipePajak);

        return array_merge($taxResult, ['normalized_type' => $normalizedType, 'after_disc' => $afterDisc]);
    }

    /** Placeholder result must equal hitungSubtotal result for the subtotal/total field. */
    private function assertBreakdownConsistentWithSubtotal(
        float $qty, float $unitPrice, float $discount, float $taxRate, string $tipePajak
    ): void {
        $breakdown = $this->computeBreakdown($qty, $unitPrice, $discount, $taxRate, $tipePajak);
        $subtotal  = HelperController::hitungSubtotal($qty, $unitPrice, $discount, $taxRate, $tipePajak);

        $this->assertEquals(
            $subtotal,
            $breakdown['total'],
            "Breakdown total ({$breakdown['total']}) must match hitungSubtotal ({$subtotal}) "
            . "for qty={$qty} price={$unitPrice} disc={$discount}% tax={$taxRate}% type={$tipePajak}"
        );
    }

    // -----------------------------------------------------------------------
    // Non Pajak
    // -----------------------------------------------------------------------

    public function test_non_pajak_no_tax_no_rounding_issue(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(10, 100_000, 0, 0, 'Non Pajak');
    }

    public function test_non_pajak_with_discount(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(5, 200_000, 10, 0, 'Non Pajak');
        $breakdown = $this->computeBreakdown(5, 200_000, 10, 0, 'Non Pajak');
        $this->assertEquals(0, $breakdown['ppn']);
        $this->assertEquals($breakdown['dpp'], $breakdown['total']);
    }

    public function test_non_pajak_tax_rate_zero(): void
    {
        // Even if taxRate > 0, 'Non Pajak' type overrides it
        $breakdownNonPajak  = $this->computeBreakdown(10, 50_000, 0, 11, 'Non Pajak');
        $breakdownZeroRate  = $this->computeBreakdown(10, 50_000, 0, 0, 'Inklusif');

        // Non Pajak: ppn always 0
        $this->assertEquals(0.0, $breakdownNonPajak['ppn']);
        // Zero rate: ppn also 0
        $this->assertEquals(0.0, $breakdownZeroRate['ppn']);
    }

    // -----------------------------------------------------------------------
    // Eklusif (stored DB value) — normalized to Eksklusif by TaxService
    // -----------------------------------------------------------------------

    public function test_eklusif_basic_calculation(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(10, 100_000, 0, 11, 'Eklusif');

        $breakdown = $this->computeBreakdown(10, 100_000, 0, 11, 'Eklusif');
        $this->assertEquals('Eksklusif', $breakdown['normalized_type']);
        // afterDisc = 1_000_000, ppn = round(1_000_000 * 11/100, 0) = 110_000
        $this->assertEquals(110_000.0, $breakdown['ppn']);
        $this->assertEquals(1_110_000.0, $breakdown['total']);
        $this->assertEquals(1_000_000.0, $breakdown['dpp']);
    }

    public function test_eksklusif_spelling_normalized(): void
    {
        // Both spellings must produce identical results
        $a = $this->computeBreakdown(5, 200_000, 5, 11, 'Eklusif');
        $b = $this->computeBreakdown(5, 200_000, 5, 11, 'Eksklusif');
        $this->assertEquals($a['dpp'],   $b['dpp']);
        $this->assertEquals($a['ppn'],   $b['ppn']);
        $this->assertEquals($a['total'], $b['total']);
    }

    public function test_eksklusif_with_discount(): void
    {
        // qty=10, price=100_000, disc=10%, tax=11%
        // gross=1_000_000, discAmt=100_000, afterDisc=900_000
        // ppn=round(900_000*0.11)=99_000, total=999_000
        $this->assertBreakdownConsistentWithSubtotal(10, 100_000, 10, 11, 'Eklusif');

        $breakdown = $this->computeBreakdown(10, 100_000, 10, 11, 'Eklusif');
        $this->assertEquals(900_000.0, $breakdown['dpp']);
        $this->assertEquals(99_000.0,  $breakdown['ppn']);
        $this->assertEquals(999_000.0, $breakdown['total']);
    }

    public function test_eksklusif_high_tax_rate(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(2, 500_000, 0, 12, 'Eklusif');
    }

    // -----------------------------------------------------------------------
    // Inklusif
    // -----------------------------------------------------------------------

    public function test_inklusif_basic_calculation(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(10, 100_000, 0, 11, 'Inklusif');

        $breakdown = $this->computeBreakdown(10, 100_000, 0, 11, 'Inklusif');
        // afterDisc=1_000_000 (inclusive: total stays 1_000_000)
        // DPP = round(1_000_000 * 100/111) = round(900_900.9...) = 900_901
        $this->assertEquals(1_000_000.0, $breakdown['total']);
        $this->assertEqualsWithDelta(900_901.0, $breakdown['dpp'], 1.0);  // rounding tolerance
        $this->assertEqualsWithDelta(99_099.0,  $breakdown['ppn'], 1.0);
    }

    public function test_inklusif_with_discount(): void
    {
        $this->assertBreakdownConsistentWithSubtotal(5, 200_000, 10, 11, 'Inklusif');
    }

    public function test_inklusif_ppn_included_synonym(): void
    {
        // 'PPN Included' is a synonym for Inklusif
        $a = $this->computeBreakdown(3, 300_000, 0, 11, 'Inklusif');
        $b = $this->computeBreakdown(3, 300_000, 0, 11, 'PPN Included');
        $this->assertEquals($a['total'], $b['total']);
        $this->assertEquals($a['ppn'],   $b['ppn']);
    }

    // -----------------------------------------------------------------------
    // Consistency: breakdown total == hitungSubtotal for all three types
    // -----------------------------------------------------------------------

    public function test_all_three_types_consistent_with_hitung_subtotal(): void
    {
        $cases = [
            [10, 100_000, 0,  11, 'Non Pajak'],
            [10, 100_000, 10, 11, 'Eklusif'],
            [10, 100_000, 10, 11, 'Inklusif'],
            [1,  550_000, 5,  12, 'Eksklusif'],
            [3,  750_000, 0,  11, 'Non Pajak'],
        ];

        foreach ($cases as [$qty, $price, $disc, $tax, $type]) {
            $this->assertBreakdownConsistentWithSubtotal($qty, $price, $disc, $tax, $type);
        }
    }

    // -----------------------------------------------------------------------
    // ppn_option 'non_ppn' forces tipe_pajak = 'Non Pajak' on all items
    // -----------------------------------------------------------------------

    public function test_non_ppn_option_sets_zero_ppn(): void
    {
        // Simulate the Radio ppn_option 'non_ppn' afterStateUpdated:
        // it changes all items to 'Non Pajak' + tax=0 and recalculates
        $item = ['quantity' => 5, 'unit_price' => 200_000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'Non Pajak'];
        $subtotal = HelperController::hitungSubtotal(
            $item['quantity'],
            HelperController::parseIndonesianMoney($item['unit_price']),
            $item['discount'],
            $item['tax'],
            $item['tipe_pajak']
        );
        // Non Pajak: subtotal = qty * price = 1_000_000
        $this->assertEquals(1_000_000.0, $subtotal);
    }

    // -----------------------------------------------------------------------
    // PPN from OrderRequest mapping consistency
    // -----------------------------------------------------------------------

    public function test_order_request_ppn_included_maps_to_inklusif(): void
    {
        // This is the mapping logic from PurchaseOrderResource afterStateUpdated
        $orTaxType = 'PPN Included';
        $tipePajak = match ($orTaxType) {
            'PPN Included' => 'Inklusif',
            'None'         => 'Non Pajak',
            default        => 'Eklusif',
        };
        $this->assertEquals('Inklusif', $tipePajak);

        // TaxService must normalize 'Inklusif' back to 'Inklusif'
        $this->assertEquals('Inklusif', TaxService::normalizeType($tipePajak));
    }

    public function test_order_request_ppn_excluded_maps_to_eklusif(): void
    {
        $orTaxType = 'PPN Excluded';
        $tipePajak = match ($orTaxType) {
            'PPN Included' => 'Inklusif',
            'None'         => 'Non Pajak',
            default        => 'Eklusif',
        };
        $this->assertEquals('Eklusif', $tipePajak);
        // TaxService normalizes 'Eklusif' → 'Eksklusif'
        $this->assertEquals('Eksklusif', TaxService::normalizeType($tipePajak));
    }

    public function test_order_request_none_maps_to_non_pajak(): void
    {
        $orTaxType = 'None';
        $tipePajak = match ($orTaxType) {
            'PPN Included' => 'Inklusif',
            'None'         => 'Non Pajak',
            default        => 'Eklusif',
        };
        $this->assertEquals('Non Pajak', $tipePajak);
        $this->assertEquals('Non Pajak', TaxService::normalizeType($tipePajak));
    }

    public function test_subtotal_consistent_after_mapping_from_order_request(): void
    {
        // Simulate full chain: OR tax_type → tipe_pajak → hitungSubtotal
        $cases = [
            ['PPN Included', 10, 100_000, 0,  11],
            ['PPN Excluded', 10, 100_000, 10, 11],
            ['None',          5, 200_000, 0,   0],
        ];

        foreach ($cases as [$orTaxType, $qty, $price, $disc, $taxPct]) {
            $tipePajak = match ($orTaxType) {
                'PPN Included' => 'Inklusif',
                'None'         => 'Non Pajak',
                default        => 'Eklusif',
            };
            $subtotal  = HelperController::hitungSubtotal($qty, $price, $disc, $taxPct, $tipePajak);
            $breakdown = $this->computeBreakdown($qty, $price, $disc, $taxPct, $tipePajak);

            $this->assertEquals(
                $subtotal,
                $breakdown['total'],
                "OR type={$orTaxType} → tipe_pajak={$tipePajak}: subtotal mismatch"
            );
        }
    }

    // -----------------------------------------------------------------------
    // MoneyHelper::rupiah formatting used in breakdown display
    // -----------------------------------------------------------------------

    public function test_rupiah_format_non_negative(): void
    {
        $this->assertStringContainsString('Rp', MoneyHelper::rupiah(1_000_000));
        $this->assertStringContainsString('1.000.000', MoneyHelper::rupiah(1_000_000));
    }

    public function test_rupiah_formats_zero(): void
    {
        $this->assertEquals('Rp 0', MoneyHelper::rupiah(0));
    }
}
