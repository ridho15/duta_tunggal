<?php

namespace Tests\Unit;

use App\Http\Controllers\HelperController;
use App\Services\TaxService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for tax calculation logic (PurchaseOrderItem tipe_pajak).
 *
 * Tipe Pajak:
 *  - Non Pajak  : no PPN, subtotal = base
 *  - Inklusif   : PPN already included in unit price (subtotal = base, DPP = base/1.rate)
 *  - Eksklusif  : PPN added on top (subtotal = base + PPN)
 */
class TaxCalculationTest extends TestCase
{
    // ─── TaxService::normalizeType ───────────────────────────────────────────

    /** @test */
    public function it_normalizes_eklusif_to_eksklusif(): void
    {
        $this->assertSame('Eksklusif', TaxService::normalizeType('Eklusif'));
        $this->assertSame('Eksklusif', TaxService::normalizeType('eklusif'));
        $this->assertSame('Eksklusif', TaxService::normalizeType('Eksklusif'));
        $this->assertSame('Eksklusif', TaxService::normalizeType('eksklusif'));
    }

    /** @test */
    public function it_normalizes_inklusif(): void
    {
        $this->assertSame('Inklusif', TaxService::normalizeType('Inklusif'));
        $this->assertSame('Inklusif', TaxService::normalizeType('inklusif'));
    }

    /** @test */
    public function it_normalizes_non_pajak(): void
    {
        $this->assertSame('Non Pajak', TaxService::normalizeType('Non Pajak'));
        $this->assertSame('Non Pajak', TaxService::normalizeType('non pajak'));
        $this->assertSame('Non Pajak', TaxService::normalizeType('non-pajak'));
    }

    /** @test */
    public function null_defaults_to_eksklusif(): void
    {
        // When tipe_pajak is null, TaxService defaults to Eksklusif
        $this->assertSame('Eksklusif', TaxService::normalizeType(null));
        $this->assertSame('Eksklusif', TaxService::normalizeType(''));
    }

    // ─── TaxService::compute ─────────────────────────────────────────────────

    /** @test */
    public function non_pajak_returns_same_amount(): void
    {
        $result = TaxService::compute(10000, 12, 'Non Pajak');
        $this->assertSame(10000.0, $result['dpp']);
        $this->assertSame(0.0, $result['ppn']);
        $this->assertSame(10000.0, $result['total']);
    }

    /** @test */
    public function eksklusif_adds_tax_on_top(): void
    {
        // unit price 10,000 + 12% PPN = 11,200
        $result = TaxService::compute(10000, 12, 'Eksklusif');
        $this->assertSame(10000.0, $result['dpp']);
        $this->assertEqualsWithDelta(1200.0, $result['ppn'], 0.01);
        $this->assertEqualsWithDelta(11200.0, $result['total'], 0.01);
    }

    /** @test */
    public function eklusif_old_spelling_adds_tax_on_top(): void
    {
        $result = TaxService::compute(10000, 12, 'Eklusif');
        $this->assertEqualsWithDelta(11200.0, $result['total'], 0.01);
    }

    /** @test */
    public function inklusif_tax_is_extracted_from_price(): void
    {
        // price 11,200 already includes 12% PPN
        // DPP = 11200 * 100/112 = 10,000
        // PPN = 11200 - 10000 = 1,200
        $result = TaxService::compute(11200, 12, 'Inklusif');
        $this->assertEqualsWithDelta(10000.0, $result['dpp'], 0.01);
        $this->assertEqualsWithDelta(1200.0, $result['ppn'], 0.01);
        $this->assertEqualsWithDelta(11200.0, $result['total'], 0.01);
    }

    /** @test */
    public function inklusif_total_does_not_increase(): void
    {
        // For Inklusif, the total should equal the input amount (no extra charge)
        $amount = 10000;
        $result = TaxService::compute($amount, 12, 'Inklusif');
        $this->assertEqualsWithDelta($amount, $result['total'], 0.01);
    }

