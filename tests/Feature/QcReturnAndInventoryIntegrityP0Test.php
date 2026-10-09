<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\PurchaseReceiptPolicy;
use App\Services\PurchaseReturnService;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcReturnAndInventoryIntegrityP0Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Rak $rak;
    protected User $user;
    protected Supplier $supplier;
    protected Currency $currency;
    protected ChartOfAccount $inventoryCoa;
    protected ChartOfAccount $apCoa;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\UnitOfMeasure::factory()->create();

        $this->cabang = Cabang::create([
            'kode' => 'JKT',
            'nama' => 'Cabang Jakarta',
            'alamat' => 'Jakarta Pusat',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Gudang Logistik Jakarta',
        ]);

        $this->rak = Rak::create([
            'code' => 'RAK-A1',
            'name' => 'Rak A1',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
            'is_default' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Logistics Admin',
            'email' => 'logistics@example.com',
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);

        $this->inventoryCoa = ChartOfAccount::create([
            'code' => '1140.01',
            'name' => 'PERSEDIAAN BARANG DAGANGAN',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->apCoa = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'HUTANG USAHA',
            'type' => 'Liability',
            'is_active' => true,
        ]);
    }

    /**
     * P0-3: Approving a Purchase Return for QC-rejected items must NEVER decrement
     * available good inventory stock or create fictitious inventory reversal journals.
     */
    public function test_purchase_return_for_qc_reject_does_not_decrement_available_stock_or_create_fictitious_inventory_journals(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'inventory_coa_id' => $this->inventoryCoa->id,
        ]);

        // PO for 10 units @ Rp 100,000
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'po_number' => 'PO-QC-001',
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 1000000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'currency_id' => $this->currency->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        // QC: 8 passed, 2 rejected
        $qc = QualityControl::create([
            'qc_number' => 'QC-20261008-001',
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'quantity_received' => 10,
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 1, // processed
            'cabang_id' => $this->cabang->id,
            'rak_id' => $this->rak->id,
        ]);

        // Good stock (8 units) enters inventory
        $stock = InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 8,
            'qty_reserved' => 0,
        ]);

        // Purchase Receipt with 8 accepted and 2 rejected
        $receipt = PurchaseReceipt::create([
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'receipt_number' => 'RC-QC-001',
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 8,
            'qty_rejected' => 2,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'rak_id' => $this->rak->id,
        ]);

        // Return generated for the 2 rejected units
        $purchaseReturn = PurchaseReturn::create([
            'purchase_receipt_id' => $receipt->id,
            'quality_control_id' => $qc->id,
            'failed_qc_action' => PurchaseReturn::QC_ACTION_RETURN_SUPPLIER,
            'nota_retur' => 'RET-QC-001',
            'return_date' => now(),
            'created_by' => $this->user->id,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
            'notes' => 'Auto-generated return for items rejected during receiving',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_receipt_item_id' => $receiptItem->id,
            'product_id' => $product->id,
            'qty_returned' => 2,
            'unit_price' => 100000,
            'reason' => 'Quality Control Rejection',
        ]);

        $this->assertTrue($purchaseReturn->isQcReturn(), 'Purchase return must be recognized as QC Return');

        // Approve the purchase return
        $returnService = app(PurchaseReturnService::class);
        $returnService->approve($purchaseReturn);

        $purchaseReturn->refresh();
        $this->assertEquals('approved', $purchaseReturn->status);

        // Assert: Available stock must REMAIN 8 units (NOT reduced to 6!)
        $stock->refresh();
        $this->assertEquals(8, (float) $stock->qty_available, 'Available stock must remain exactly 8 and NOT be reduced by QC reject return');

        // Assert: No inventory GL credit journal entry created by this return
        $journalsCount = JournalEntry::where('source_type', PurchaseReturn::class)
            ->where('source_id', $purchaseReturn->id)
            ->count();
        $this->assertEquals(0, $journalsCount, 'QC reject return must not generate trade inventory reversal journal');
    }

    /**
     * P0-4: PurchaseReceiptPolicy and Model deleting event must strictly BLOCK
     * deleting completed or partial purchase receipts to prevent ghost stock.
     */
    public function test_purchase_receipt_policy_and_model_block_deleting_completed_receipts(): void
    {
        $this->actingAs($this->user);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete purchase receipt']);
        $this->user->givePermissionTo('delete purchase receipt');

        $policy = new PurchaseReceiptPolicy();

        $completedReceipt = PurchaseReceipt::create([
            'cabang_id' => $this->cabang->id,
            'receipt_number' => 'RC-CMPL-001',
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
        ]);

        $draftReceipt = PurchaseReceipt::create([
            'cabang_id' => $this->cabang->id,
            'receipt_number' => 'RC-DRFT-001',
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'draft',
        ]);

        // Policy check: completed receipt delete is BLOCKED
        $this->assertFalse($policy->delete($this->user, $completedReceipt), 'Policy must disallow deleting completed receipt');
        $this->assertFalse($policy->forceDelete($this->user, $completedReceipt), 'Policy must disallow force deleting completed receipt');

        // Policy check: draft receipt delete is ALLOWED
        $this->assertTrue($policy->delete($this->user, $draftReceipt), 'Policy must allow deleting draft receipt');

        // Model check: attempting to delete completed receipt throws Exception
        $this->expectException(\Exception::class);
        $completedReceipt->delete();
    }

    /**
     * P0-5: StockOpnameService must successfully approve physical counts on racked warehouses
     * without deadlock, gracefully resolving any unassigned racks.
     */
    public function test_stock_opname_approval_succeeds_without_deadlock_when_warehouse_has_racks(): void
    {
        $this->actingAs($this->user);

        $product1 = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'cost_price' => 50000,
        ]);

        // Normal stock with rack
        InventoryStock::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 15,
            'qty_reserved' => 0,
        ]);

        // Legacy/unassigned ghost stock with rak_id = null and qty_available = 0
        InventoryStock::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => null,
            'qty_available' => 0,
            'qty_reserved' => 0,
        ]);

        // Create stock opname
        $opname = StockOpname::create([
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'opname_number' => 'SO-20261008-001',
            'opname_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $service = app(StockOpnameService::class);

        // 1. Start physical count
        $service->startPhysicalCount($opname);

        $opname->refresh();
        $this->assertEquals('in_progress', $opname->status);

        // Verify ghost row (rak_id = null with qty = 0) was NOT added as an opname item
        $unassignedEmptyItems = StockOpnameItem::where('stock_opname_id', $opname->id)
            ->whereNull('rak_id')
            ->where('system_qty', '<=', 0)
            ->count();
        $this->assertEquals(0, $unassignedEmptyItems, 'Ghost row with rak_id NULL and 0 qty must be skipped');

        // 2. Complete count
        foreach ($opname->items as $item) {
            $item->update([
                'physical_qty' => $item->system_qty, // Exact match
            ]);
        }
        $opname->update(['status' => 'completed']);

        // 3. Approve stock opname (must NOT throw ValidationException deadlock)
        $approvedOpname = $service->approveStockOpname($opname, $this->user->id);

        $this->assertEquals('approved', $approvedOpname->status, 'Stock Opname on racked warehouse must approve successfully without deadlock');
    }
}
