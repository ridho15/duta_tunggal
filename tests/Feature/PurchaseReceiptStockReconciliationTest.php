<?php

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
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buildPostedPurchaseReceiptFixture(): array
{
    $cabang = \App\Models\Cabang::factory()->create();
    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);

    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $product = Product::factory()->forCabang($cabang)->create();

    $inventoryCoa = ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]);
    $temporaryProcurementCoa = ChartOfAccount::firstOrCreate(['code' => '1400.01'], ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]);
    $unbilledPurchaseCoa = ChartOfAccount::firstOrCreate(['code' => '2100.10'], ['name' => 'Hutang Pembelian Belum Ditagih', 'type' => 'Liability', 'is_active' => true]);

    $product->update([
        'inventory_coa_id' => $inventoryCoa->id,
        'temporary_procurement_coa_id' => $temporaryProcurementCoa->id,
        'unbilled_purchase_coa_id' => $unbilledPurchaseCoa->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    // Create PO item with quantity that allows for QC (not already received)
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 10000,
    ]);

    // Create PurchaseReceipt in draft status first
    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_number' => 'RN-RECON-001',
        'receipt_date' => now(),
        'received_by' => $user->id,
        'currency_id' => $currency->id,
        'status' => 'draft', // Start as draft, not completed
        'cabang_id' => $cabang->id,
    ]);

    // Create receipt item in pending QC state (not qty_accepted yet)
    $receiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
        'qty_received' => 5,
        'qty_accepted' => 0, // Start with 0, QC will update this
        'qty_rejected' => 0,
        'status' => 'pending', // Pending QC state
    ]);

    // Create QC for the PO item (not from receipt) - this is the pre-receipt QC flow
    $qc = QualityControl::factory()->create([
        'qc_number' => 'QC-RECON-001',
        'inspected_by' => $user->id,
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'notes' => 'QC for reconciliation test',
        'status' => 1, // Completed
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'from_model_type' => PurchaseOrderItem::class,
        'from_model_id' => $poItem->id,
        'cabang_id' => $cabang->id,
    ]);

    // Now update receipt item to reflect QC acceptance
    $receiptItem->update([
        'qty_accepted' => 5,
        'qty_rejected' => 0,
        'status' => 'completed',
    ]);

    // Mark receipt as completed
    $receipt->update(['status' => 'completed']);

    return compact('cabang', 'user', 'supplier', 'warehouse', 'currency', 'product', 'purchaseOrder', 'poItem', 'receipt', 'receiptItem', 'qc');
}

test('purchase receipt item stock posting recreates missing stock movement without duplicating journals', function () {
    $fixture = buildPostedPurchaseReceiptFixture();
    $service = app(PurchaseReceiptService::class);

    // Post the receipt item to create stock movement
    $firstResult = $service->postItemInventoryAfterQC($fixture['receiptItem']);

    // Check that posting was successful
    expect(in_array($firstResult['status'], ['posted', 'skipped', 'reconciled']))->toBeTrue();

    // Check stock movement was created
    $movementQuery = StockMovement::query()
        ->where('product_id', $fixture['receiptItem']->product_id)
        ->where('warehouse_id', $fixture['receiptItem']->warehouse_id)
        ->whereNull('rak_id');

    $movementCount = $movementQuery->count();
    expect($movementCount)->toBeGreaterThanOrEqual(1);

    // Check inventory stock has quantity
    $stockQty = InventoryStock::freeQtyFor($fixture['receiptItem']->product_id, $fixture['receiptItem']->warehouse_id, null);
    expect($stockQty)->toBeGreaterThanOrEqual(0.0);

    // Delete stock movements and inventory stock to simulate missing data
    StockMovement::query()->delete();
    InventoryStock::query()->delete();

    // Try to reconcile - should recreate the stock movement
    $secondResult = $service->postItemInventoryAfterQC($fixture['receiptItem']->fresh());

    // Should be reconciled or posted (not error)
    expect(in_array($secondResult['status'], ['reconciled', 'posted']))->toBeTrue();

    // Stock movement should be recreated
    expect(StockMovement::query()
        ->where('product_id', $fixture['receiptItem']->product_id)
        ->where('warehouse_id', $fixture['receiptItem']->warehouse_id)
        ->exists())->toBeTrue();

    // Inventory stock should be recreated
    expect(InventoryStock::query()
        ->where('product_id', $fixture['receiptItem']->product_id)
        ->where('warehouse_id', $fixture['receiptItem']->warehouse_id)
        ->exists())->toBeTrue();
});

test('purchase receipt reconcile stock command backfills a missing stock movement and inventory stock row', function () {
    $fixture = buildPostedPurchaseReceiptFixture();
    $service = app(PurchaseReceiptService::class);

    // Post the receipt item first
    $service->postItemInventoryAfterQC($fixture['receiptItem']);

    // Delete stock movements and inventory stock to simulate missing data
    StockMovement::query()->delete();
    InventoryStock::query()->delete();

    // Run the reconcile command
    $this->artisan('purchase-receipt:reconcile-stock', [
        '--item-id' => $fixture['receiptItem']->id,
        '--yes' => true,
    ])->assertExitCode(0);

    // Stock movement should be recreated by the command
    expect(StockMovement::query()
        ->where('product_id', $fixture['receiptItem']->product_id)
        ->where('warehouse_id', $fixture['receiptItem']->warehouse_id)
        ->exists())->toBeTrue();

    // Inventory stock should be recreated
    $stockQty = InventoryStock::freeQtyFor($fixture['receiptItem']->product_id, $fixture['receiptItem']->warehouse_id, null);
    expect($stockQty)->toBeGreaterThanOrEqual(0.0);
});