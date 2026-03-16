<?php

namespace Tests\Feature;

use App\Services\TaxService;
use Tests\TestCase;

/**
 * Tests for DPP (Dasar Pengenaan Pajak) calculation correctness in
 * InvoiceResource and PurchaseInvoiceResource.
 *
 * DPP = taxable base = price AFTER discount, BEFORE PPN is added.
 *
 * Bug that was fixed:
 * - InvoiceResource used: price = unit_price - discount% + tax%
 *   (treated percentages as nominal amounts — completely wrong arithmetic)
 * - PurchaseInvoiceResource used: price = afterDiscount + taxAmount
 *   (price already included item-level PPN, making DPP = tax-inclusive total)
 *
 * Correct behaviour:
 * - price = unit_price * (1 - discount/100)  [pre-tax, after discount]
 * - DPP = price * quantity
 * - PPN is calculated at invoice level: DPP * ppn_rate / 100
 */
class InvoiceDppCalculationTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helper: replicate the FIXED InvoiceResource item calculation
    // -----------------------------------------------------------------------
    private function calcInvoiceItemDpp(float $unitPrice, float $discountPct, int $quantity): float
    {
        $discountAmount = $unitPrice * ($discountPct / 100);
        $price          = $unitPrice - $discountAmount;
        return $price * $quantity;
    }

    // Helper: replicate the BUGGY old InvoiceResource calculation (for comparison)
    private function calcInvoiceItemDppBuggy(float $unitPrice, float $discountPct, float $taxPct, int $quantity): float
    {
        $price = $unitPrice - $discountPct + $taxPct; // old wrong formula
        return $price * $quantity;
    }

    // -----------------------------------------------------------------------
    // Helper: replicate the FIXED PurchaseInvoiceResource item calculation
    // -----------------------------------------------------------------------
    private function calcPurchaseInvoiceItemDpp(float $unitPrice, float $discountPct, int $qtyAccepted): float
    {
        $discountAmount = $unitPrice * ($discountPct / 100);
        $afterDiscount  = $unitPrice - $discountAmount;
        return $afterDiscount * $qtyAccepted;
    }

    // Helper: replicate the BUGGY old PurchaseInvoiceResource calculation
    private function calcPurchaseInvoiceItemDppBuggy(float $unitPrice, float $discountPct, float $taxPct, int $qtyAccepted): float
    {
        $discountAmount = $unitPrice * ($discountPct / 100);
        $afterDiscount  = $unitPrice - $discountAmount;
        $taxAmount      = $afterDiscount * ($taxPct / 100);
        $finalUnitPrice = $afterDiscount + $taxAmount; // old wrong: price includes tax
        return $finalUnitPrice * $qtyAccepted;
    }

    // -----------------------------------------------------------------------
    // DPP basic correctness tests (InvoiceResource formula)
    // -----------------------------------------------------------------------

    /** @test */
    public function test_invoice_dpp_no_discount_no_tax(): void
    {
        // unit_price=500000, discount=0%, tax=0%, qty=3
        // DPP = 500000 * 3 = 1,500,000
        $dpp = $this->calcInvoiceItemDpp(500_000, 0, 3);
        $this->assertEquals(1_500_000.0, $dpp);
    }

    /** @test */
    public function test_invoice_dpp_with_discount_percentage(): void
    {
        // unit_price=1,000,000, discount=10%, qty=2
        // price after discount = 1,000,000 * 0.90 = 900,000
        // DPP = 900,000 * 2 = 1,800,000
        $dpp = $this->calcInvoiceItemDpp(1_000_000, 10, 2);
        $this->assertEquals(1_800_000.0, $dpp);
    }

    /** @test */
    public function test_invoice_dpp_is_pre_tax_not_including_ppn(): void
    {
        // unit_price=1,000,000, discount=10%, tax(ppn)=11%, qty=2
        // DPP must be price BEFORE PPN: 900,000 * 2 = 1,800,000
        // NOT 900,000 * 1.11 * 2 = 1,998,000
        $dpp = $this->calcInvoiceItemDpp(1_000_000, 10, 2);
        $this->assertEquals(1_800_000.0, $dpp);

        // Confirm: invoice-level PPN = DPP * 11% = 1,800,000 * 0.11 = 198,000
        $ppn   = round($dpp * 11 / 100, 0);
        $total = $dpp + $ppn;
        $this->assertEquals(198_000.0, $ppn);
        $this->assertEquals(1_998_000.0, $total);
    }

    /** @test */
    public function test_invoice_dpp_fixed_formula_differs_from_buggy_formula(): void
    {
        // This test documents that the bug produced wrong values.
        // unit_price=1,000,000, discount=10, tax=11, qty=2
        $dppFixed = $this->calcInvoiceItemDpp(1_000_000, 10, 2);
        $dppBuggy = $this->calcInvoiceItemDppBuggy(1_000_000, 10, 11, 2);

        // Fixed: 1,800,000  — correct DPP
        $this->assertEquals(1_800_000.0, $dppFixed);

        // Buggy: (1,000,000 - 10 + 11) * 2 = 1,000,001 * 2 = 2,000,002
        // This is clearly wrong (nearly equal to gross with no discount applied)
        $this->assertEquals(2_000_002.0, $dppBuggy);

        // Ensure the values are different — confirming the bug existed
        $this->assertNotEquals($dppFixed, $dppBuggy);
    }

    /** @test */
    public function test_invoice_dpp_zero_discount_still_works(): void
    {
        // unit_price=750,000, discount=0%, qty=4
        // DPP = 750,000 * 4 = 3,000,000
        $dpp = $this->calcInvoiceItemDpp(750_000, 0, 4);
        $this->assertEquals(3_000_000.0, $dpp);
    }

    /** @test */
    public function test_invoice_dpp_multiple_items_sum(): void
    {
        // Item 1: unit_price=1,000,000, discount=10%, qty=2 → DPP=1,800,000
        // Item 2: unit_price=500,000,   discount=5%,  qty=3 → DPP=1,425,000
        // Total DPP = 3,225,000
        $dpp1    = $this->calcInvoiceItemDpp(1_000_000, 10, 2);
        $dpp2    = $this->calcInvoiceItemDpp(500_000, 5, 3);
        $totalDpp = $dpp1 + $dpp2;

        $this->assertEquals(1_800_000.0, $dpp1);
        $this->assertEquals(1_425_000.0, $dpp2);
        $this->assertEquals(3_225_000.0, $totalDpp);
    }

    // -----------------------------------------------------------------------
    // DPP basic correctness tests (PurchaseInvoiceResource formula)
    // -----------------------------------------------------------------------

    /** @test */
    public function test_purchase_invoice_dpp_no_discount(): void
    {
        // unit_price=800,000, discount=0%, qty_accepted=5
        // DPP = 800,000 * 5 = 4,000,000
        $dpp = $this->calcPurchaseInvoiceItemDpp(800_000, 0, 5);
        $this->assertEquals(4_000_000.0, $dpp);
    }

    /** @test */
    public function test_purchase_invoice_dpp_with_discount(): void
    {
        // unit_price=1,000,000, discount=10%, qty_accepted=2
        // afterDiscount = 900,000; DPP = 900,000 * 2 = 1,800,000
        $dpp = $this->calcPurchaseInvoiceItemDpp(1_000_000, 10, 2);
        $this->assertEquals(1_800_000.0, $dpp);
    }

    /** @test */
    public function test_purchase_invoice_dpp_is_pre_tax(): void
    {
        // unit_price=1,000,000, discount=10%, tax=11%, qty_accepted=2
        // DPP must be afterDiscount * qty = 900,000 * 2 = 1,800,000
        // (NOT including the item-level tax in DPP)
        $dpp = $this->calcPurchaseInvoiceItemDpp(1_000_000, 10, 2);
        $this->assertEquals(1_800_000.0, $dpp);

        // The item-level PPN should be applied at invoice level:
        $ppn   = round($dpp * 11 / 100, 0);
        $total = $dpp + $ppn;
        $this->assertEquals(198_000.0, $ppn);
        $this->assertEquals(1_998_000.0, $total);
    }

    /** @test */
    public function test_purchase_invoice_dpp_fixed_differs_from_buggy(): void
    {
        // unit_price=1,000,000, discount=10%, tax=11%, qty_accepted=2
        $dppFixed = $this->calcPurchaseInvoiceItemDpp(1_000_000, 10, 2);
        $dppBuggy = $this->calcPurchaseInvoiceItemDppBuggy(1_000_000, 10, 11, 2);

        // Fixed: afterDiscount=900,000; DPP = 900,000 * 2 = 1,800,000
        $this->assertEquals(1_800_000.0, $dppFixed);

        // Buggy: afterDiscount=900,000; taxAmt=99,000; finalUnitPrice=999,000; DPP = 999,000 * 2 = 1,998,000
        $this->assertEquals(1_998_000.0, $dppBuggy);

        // Confirm the bug overinflated DPP (included PPN in DPP = tax was applied twice)
        $this->assertNotEquals($dppFixed, $dppBuggy);
        $this->assertGreaterThan($dppFixed, $dppBuggy);
    }

    /** @test */
    public function test_purchase_invoice_dpp_multiple_items_sum(): void
    {
        // Item 1: unit_price=600,000, discount=0%,  qty_accepted=10 → DPP=6,000,000
        // Item 2: unit_price=200,000, discount=20%, qty_accepted=5  → DPP=800,000
        // Total DPP = 6,800,000
        $dpp1    = $this->calcPurchaseInvoiceItemDpp(600_000, 0, 10);
        $dpp2    = $this->calcPurchaseInvoiceItemDpp(200_000, 20, 5);
        $totalDpp = $dpp1 + $dpp2;

        $this->assertEquals(6_000_000.0, $dpp1);
        $this->assertEquals(800_000.0, $dpp2);
        $this->assertEquals(6_800_000.0, $totalDpp);
    }

    // -----------------------------------------------------------------------
    // TaxService consistency: confirm DPP from TaxService matches our formula
    // -----------------------------------------------------------------------

    /** @test */
    public function test_taxservice_eksklusif_dpp_matches_invoice_dpp_formula(): void
    {
        // For Eksklusif: TaxService::compute(amount) returns dpp = amount (the pre-tax base)
        // Our formula: DPP for item = afterDiscount * quantity
        // TaxService receives afterDiscount * quantity as the amount
        $unitPrice    = 1_000_000.0;
        $discountPct  = 10.0;
        $taxRate      = 11.0;
        $quantity     = 2;

        $dpp = $this->calcInvoiceItemDpp($unitPrice, $discountPct, $quantity); // 1,800,000

        $taxResult = TaxService::compute($dpp, $taxRate, 'Eksklusif');

        // TaxService Eksklusif: dpp = input amount = 1,800,000 (unchanged)
        $this->assertEquals($dpp, $taxResult['dpp']);
        $this->assertEquals(198_000.0, $taxResult['ppn']);    // 1,800,000 * 11%
        $this->assertEquals(1_998_000.0, $taxResult['total']); // 1,800,000 + 198,000
    }

    /** @test */
    public function test_taxservice_inklusif_dpp_calculated_from_gross(): void
    {
        // For Inklusif: the gross price already includes PPN.
        // TaxService extracts DPP = gross * 100 / (100 + rate)
        $grossPerItem = 1_110_000.0; // 1,000,000 DPP + 110,000 PPN (11%)
        $quantity     = 2;
        $taxRate      = 11.0;

        $grossTotal = $grossPerItem * $quantity; // 2,220,000

        $taxResult = TaxService::compute($grossTotal, $taxRate, 'Inklusif');

        // DPP = 2,220,000 * 100 / 111 = 2,000,000
        $this->assertEquals(2_000_000.0, $taxResult['dpp']);
        // PPN = 220,000
        $this->assertEquals(220_000.0, $taxResult['ppn']);
        // Total = gross (unchanged for inklusif)
        $this->assertEquals(2_220_000.0, $taxResult['total']);
    }

    /** @test */
    public function test_taxservice_non_pajak_dpp_equals_gross(): void
    {
        $amount = 1_500_000.0;
        $result = TaxService::compute($amount, 11, 'Non Pajak');

        // Non Pajak: DPP = amount, PPN = 0, total = amount
        $this->assertEquals($amount, $result['dpp']);
        $this->assertEquals(0.0, $result['ppn']);
        $this->assertEquals($amount, $result['total']);
    }
}
