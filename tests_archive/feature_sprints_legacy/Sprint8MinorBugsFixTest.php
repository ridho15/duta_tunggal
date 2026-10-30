<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\QualityControlItem;
use App\Models\Rak;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use App\Support\StatusLabels;
use App\Support\VendorPaymentAccounts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Sprint8MinorBugsFixTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Rak $rak;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create();

        $this->warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->rak = Rak::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::factory()->forCabang($this->cabang)->create([
            'name' => 'Produk Test Tahap 8',
            'sell_price' => 100000,
            'cost_price' => 75000,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
    }

    /**
     * Bug B: Free stock calculation in SaleOrderApiController
     */
    public function test_sale_order_api_free_stock_aggregates_across_warehouses_and_floors_to_zero(): void
    {
        $this->actingAs($this->user);

        $warehouse2 = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);
        $rak2 = Rak::factory()->create([
            'warehouse_id' => $warehouse2->id,
        ]);

        // Warehouse 1: available = 15, reserved = 5 (net +10)
        InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 15,
            'qty_reserved' => 5,
        ]);

        // Warehouse 2: available = 0, reserved = 2 (net -2, previously excluded by qty_available > 0)
        InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'rak_id' => $rak2->id,
            'qty_available' => 0,
            'qty_reserved' => 2,
        ]);

        $response = $this->getJson('/api/v1/sales-orders/dependencies');

        $response->assertOk();
        $products = collect($response->json('data.products'));
        $found = $products->firstWhere('id', $this->product->id);

        $this->assertNotNull($found);
        // Total should be: 10 + (-2) = 8 pcs
        $this->assertEquals(8.0, (float) $found['free_stock']);
    }

    /**
     * Bug C: VendorPaymentAccounts excludes parent accounts and deposito/investasi
     */
    public function test_vendor_payment_accounts_excludes_parent_accounts_and_deposito_investasi(): void
    {
        // 1. Akun induk bank
        $parentBank = ChartOfAccount::create([
            'code' => '1112.99',
            'name' => 'Akun Induk Bank Test',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        // 2. Akun anak bank (leaf)
        $childBank = ChartOfAccount::create([
            'code' => '1112.99.01',
            'name' => 'Bank BCA Operasional Test',
            'type' => 'Asset',
            'parent_id' => $parentBank->id,
            'is_active' => true,
        ]);

        // 3. Akun deposito bank (leaf tapi mengandung kata deposito)
        $depositBank = ChartOfAccount::create([
            'code' => '1112.99.02',
            'name' => 'Deposito Berjangka Mandiri Test',
            'type' => 'Asset',
            'parent_id' => $parentBank->id,
            'is_active' => true,
        ]);

        // 4. Akun investasi (leaf tapi mengandung kata investasi)
        $investBank = ChartOfAccount::create([
            'code' => '1112.99.03',
            'name' => 'Investasi Reksadana Bank Test',
            'type' => 'Asset',
            'parent_id' => $parentBank->id,
            'is_active' => true,
        ]);

        $options = VendorPaymentAccounts::options('Bank Transfer');

        // Akun induk HARUS TIDAK ada
        $this->assertArrayNotHasKey($parentBank->id, $options, 'Akun induk tidak boleh muncul di opsi COA');

        // Akun deposito HARUS TIDAK ada
        $this->assertArrayNotHasKey($depositBank->id, $options, 'Akun deposito tidak boleh muncul di opsi COA');

        // Akun investasi HARUS TIDAK ada
        $this->assertArrayNotHasKey($investBank->id, $options, 'Akun investasi tidak boleh muncul di opsi COA');

        // Akun operasional anak HARUS ada
        $this->assertArrayHasKey($childBank->id, $options, 'Akun anak operasional harus muncul di opsi COA');
    }

    /**
     * Bug E: Invoice STATUS_SENT label is unified to 'Menunggu Pembayaran'
     */
    public function test_invoice_status_sent_label_is_menunggu_pembayaran(): void
    {
        $this->assertEquals('Menunggu Pembayaran', Invoice::STATUS_LABELS[Invoice::STATUS_SENT]);
        $this->assertEquals('Menunggu Pembayaran', StatusLabels::label('invoice', Invoice::STATUS_SENT));
        $this->assertEquals('Menunggu Pembayaran', StatusLabels::label('invoice', 'sent'));
    }

    /**
     * Bug F: QC header reason_reject is aggregated from rejected items
     */
    public function test_quality_control_aggregates_reason_reject_to_header(): void
    {
        Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

        $supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
            'perusahaan' => 'Supplier Test 8',
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $poItem1 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 75000,
        ]);

        $product2 = Product::factory()->forCabang($this->cabang)->create([
            'name' => 'Produk Test 8-B',
            'cost_price' => 50000,
        ]);

        $poItem2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product2->id,
            'quantity' => 5,
            'unit_price' => 50000,
        ]);

        $qc = QualityControl::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'purchase_order_id' => $po->id,
            'product_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'cabang_id' => $this->cabang->id,
            'quantity_received' => 15,
            'passed_quantity' => 12,
            'rejected_quantity' => 3,
            'status' => 0,
            'inspected_by' => $this->user->id,
            'reason_reject' => null,
        ]);

        // Item 1: 8 passed, 2 rejected (reason: Dimensi cacat)
        QualityControlItem::create([
            'quality_control_id' => $qc->id,
            'purchase_order_item_id' => $poItem1->id,
            'product_id' => $this->product->id,
            'quantity_received' => 10,
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'failed_qc_action' => 'wait_next_delivery',
            'reason_reject' => 'Dimensi cacat',
            'rak_id' => $this->rak->id,
            'status' => 0,
        ]);

        // Item 2: 4 passed, 1 rejected (reason: Karat pada ulir)
        QualityControlItem::create([
            'quality_control_id' => $qc->id,
            'purchase_order_item_id' => $poItem2->id,
            'product_id' => $product2->id,
            'quantity_received' => 5,
            'passed_quantity' => 4,
            'rejected_quantity' => 1,
            'failed_qc_action' => 'wait_next_delivery',
            'reason_reject' => 'Karat pada ulir',
            'rak_id' => $this->rak->id,
            'status' => 0,
        ]);

        $qcService = app(QualityControlService::class);
        $qcService->completeQualityControl($qc, []);

        $qc->refresh();
        $this->assertNotEmpty($qc->reason_reject);
        $this->assertStringContainsString('Dimensi cacat', $qc->reason_reject);
        $this->assertStringContainsString('Karat pada ulir', $qc->reason_reject);
    }
}