    /** @test */
    public function zero_rate_always_returns_no_tax(): void
    {
        foreach (['Non Pajak', 'Inklusif', 'Eksklusif'] as $type) {
            $result = TaxService::compute(10000, 0, $type);
            $this->assertSame(0.0, $result['ppn'], "Expected 0 ppn for $type with 0% rate");
        }
    }

    // ─── HelperController::hitungSubtotal ────────────────────────────────────

    /** @test */
    public function hitung_subtotal_non_pajak(): void
    {
        // qty=10, price=1000, discount=0, tax=0, Non Pajak → 10000
        $result = HelperController::hitungSubtotal(10, 1000, 0, 0, 'Non Pajak');
        $this->assertEqualsWithDelta(10000.0, $result, 0.01);
    }

    /** @test */
    public function hitung_subtotal_eksklusif_adds_tax(): void
    {
        // qty=10, price=1000, discount=0, tax=12%, Eksklusif → 10000 + 1200 = 11200
        $result = HelperController::hitungSubtotal(10, 1000, 0, 12, 'Eksklusif');
        $this->assertEqualsWithDelta(11200.0, $result, 0.01);
    }

    /** @test */
    public function hitung_subtotal_inklusif_no_extra_charge(): void
    {
        // qty=10, price=1000, discount=0, tax=12%, Inklusif → total stays 10000 (tax inside)
        $result = HelperController::hitungSubtotal(10, 1000, 0, 12, 'Inklusif');
        $this->assertEqualsWithDelta(10000.0, $result, 0.01);
    }

    /** @test */
    public function hitung_subtotal_with_discount_eksklusif(): void
    {
        // qty=10, price=1000, discount=10%, tax=12%, Eksklusif
        // base = 10000, after discount = 9000, after tax = 9000 * 1.12 = 10080
        $result = HelperController::hitungSubtotal(10, 1000, 10, 12, 'Eksklusif');
        $this->assertEqualsWithDelta(10080.0, $result, 0.01);
    }

    /** @test */
    public function hitung_subtotal_with_discount_inklusif(): void
    {
        // qty=10, price=1000, discount=10%, tax=12%, Inklusif
        // base = 10000, after discount = 9000 (tax included in 9000, total stays 9000)
        $result = HelperController::hitungSubtotal(10, 1000, 10, 12, 'Inklusif');
        $this->assertEqualsWithDelta(9000.0, $result, 0.01);
    }

    /** @test */
    public function hitung_subtotal_eklusif_old_spelling(): void
    {
        // Old 'Eklusif' spelling should behave same as 'Eksklusif'
        $eksklusif = HelperController::hitungSubtotal(1, 10000, 0, 12, 'Eksklusif');
        $eklusif   = HelperController::hitungSubtotal(1, 10000, 0, 12, 'Eklusif');
        $this->assertEqualsWithDelta($eksklusif, $eklusif, 0.01);
    }

    /** @test */
    public function inklusif_ppn_is_less_than_eksklusif_ppn(): void
    {
        // For the same price, Inklusif PPN is smaller than Eksklusif PPN
        // because Inklusif extracts PPN from the price while Eksklusif adds on top
        $inklusif = TaxService::compute(10000, 12, 'Inklusif');
        $eksklusif = TaxService::compute(10000, 12, 'Eksklusif');
        $this->assertLessThan($eksklusif['ppn'], $inklusif['ppn']);
    }

    /** @test */
    public function non_pajak_item_tax_rate_ignored(): void
    {
        // Even if tax rate is 12, Non Pajak means no PPN
        $withTax    = HelperController::hitungSubtotal(1, 10000, 0, 12, 'Non Pajak');
        $withoutTax = HelperController::hitungSubtotal(1, 10000, 0, 0, 'Non Pajak');
        $this->assertEqualsWithDelta($withoutTax, $withTax, 0.01);
        $this->assertEqualsWithDelta(10000.0, $withTax, 0.01);
    }
}
