<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 3: Document Code Generation
 * Tests: SO prefix, RN prefix, format, sequence increment, uniqueness.
 */
class DocumentCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Currency $currency;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang    = Cabang::factory()->create();
        $this->currency  = Currency::factory()->create();
        $this->supplier  = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
    }

    // ─── Sales Order Code (SO-) ───────────────────────────────────────────────

    /** @test */
    public function so_number_generator_produces_so_prefix(): void
    {
        $service = app(SalesOrderService::class);
        $soNumber = $service->generateSoNumber();

        $this->assertStringStartsWith('SO-', $soNumber,
            'Sales Order number must start with SO- prefix');
    }

    /** @test */
    public function so_number_generator_does_not_produce_rn_prefix(): void
    {
        // Regression test for the bug where generateSoNumber() used 'RN-' prefix
        $service = app(SalesOrderService::class);
        $soNumber = $service->generateSoNumber();

        $this->assertStringNotContainsString('RN-', $soNumber,
            'Sales Order number must NOT start with RN- (that prefix belongs to Purchase Receipts)');
    }

    /** @test */
    public function so_number_generator_produces_unique_codes(): void
    {
        $service  = app(SalesOrderService::class);
        $customer = \App\Models\Customer::factory()->create();

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $num = $service->generateSoNumber();
            $this->assertNotContains($num, $numbers, "Duplicate SO number generated: {$num}");
            $numbers[] = $num;

            // Register in DB so next call knows it's taken
            SaleOrder::create([
                'customer_id'     => $customer->id,
                'cabang_id'       => $this->cabang->id,
                'so_number'       => $num,
                'order_date'      => now(),
                'status'          => 'draft',
                'total_amount'    => 0,
                'tipe_pengiriman' => 'Kirim Langsung',
            ]);
        }

        $this->assertCount(5, array_unique($numbers), 'All 5 generated SO numbers must be unique');
    }

    /** @test */
    public function so_number_increments_sequentially(): void
    {
        $service  = app(SalesOrderService::class);
        $customer = \App\Models\Customer::factory()->create();

        $first = $service->generateSoNumber();
        SaleOrder::create([
            'customer_id'     => $customer->id,
            'cabang_id'       => $this->cabang->id,
            'so_number'       => $first,
            'order_date'      => now(),
            'status'          => 'draft',
            'total_amount'    => 0,
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $second = $service->generateSoNumber();

        $this->assertNotEquals($first, $second, 'Second SO number must differ from first');

        // Both start with SO-
        $this->assertStringStartsWith('SO-', $first);
        $this->assertStringStartsWith('SO-', $second);

        // Extract numeric suffixes to verify increment
        $firstNum  = (int) ltrim(substr($first,  strlen('SO-')), '0');
        $secondNum = (int) ltrim(substr($second, strlen('SO-')), '0');
        $this->assertGreaterThan($firstNum, $secondNum, 'Second number must be higher than first');
    }

    /** @test */
    public function so_number_ignores_cabang_scope_for_uniqueness(): void
    {
        // Numbers must be globally unique, not just per branch
        $service  = app(SalesOrderService::class);
        $customer = \App\Models\Customer::factory()->create();
        $other    = Cabang::factory()->create();

        $numA = $service->generateSoNumber();
        SaleOrder::withoutGlobalScopes()->create([
            'customer_id'     => $customer->id,
            'cabang_id'       => $other->id,   // different branch
            'so_number'       => $numA,
            'order_date'      => now(),
            'status'          => 'draft',
            'total_amount'    => 0,
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $numB = $service->generateSoNumber();

        $this->assertNotEquals($numA, $numB,
            'SO number generator must avoid collision across branches');
    }

    // ─── Purchase Receipt Code (RN-) ─────────────────────────────────────────

    /** @test */
    public function rn_number_generator_produces_rn_prefix(): void
    {
        $service  = app(PurchaseReceiptService::class);
        $rnNumber = $service->generateReceiptNumber();

        $this->assertStringStartsWith('RN-', $rnNumber,
            'Purchase Receipt number must start with RN- prefix');
    }

    /** @test */
    public function rn_number_contains_date_in_format_yyyymmdd(): void
    {
        $service  = app(PurchaseReceiptService::class);
        $rnNumber = $service->generateReceiptNumber();
        $today    = now()->format('Ymd');

        // Format: RN-YYYYMMDD-XXXX
        $this->assertStringContainsString($today, $rnNumber,
            "Receipt number must contain today's date ({$today})");
    }

    /** @test */
    public function rn_number_generator_produces_unique_codes(): void
    {
        $service = app(PurchaseReceiptService::class);
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cabang_id'   => $this->cabang->id,
            'status'      => 'approved',
        ]);

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $num = $service->generateReceiptNumber();
            $this->assertNotContains($num, $numbers, "Duplicate RN number generated: {$num}");
            $numbers[] = $num;

            // Register so next call sees it as taken
            PurchaseReceipt::create([
                'receipt_number'    => $num,
                'purchase_order_id' => $po->id,
                'receipt_date'      => now()->toDateString(),
                'received_by'       => $this->user->id,
                'status'            => 'draft',
                'cabang_id'         => $this->cabang->id,
                'currency_id'       => $this->currency->id,
            ]);
        }

        $this->assertCount(5, array_unique($numbers), 'All 5 RN numbers must be unique');
    }

    /** @test */
    public function rn_number_format_is_rn_date_fourdigit(): void
    {
        $service  = app(PurchaseReceiptService::class);
        $rnNumber = $service->generateReceiptNumber();
        $today    = now()->format('Ymd');

        // Assert format: RN-YYYYMMDD-XXXX (where XXXX is 4-digit zero-padded)
        $this->assertMatchesRegularExpression(
            '/^RN-\d{8}-\d{4}$/',
            $rnNumber,
            "RN number must match format RN-YYYYMMDD-XXXX, got: {$rnNumber}"
        );
    }

    // ─── Invoice Code (INV-) ─────────────────────────────────────────────────

    /** @test */
    public function invoice_number_has_inv_prefix(): void
    {
        $service = app(\App\Services\InvoiceService::class);
        $num     = $service->generateInvoiceNumber();
        $today   = now()->format('Ymd');

        $this->assertStringStartsWith('INV-' . $today . '-', $num);
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{4}$/', $num);
    }

    /** @test */
    public function invoice_number_increments_across_calls(): void
    {
        $service = app(\App\Services\InvoiceService::class);
        $today   = now()->format('Ymd');

        $first = $service->generateInvoiceNumber();
        // Register so next call sees it
        \App\Models\Invoice::withoutEvents(function () use ($first) {
            \App\Models\Invoice::withoutGlobalScopes()->create([
                'invoice_number'  => $first,
                'from_model_type' => 'App\\Models\\SaleOrder',
                'from_model_id'   => 1,
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'subtotal'        => 100_000,
                'tax'             => 0,
                'total'           => 100_000,
                'status'          => 'draft',
                'cabang_id'       => $this->cabang->id,
            ]);
        });
        $second = $service->generateInvoiceNumber();

        $this->assertNotEquals($first, $second);
        [$prefixA, $seqA] = [substr($first, 0, -4), (int) substr($first, -4)];
        [$prefixB, $seqB] = [substr($second, 0, -4), (int) substr($second, -4)];
        $this->assertEquals($prefixA, $prefixB, 'Prefix part must match');
        $this->assertEquals($seqA + 1, $seqB, 'Sequence must increment by 1');
    }
}
