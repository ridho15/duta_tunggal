<?php

namespace Tests\Feature;

use App\Filament\Resources\StockTransferResource\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransferResource\Pages\EditStockTransfer;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReturnProductService;
use App\Services\StockTransferService;
use App\Services\SuratJalanService;
use App\Support\OrderRequestQuantityLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class Sprint2LogisticsAndStockVerificationTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Customer $customer;
    private Supplier $supplier;
    private Warehouse $warehouseSource;
    private Warehouse $warehouseDest;
    private Rak $rakSource;
    private Product $product;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $goodsDeliveryCoa;
    private ChartOfAccount $revenueCoa;
    private ChartOfAccount $apCoa;
    private ChartOfAccount $unbilledPurchaseCoa;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'exchange_rate' => 1.0,
                'is_default' => true,
                'status' => 1,
            ]
        );

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-VERIF-S2'],
            [
                'nama' => 'Cabang Verifikasi Sprint 2',
                'alamat' => 'Jl. Verifikasi Logistik No. 2',
                'status' => 1,
                'lihat_stok_cabang_lain' => true,
            ]
        );

        $this->user = User::factory()->create([
            'username' => 'verif_s2_' . uniqid(),
            'email' => 'verif_s2_' . uniqid() . '@example.com',
            'kode_user' => 'V2' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        Auth::login($this->user);

        $this->customer = Customer::factory()->create([
            'name' => 'Customer Verif S2',
            'perusahaan' => 'PT Customer Verif S2',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        // Accounting COAs
        $this->inventoryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.01'],
            ['name' => 'Persediaan Barang Dagangan S2', 'type' => 'Asset', 'status' => 1]
        );

        $this->goodsDeliveryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Barang Terkirim (In Transit) S2', 'type' => 'Asset', 'status' => 1]
        );

        $this->revenueCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Pendapatan Penjualan', 'type' => 'Revenue', 'status' => 1]
        );

        $this->apCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2110'],
            ['name' => 'Hutang Dagang', 'type' => 'Liability', 'status' => 1]
        );

        $this->unbilledPurchaseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2130.01'],
            ['name' => 'Hutang Belum Difakturkan', 'type' => 'Liability', 'status' => 1]
        );

        // Warehouses (Source has no rak by default or tests both, Destination has no rak)
        $this->warehouseSource = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-SRC-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Sumber Tanpa Rak',
            'location' => 'Area Sumber',
            'status' => 1,
        ]);

        $this->warehouseDest = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-DST-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Tujuan Tanpa Rak',
            'location' => 'Area Tujuan',
            'status' => 1,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Produk Logistik Sprint 2',
            'sku' => 'SKU-S2-' . strtoupper(substr(uniqid(), -4)),
            'inventory_coa_id' => $this->inventoryCoa->id,
            'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
            'unbilled_purchase_coa_id' => $this->unbilledPurchaseCoa->id,
            'cost_price' => 50000,
        ]);
    }

    /**
     * BUG 2 (KRITIS - P0):
     * Transfer stok dari gudang tanpa Rak: item tersimpan dengan from_rak_id = null & to_rak_id = null,
     * dan approval berhasil memutasi stok fisik serta mencatat kartu stok (StockMovement).
     */
    public function test_bug_2_stock_transfer_without_rak_preserves_item_and_executes_movements(): void
    {
        // 1. Setup initial stock in warehouseSource (no rak -> rak_id = null)
        $initialSourceStock = InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouseSource->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 25,
                'qty_reserved' => 0,
            ]
        );


        // 2. Create Stock Transfer
        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $this->warehouseDest->id,
            'cabang_id' => $this->cabang->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
            'notes' => 'Transfer uji tanpa rak',
        ]);

        // 3. Create Stock Transfer Item with null rak_ids
        $transferItem = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouseSource->id,
            'from_rak_id' => null,
            'to_warehouse_id' => $this->warehouseDest->id,
            'to_rak_id' => null,
            'quantity' => 10,
        ]);

        // Assert item exists and rak_id fields remain NULL (not converted to empty string or 0)
        $this->assertDatabaseHas('stock_transfer_items', [
            'id' => $transferItem->id,
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouseSource->id,
            'from_rak_id' => null,
            'to_warehouse_id' => $this->warehouseDest->id,
            'to_rak_id' => null,
            'quantity' => 10,
        ]);

        // 4. Request transfer
        $service = app(StockTransferService::class);
        $transfer = $service->requestTransfer($transfer);
        $this->assertEquals('Request', $transfer->status);

        // 5. Approve transfer
        $approvedTransfer = $service->approveStockTransfer($transfer);
        $this->assertEquals('Approved', $approvedTransfer->status);

        // 6. Verify physical stock mutations
        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseSource->id)
            ->whereNull('rak_id')
            ->first();
        $this->assertNotNull($sourceStock);
        $this->assertEquals(15.0, (float) $sourceStock->qty_available);

        $destStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseDest->id)
            ->whereNull('rak_id')
            ->first();
        $this->assertNotNull($destStock);
        $this->assertEquals(10.0, (float) $destStock->qty_available);

        // 7. Verify StockMovement entries created for both sides
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseSource->id,
            'rak_id' => null,
            'type' => 'transfer_out',
            'quantity' => 10,
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $transfer->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseDest->id,
            'rak_id' => null,
            'type' => 'transfer_in',
            'quantity' => 10,
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $transfer->id,
        ]);
    }

    /**
     * BUG 2 (KRITIS - P0):
     * Form transfer stok menolak penyimpanan transfer kosong (0 items) pada Create & Edit page,
     * serta service menolak request transfer tanpa item.
     */
    public function test_bug_2_stock_transfer_rejects_empty_items_on_form_and_service(): void
    {
        // 1. Test CreateStockTransfer mutation validation with empty items
        $createPage = new class extends CreateStockTransfer {
            public function invokeMutate(array $data): array {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $this->expectException(ValidationException::class);
        $createPage->invokeMutate([
            'transfer_number' => 'TRF-EMPTY',
            'stockTransferItem' => [],
        ]);
    }

    /**
     * BUG 2 (KRITIS - P0) lanjutan:
     * EditStockTransfer mutation juga menolak transfer tanpa item.
     */
    public function test_bug_2_edit_stock_transfer_rejects_empty_items(): void
    {
        $editPage = new class extends EditStockTransfer {
            public function invokeMutate(array $data): array {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $this->expectException(ValidationException::class);
        $editPage->invokeMutate([
            'transfer_number' => 'TRF-EMPTY-EDIT',
            'stockTransferItem' => [],
        ]);
    }

    /**
     * BUG 2 (KRITIS - P0) lanjutan:
     * StockTransferService::requestTransfer menolak transfer yang tidak memiliki item di database.
     */
    public function test_bug_2_service_rejects_request_on_transfer_without_items(): void
    {
        $emptyTransfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouseSource->id,
            'to_warehouse_id' => $this->warehouseDest->id,
            'cabang_id' => $this->cabang->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $this->expectException(ValidationException::class);
        app(StockTransferService::class)->requestTransfer($emptyTransfer);
    }

    /**
     * BUG 3 (KRITIS - P0):
     * Return Product (Customer Return): saat disetujui, stok fisik bertambah kembali di inventory_stocks,
     * kartu stok tercatat dengan type 'return_in', dan jurnal pembalik dibuat secara seimbang.
     */
    public function test_bug_3_return_product_approval_restores_stock_records_movement_and_creates_journal(): void
    {
        // 1. Initial stock in warehouseSource before Delivery Order: 15
        $stock = InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouseSource->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 15,
                'qty_reserved' => 0,
            ]
        );

        // 2. Setup Sale Order and Delivery Order (10 sent, remaining stock: 5)
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'warehouse_id' => $this->warehouseSource->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'sent',
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // Simulate stock after delivery order was sent: 5 units remaining
        $stock->update([
            'qty_available' => 5,
        ]);

        // 3. Create ReturnProduct for 4 units
        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $deliveryOrder->id,
            'warehouse_id' => $this->warehouseSource->id,
            'status' => 'draft',
            'reason' => 'Barang cacat kemasan',
            'return_action' => 'reduce_quantity_only',
        ]);

        $returnItem = ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'condition' => 'damage',
        ]);

        // 4. Approve Return Product via ReturnProductService
        $returnService = app(ReturnProductService::class);
        $returnService->updateQuantityFromModel($returnProduct);

        // A. Assert ReturnProduct status updated to approved
        $returnProduct->refresh();
        $this->assertEquals('approved', $returnProduct->status);

        // B. Assert DeliveryOrderItem quantity reduced (10 - 4 = 6)
        $doItem->refresh();
        $this->assertEquals(6.0, (float) $doItem->quantity);

        // C. Assert physical stock is restored in inventory_stocks (5 + 4 = 9)
        $stock->refresh();
        $this->assertEquals(9.0, (float) $stock->qty_available);

        // D. Assert StockMovement of type 'return_in' was created
        $movement = StockMovement::where('from_model_type', ReturnProduct::class)
            ->where('from_model_id', $returnProduct->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('customer_return', $movement->type);
        $this->assertEquals(4.0, (float) $movement->quantity);
        $this->assertEquals($this->warehouseSource->id, $movement->warehouse_id);
        $this->assertEquals(200000.0, (float) $movement->value); // 4 * 50,000

        // E. Assert Journal Entries were created and are balanced
        $journals = JournalEntry::where('source_type', ReturnProduct::class)
            ->where('source_id', $returnProduct->id)
            ->get();

        $this->assertNotEmpty($journals, 'Jurnal pembalik retur produk harus tercipta.');
        $totalDebit = $journals->sum('debit');
        $totalCredit = $journals->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit, 'Jurnal retur harus seimbang (balance).');
        $this->assertEquals(200000.0, (float) $totalDebit);

        // Debit should be Persediaan (inventoryCoa) and Credit should be Barang Terkirim (goodsDeliveryCoa)
        $debitEntry = $journals->where('coa_id', $this->inventoryCoa->id)->first();
        $creditEntry = $journals->where('coa_id', $this->goodsDeliveryCoa->id)->first();

        $this->assertNotNull($debitEntry, 'Akun Persediaan harus didebit.');
        $this->assertEquals(200000.0, (float) $debitEntry->debit);
        $this->assertNotNull($creditEntry, 'Akun Barang Terkirim harus dikredit.');
        $this->assertEquals(200000.0, (float) $creditEntry->credit);
    }

    /**
     * BUG 5 (TINGGI - P1):
     * Qty Penerimaan Barang (Purchase Receipt) tidak boleh melebihi batas Qty PO.
     * Validasi model-level melempar InvalidArgumentException jika melebihi kuota PO.
     */
    public function test_bug_5_purchase_receipt_cannot_exceed_po_quantity(): void
    {
        // 1. Create Purchase Order with 10 units
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-S2-' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 500000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'currency_id' => Currency::where('code', 'IDR')->first()->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'total_amount' => 500000,
        ]);

        // 2. First receipt of 7 units -> Valid
        $receipt1 = PurchaseReceipt::create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'received_by' => $this->user->id,
            'currency_id' => Currency::where('code', 'IDR')->first()->id,
            'receipt_number' => 'RCV-S2-001',
            'receipt_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $rcvItem1 = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt1->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseSource->id,
            'qty_received' => 7,
            'qty_accepted' => 7,
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $rcvItem1->id,
            'qty_received' => 7,
        ]);

        // 3. Check remaining limit: 10 - 7 = 3
        $limitData = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit($poItem->id);
        $this->assertEquals(3.0, (float) $limitData['remaining_accepted']);

        // 4. Second receipt attempting 5 units (7 + 5 = 12 > 10) -> Throws InvalidArgumentException
        $receipt2 = PurchaseReceipt::create([
            'purchase_order_id' => $purchaseOrder->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'received_by' => $this->user->id,
            'currency_id' => Currency::where('code', 'IDR')->first()->id,
            'receipt_number' => 'RCV-S2-002',
            'receipt_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $exceptionCaught = false;
        try {
            PurchaseReceiptItem::create([
                'purchase_receipt_id' => $receipt2->id,
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouseSource->id,
                'qty_received' => 5,
                'qty_accepted' => 5,
            ]);
        } catch (\InvalidArgumentException $e) {
            $exceptionCaught = true;
            $this->assertStringContainsString('tidak boleh melebihi sisa PO', $e->getMessage());
        }

        $this->assertTrue($exceptionCaught, 'Observer harus memblokir penerimaan melebihi sisa PO.');

        // 5. Receiving exact remaining (3 units) -> Valid
        $rcvItem2 = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt2->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseSource->id,
            'qty_received' => 3,
            'qty_accepted' => 3,
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $rcvItem2->id,
            'qty_received' => 3,
        ]);

        // Now limit should be 0
        $finalLimitData = OrderRequestQuantityLock::purchaseOrderItemReceiptLimit($poItem->id);
        $this->assertEquals(0.0, (float) $finalLimitData['remaining_accepted']);
    }

    /**
     * BUG 6 (TINGGI - P1):
     * Validasi Surat Jalan vs Delivery Order:
     * Surat Jalan hanya boleh dibuat / dikaitkan dengan Delivery Order yang berstatus 'approved'.
     * Status non-approved ('draft', 'reject', 'closed', 'cancelled') ditolak dengan ValidationException.
     */
    public function test_bug_6_surat_jalan_rejects_delivery_orders_with_non_approved_status(): void
    {
        $suratJalanService = app(SuratJalanService::class);

        // 1. DO with status 'draft' -> Rejected
        $draftDo = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'status' => 'draft',
            'do_number' => 'DO-DRAFT-01',
        ]);

        $draftException = false;
        try {
            $suratJalanService->assertDeliveryOrdersUsable(collect([$draftDo]));
        } catch (ValidationException $e) {
            $draftException = true;
            $msg = $e->validator->errors()->first('deliveryOrder');
            $this->assertStringContainsString('hanya dapat dibuat dari Delivery Order berstatus approved', $msg);
            $this->assertStringContainsString('DO-DRAFT-01', $msg);
        }
        $this->assertTrue($draftException, 'DO Draft harus ditolak untuk Surat Jalan.');

        // 2. DO with status 'reject' -> Rejected
        $rejectDo = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'status' => 'reject',
            'do_number' => 'DO-REJECT-01',
        ]);

        $rejectException = false;
        try {
            $suratJalanService->assertDeliveryOrdersUsable(collect([$rejectDo]));
        } catch (ValidationException $e) {
            $rejectException = true;
            $msg = $e->validator->errors()->first('deliveryOrder');
            $this->assertStringContainsString('DO-REJECT-01', $msg);
        }
        $this->assertTrue($rejectException, 'DO Reject harus ditolak untuk Surat Jalan.');

        // 3. DO with status 'closed' -> Rejected
        $closedDo = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'status' => 'closed',
            'do_number' => 'DO-CLOSED-01',
        ]);

        $closedException = false;
        try {
            $suratJalanService->assertDeliveryOrdersUsable(collect([$closedDo]));
        } catch (ValidationException $e) {
            $closedException = true;
            $msg = $e->validator->errors()->first('deliveryOrder');
            $this->assertStringContainsString('DO-CLOSED-01', $msg);
        }
        $this->assertTrue($closedException, 'DO Closed harus ditolak untuk Surat Jalan.');

        // 4. DO with status 'approved' -> Accepted without exception
        $approvedDo = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouseSource->id,
            'status' => 'approved',
            'do_number' => 'DO-APPRV-01',
        ]);

        // Should not throw any exception
        $suratJalanService->assertDeliveryOrdersUsable(collect([$approvedDo]));
        $this->assertTrue(true, 'DO Approved berhasil lolos validasi.');
    }
}
