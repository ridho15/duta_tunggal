<?php

namespace Tests\Feature\ERP;

use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 1 & 2: Sales Module Tax Verification
 * Tests: TaxService logic, hitungSubtotal, Inklusif/Eksklusif formulas,
 *        storage in SO items, Quotation items, and Invoice.
 */
class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang    = Cabang::factory()->create();
        $this->customer  = Customer::factory()->create();
        $this->supplier  = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create();
    }

    // ─── TaxService Unit-level ────────────────────────────────────────────────

    /** @test */
    public function tax_service_eksklusif_adds_tax_on_top_of_price(): void
    {
        // Item price = 1,000,000 | Tax 12% Eksklusif
        // Expected: DPP = 1,000,000 | PPN = 120,000 | Total = 1,120,000
        $result = TaxService::compute(1_000_000, 12, 'Eksklusif');

        $this->assertEquals(1_000_000, $result['dpp'], 'DPP must equal the original amount for Eksklusif');
        $this->assertEquals(120_000, $result['ppn'], 'PPN = amount * rate / 100');
        $this->assertEquals(1_120_000, $result['total'], 'Total = DPP + PPN for Eksklusif');
    }

    /** @test */
    public function tax_service_inklusif_extracts_tax_from_gross_price(): void
    {
        // Gross = 1,120,000 already includes 12% PPN (Inklusif)
        // Expected DPP = 1,120,000 * 100 / 112 = 1,000,000
        // Expected PPN = 1,120,000 - 1,000,000 = 120,000
        $result = TaxService::compute(1_120_000, 12, 'Inklusif');

        $this->assertEquals(1_000_000, $result['dpp'], 'DPP = gross * 100 / (100 + rate)');
        $this->assertEquals(120_000, $result['ppn'], 'PPN = gross - DPP');
        $this->assertEquals(1_120_000, $result['total'], 'Total = gross unchanged for Inklusif');
    }

    /** @test */
    public function tax_service_non_pajak_returns_zero_ppn(): void
    {
        $result = TaxService::compute(1_000_000, 11, 'Non Pajak');

        $this->assertEquals(1_000_000, $result['dpp']);
        $this->assertEquals(0.0, $result['ppn'], 'Non Pajak must have 0 PPN');
        $this->assertEquals(1_000_000, $result['total']);
    }

    /** @test */
    public function tax_service_zero_rate_returns_no_tax(): void
    {
        $result = TaxService::compute(500_000, 0, 'Eksklusif');

        $this->assertEquals(0.0, $result['ppn']);
        $this->assertEquals(500_000, $result['total']);
    }

    /** @test */
    public function tax_service_normalizes_type_variants(): void
    {
        $this->assertEquals('Inklusif', TaxService::normalizeType('inklusif'));
        $this->assertEquals('Eksklusif', TaxService::normalizeType('eklusif'));
        $this->assertEquals('Eksklusif', TaxService::normalizeType('eksklusif'));
        $this->assertEquals('Non Pajak', TaxService::normalizeType('non pajak'));
        $this->assertEquals('Non Pajak', TaxService::normalizeType('non-pajak'));
    }

    /** @test */
    public function tax_service_validate_negative_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaxService::compute(-100, 12, 'Eksklusif');
    }

    /** @test */
    public function tax_service_validate_rate_out_of_range_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaxService::compute(100_000, 150, 'Eksklusif');
    }

    // ─── hitungSubtotal (HelperController) ───────────────────────────────────

    /** @test */
    public function hitung_subtotal_eksklusif_adds_tax_to_line_total(): void
    {
        // qty=2, price=500,000, discount=0%, tax=12%, type=Eksklusif
        // base = 2 * 500,000 = 1,000,000
        // ppn  = 1,000,000 * 12% = 120,000
        // total= 1,120,000
        $result = HelperController::hitungSubtotal(2, 500_000, 0, 12, 'Eksklusif');
        $this->assertEquals(1_120_000, $result);
    }

    /** @test */
    public function hitung_subtotal_inklusif_total_unchanged(): void
    {
        // qty=1, price=1,120,000 (includes PPN), discount=0, tax=12%, type=Inklusif
        // total must stay 1,120,000 (not inflate further)
        $result = HelperController::hitungSubtotal(1, 1_120_000, 0, 12, 'Inklusif');
        $this->assertEquals(1_120_000, $result);
    }

    /** @test */
    public function hitung_subtotal_with_discount_reduces_base_before_tax(): void
    {
        // qty=1, price=1,000,000, discount=10% => after_discount=900,000
        // tax=12% Eksklusif => total = 900,000 + 108,000 = 1,008,000
        $result = HelperController::hitungSubtotal(1, 1_000_000, 10, 12, 'Eksklusif');
        $this->assertEquals(1_008_000, $result);
    }

    /** @test */
    public function hitung_subtotal_with_max_discount_zero_result(): void
    {
        // qty=5, price=100,000, discount=100% => after_discount=0 => total=0
        $result = HelperController::hitungSubtotal(5, 100_000, 100, 12, 'Eksklusif');
        $this->assertEquals(0, $result);
    }

    // ─── SaleOrderItem tax field persistence ─────────────────────────────────

    /** @test */
    public function sale_order_item_tax_field_stores_rate_as_percent(): void
    {
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 2,
            'unit_price'    => 500_000,
            'discount'      => 0,
            'tax'           => 12,   // 12% stored as integer rate
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $this->assertDatabaseHas('sale_order_items', [
            'id'  => $item->id,
            'tax' => 12,
        ]);
        // Verify it is NOT storing absolute amount (120,000)
        $this->assertNotEquals(120_000, $item->fresh()->tax, 'tax column stores RATE not absolute amount');
    }

    /** @test */
    public function sale_order_total_amount_computed_with_inklusif_tax(): void
    {
        // SalesOrderService::updateTotalAmount uses 'Inklusif' mode
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id'    => $this->product->id,
            'quantity'      => 1,
            'unit_price'    => 1_120_000,
            'discount'      => 0,
            'tax'           => 12,
            'warehouse_id'  => $this->warehouse->id,
        ]);

        $service = app(\App\Services\SalesOrderService::class);
        $service->updateTotalAmount($so->load('saleOrderItem'));

        // Inklusif: total for 1,120,000 gross at 12% stays 1,120,000
        $this->assertEquals(1_120_000, $so->fresh()->total_amount);
    }

    // ─── QuotationItem tax ────────────────────────────────────────────────────

    /** @test */
    public function quotation_item_stores_tax_rate(): void
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'draft',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id'   => $this->product->id,
            'quantity'     => 3,
            'unit_price'   => 200_000,
            'total_price'  => 3 * 200_000 * 1.12, // price incl 12% eksklusif pov
            'discount'     => 0,
            'tax'          => 12,
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'id'  => $item->id,
            'tax' => 12,
        ]);
    }

    // ─── Invoice tax rate storage ─────────────────────────────────────────────

    /** @test */
    public function invoice_tax_field_stores_rate_and_total_includes_ppn(): void
    {
        $subtotal = 1_000_000;
        $rate     = 12;
        $ppn      = $subtotal * $rate / 100;  // 120,000
        $total    = $subtotal + $ppn;          // 1,120,000

        $invoice = Invoice::withoutEvents(function () use ($subtotal, $rate, $total) {
            return Invoice::create([
                'invoice_number'   => 'INV-TAXTEST-001',
                'from_model_type'  => 'App\\Models\\SaleOrder',
                'from_model_id'    => 1,
                'invoice_date'     => now()->toDateString(),
                'due_date'         => now()->addDays(30)->toDateString(),
                'subtotal'         => $subtotal,
                'tax'              => $rate,    // stores RATE percent
                'other_fee'        => null,
                'total'            => $total,
                'status'           => 'draft',
                'cabang_id'        => $this->cabang->id,
            ]);
        });

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'tax' => 12]);

        // Verify computed PPN from stored rate matches expected
        $computedPpn = (float) $invoice->fresh()->subtotal * ((float) $invoice->fresh()->tax / 100);
        $this->assertEquals(120_000, $computedPpn, 'PPN computed from rate must equal 120,000');

        // Verify total = subtotal + PPN
        $this->assertEquals(1_120_000, (float) $invoice->fresh()->total);
    }

    /** @test */
    public function invoice_with_eleven_percent_tax_computes_correctly(): void
    {
        $subtotal = 2_000_000;
        $rate     = 11;
        $ppn      = round($subtotal * $rate / 100); // 220,000
        $total    = $subtotal + $ppn;               // 2,220,000

        $invoice = Invoice::withoutEvents(function () use ($subtotal, $rate, $total) {
            return Invoice::create([
                'invoice_number'  => 'INV-TAXTEST-002',
                'from_model_type' => 'App\\Models\\SaleOrder',
                'from_model_id'   => 1,
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'subtotal'        => $subtotal,
                'tax'             => $rate,
                'other_fee'       => null,
                'total'           => $total,
                'status'          => 'draft',
                'cabang_id'       => $this->cabang->id,
            ]);
        });

        $computedPpn = (float) $invoice->fresh()->subtotal * ((float) $invoice->fresh()->tax / 100);
        $this->assertEquals(220_000, $computedPpn);
    }

    /** @test */
    public function inklusif_formula_dpp_is_correct_at_twelve_percent(): void
    {
        // PMK 136/2023: DPP = gross * 100 / (100 + rate)
        $gross  = 1_120_000;
        $result = TaxService::computeFromInclusiveGross($gross, 12);

        $this->assertEquals(1_000_000, $result['dpp']);
        $this->assertEquals(120_000,   $result['ppn']);
        $this->assertEquals(1_120_000, $result['total']);
    }

    /** @test */
    public function eksklusif_formula_at_eleven_percent_matches_spec(): void
    {
        // Scenario B from spec: 11% exclusive
        $result = TaxService::compute(1_000_000, 11, 'Eksklusif');

        $this->assertEquals(1_000_000, $result['dpp']);
        $this->assertEquals(110_000,   $result['ppn']);
        $this->assertEquals(1_110_000, $result['total']);
    }
}
