<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Support\ProductPriceLimitation;
use Tests\TestCase;

/**
 * Batas harga produk (laporan UAT B4 & B6): harga efektif setelah diskon tidak boleh menembus batas,
 * dan harga_batas > 100 dibaca sebagai harga minimum NOMINAL (data legacy), 0–100 sebagai persen.
 */
class ProductPriceLimitationTest extends TestCase
{
    private function product(float $sell, float $cost, float $limit): Product
    {
        return (new Product)->forceFill(['id' => 1, 'name' => 'Uji', 'sell_price' => $sell, 'cost_price' => $cost, 'harga_batas' => $limit]);
    }

    public function test_percent_limit_floor_is_sell_price_minus_percent(): void
    {
        $p = $this->product(100000, 50000, 10);   // batas 10% → 90.000

        $this->assertEquals(90000.0, ProductPriceLimitation::getMinimumAllowedPrice($p));
        $this->assertNull(ProductPriceLimitation::validatePrice($p, 90000));
        $this->assertNotNull(ProductPriceLimitation::validatePrice($p, 89999));
    }

    public function test_nominal_limit_above_100_is_treated_as_minimum_price(): void
    {
        $p = $this->product(100000, 50000, 85000);   // legacy: batas nominal Rp85.000

        $this->assertEquals(85000.0, ProductPriceLimitation::getMinimumAllowedPrice($p));
        $this->assertNull(ProductPriceLimitation::validatePrice($p, 85000));
        $this->assertNotNull(ProductPriceLimitation::validatePrice($p, 84000));
    }

    public function test_nominal_limit_never_goes_below_cost_price(): void
    {
        $p = $this->product(100000, 90000, 500);   // nominal 500 < modal 90.000 → modal menjadi batas mutlak

        $this->assertEquals(90000.0, ProductPriceLimitation::getMinimumAllowedPrice($p));
    }

    public function test_discount_cannot_push_effective_price_below_the_limit(): void
    {
        $p = $this->product(100000, 50000, 10);   // batas 90.000

        // Harga satuan 100.000 lolos sendiri, tetapi diskon 15% membuat harga efektif 85.000 < 90.000
        $this->assertNull(ProductPriceLimitation::validatePrice($p, 100000));
        $message = ProductPriceLimitation::validatePrice($p, 100000, 15);
        $this->assertNotNull($message);
        $this->assertStringContainsString('setelah diskon 15%', $message);

        // Diskon 10% tepat di batas (90.000) → lolos
        $this->assertNull(ProductPriceLimitation::validatePrice($p, 100000, 10));
    }

    public function test_product_without_limit_falls_back_to_sell_price_floor(): void
    {
        $p = $this->product(100000, 50000, 0);

        $this->assertEquals(100000.0, ProductPriceLimitation::getMinimumAllowedPrice($p));
        $this->assertNotNull(ProductPriceLimitation::validatePrice($p, 99000));
    }
}
