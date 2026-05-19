<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBiaya;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReceiptBiaya;
use App\Models\PurchaseReceiptPhoto;
use App\Models\PurchaseReceiptItemPhoto;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BalanceSheetService;
use App\Services\QualityControlService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QcBeforeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
        $this->currency = Currency::factory()->create([
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'code' => 'IDR',
            'to_rupiah' => 1,
        ]);

        // configure product COA
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();

        if ($inventoryCoa) $this->product->inventory_coa_id = $inventoryCoa->id;
        if ($unbilledPurchaseCoa) $this->product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
        if ($temporaryProcurementCoa) $this->product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
        $this->product->save();

        $this->actingAs($this->user);
    }

    #[Test]
    public function qc_from_po_item_creates_receipt_and_posts_journals()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertNotNull($qc);
        $this->assertDatabaseHas('quality_controls', [
            'id' => $qc->id,
            'from_model_type' => \App\Models\PurchaseOrderItem::class,
        ]);

        $qcService->completeQualityControl($qc, []);

        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'qty_accepted' => 5,
        ]);

        $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($receiptItem);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
            'journal_type' => 'inventory',
            'description' => 'Debit inventory for receipt item ' . $receiptItem->id,
            'debit' => 50000,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
            'journal_type' => 'inventory',
            'description' => 'Inventory Posting - Credit unbilled purchase for receipt item ' . $receiptItem->id,
            'credit' => 50000,
        ]);

        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);

        $this->assertEquals('posted', $result['status']);
    }

    #[Test]
    public function purchase_receipt_inventory_journal_uses_idr_amount_for_usd_po_item()
    {
        $usd = Currency::factory()->create([
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'to_rupiah' => 15000,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $usd->id,
            'nominal' => 16000,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 5,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
            'currency_id' => $usd->id,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'RN-USD-JOURNAL',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $usd->id,
            'status' => 'completed',
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        $result = app(\App\Services\PurchaseReceiptService::class)->postPurchaseReceipt($receipt);

        $this->assertEquals('posted', $result['status']);

        $debitEntry = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('journal_type', 'inventory')
            ->where('debit', '>', 0)
            ->first();
        $creditEntry = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('journal_type', 'inventory')
            ->where('credit', '>', 0)
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals(80000.0, (float) $debitEntry->debit);
        $this->assertEquals(80000.0, (float) $creditEntry->credit);
        $this->assertEquals($usd->id, (int) $debitEntry->currency_id);
        $this->assertEquals(16000.0, (float) $debitEntry->exchange_rate);
        $this->assertEquals(5.0, (float) $debitEntry->amount_original_currency);

        $stockMovement = StockMovement::where('from_model_type', PurchaseReceiptItem::class)
            ->where('from_model_id', $receiptItem->id)
            ->where('type', 'purchase_in')
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals(80000.0, (float) $stockMovement->value);
        $this->assertEquals(5.0, (float) $stockMovement->meta['raw_unit_price']);
        $this->assertEquals(80000.0, (float) $stockMovement->meta['unit_cost_idr']);
        $this->assertEquals(16000.0, (float) $stockMovement->meta['exchange_rate']);
    }

    #[Test]
    public function qc_complete_auto_receipt_posts_usd_inventory_journal_in_idr()
    {
        $usd = Currency::factory()->create([
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'to_rupiah' => 15000,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $usd->id,
            'nominal' => 16000,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 5,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
            'currency_id' => $usd->id,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 1,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $qcService->completeQualityControl($qc, []);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNotNull($receiptItem);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
            'journal_type' => 'inventory',
            'debit' => 80000,
            'currency_id' => $usd->id,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
            'journal_type' => 'inventory',
            'credit' => 80000,
            'currency_id' => $usd->id,
        ]);
    }

    #[Test]
    public function temporary_procurement_and_return_product_journals_use_idr_for_usd_receipt_item()
    {
        $usd = Currency::factory()->create([
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'to_rupiah' => 15000,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $usd->id,
            'nominal' => 16000,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 5,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
            'currency_id' => $usd->id,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'RN-USD-TEMP',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => $usd->id,
            'status' => 'completed',
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $service = app(\App\Services\PurchaseReceiptService::class);

        $temporaryResult = $service->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
        $returnResult = $service->postReturnProduct($receiptItem, 'Failed QC');

        $this->assertEquals('posted', $temporaryResult['status']);
        $this->assertEquals('posted', $returnResult['status']);

        $procurementEntries = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('journal_type', 'procurement')
            ->get();

        $returnEntries = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('journal_type', 'return')
            ->get();

        $this->assertEquals(80000.0, (float) $procurementEntries->sum('debit'));
        $this->assertEquals(80000.0, (float) $procurementEntries->sum('credit'));
        $this->assertEquals(80000.0, (float) $returnEntries->sum('debit'));
        $this->assertEquals(80000.0, (float) $returnEntries->sum('credit'));
        $this->assertTrue($procurementEntries->every(fn ($entry) => (float) $entry->exchange_rate === 16000.0));
        $this->assertTrue($returnEntries->every(fn ($entry) => (float) $entry->exchange_rate === 16000.0));

        $balanceSheet = app(BalanceSheetService::class)->generate();
        $this->assertTrue($balanceSheet['is_balanced']);
        $this->assertLessThan(0.01, abs((float) $balanceSheet['difference']));
    }

    #[Test]
    public function qc_from_po_item_copies_purchase_order_biaya_to_receipt()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_price' => 12500,
        ]);

        $poBiaya = PurchaseOrderBiaya::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->currency->id,
            'coa_id' => ChartOfAccount::where('code', '6100.02')->first()?->id,
            'nama_biaya' => 'Biaya Handling',
            'total' => 25000,
            'untuk_pembelian' => 0,
            'masuk_invoice' => 1,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 4,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $qcService->completeQualityControl($qc, []);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $this->assertDatabaseHas('purchase_receipt_biayas', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_biaya_id' => $poBiaya->id,
            'nama_biaya' => 'Biaya Handling',
            'total' => 25000,
        ]);

        $receiptBiaya = PurchaseReceiptBiaya::where('purchase_receipt_id', $receipt->id)
            ->where('purchase_order_biaya_id', $poBiaya->id)
            ->first();

        $this->assertNotNull($receiptBiaya);
        $this->assertSame('Biaya Handling', $receiptBiaya->nama_biaya);
        $this->assertEquals(25000, (float) $receiptBiaya->total);
        $this->assertEquals(1, (int) $receiptBiaya->masuk_invoice);
    }

    #[Test]
    public function qc_handles_rejected_quantities_correctly()
    {
        // create approved purchase order and item
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        // create QC with some rejected items
        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 7,  // 7 passed
            'rejected_quantity' => 3, // 3 rejected
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertNotNull($qc);
        $this->assertEquals(7, $qc->passed_quantity);
        $this->assertEquals(3, $qc->rejected_quantity);

        // complete QC
        $qcService->completeQualityControl($qc, [
            'item_condition' => 'damage',
            'notes' => '3 items damaged during transport'
        ]);

        // assert receipt was created with correct quantities
        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals(10, $receiptItem->qty_received); // total received
        $this->assertEquals(7, $receiptItem->qty_accepted);  // only accepted items
        $this->assertEquals(3, $receiptItem->qty_rejected);  // rejected items

        // post receipt
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);
        $this->assertEquals('posted', $result['status']);

        // verify inventory only reflects accepted quantity
        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(7.0, (float) $inventoryStock->qty_available); // only accepted qty in inventory
    }

    #[Test]
    public function qc_approval_updates_inventory_stock()
    {
        // create approved purchase order and item
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 20000,
        ]);

        // create QC with all items passed
        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // complete QC with approval
        $qcService->completeQualityControl($qc, [
            'item_condition' => 'good',
            'notes' => 'All items passed QC'
        ]);

        // get the created receipt
        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        // post receipt to update inventory
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);
        $this->assertEquals('posted', $result['status']);

        // verify inventory stock was updated
        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_available);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_on_hand);

        // verify stock movement was recorded
        $stockMovement = \App\Models\StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', 'purchase_in')
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals(5.0, (float) $stockMovement->quantity);
        $this->assertEquals('purchase_in', $stockMovement->type);
    }

    #[Test]
    public function qc_rejection_prevents_inventory_update()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 0,
            'rejected_quantity' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $qcService->completeQualityControl($qc, [
            'item_condition' => 'damage',
            'notes' => 'All items rejected due to quality issues'
        ]);

        $this->assertDatabaseMissing('purchase_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        if ($inventoryStock) {
            $this->assertEquals(0.0, (float) $inventoryStock->qty_available);
        }

        $this->assertDatabaseMissing('purchase_receipt_items', [
            'purchase_order_item_id' => $poItem->id,
        ]);
    }

    #[Test]
    public function qc_with_missing_coa_accounts_skips_journals()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 5000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 3,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // should not throw
        $qcService->completeQualityControl($qc, []);

        $this->assertDatabaseHas('quality_controls', ['id' => $qc->id]);
        $this->assertFalse(JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qc->id)
            ->exists(), 'No QC-related journals should be created');
    }
}
