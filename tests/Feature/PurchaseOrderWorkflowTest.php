<?php

use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure required models exist
    \App\Models\UnitOfMeasure::factory()->create();
    Currency::factory()->create();
    Supplier::factory()->create();
});

it('completes full purchase order workflow', function () {
    // Setup
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $currency = Currency::factory()->create();

    // 1. CREATE PO (from Order Request - using existing approved OrderRequest)
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-20251031-0001',
        'order_date' => now(),
        'expected_date' => now()->addDays(7),
        'status' => 'draft',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10.00,
        'unit_price' => 10000,
        'discount' => 0,
        'tax' => 0,
    ]);

    expect($purchaseOrder)
        ->status->toBe('draft')
        ->po_number->toBe('PO-20251031-0001');

    // 2. APPROVAL WORKFLOW - Approve PO
    $purchaseOrder->update(['status' => 'approved']);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('approved');

    // 3. PO ISSUED - Approval stage removed; PO proceeds as 'approved' or directly to receiving in the OR-driven flow.

    // 4. RECEIVING PROCESS - Create Purchase Receipt
    $purchaseReceipt = PurchaseReceipt::factory()->create([
        'receipt_number' => 'RN-20251031-0001',
        'purchase_order_id' => $purchaseOrder->id,
        'receipt_date' => now(),
        'received_by' => $user->id,
        'status' => 'completed',
        'currency_id' => $currency->id,
    ]);

    $receiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $purchaseReceipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_accepted' => 10,
        'qty_rejected' => 0,
        'warehouse_id' => $warehouse->id,
    ]);

    expect($purchaseReceipt)
        ->purchase_order_id->toBe($purchaseOrder->id)
        ->status->toBe('completed');

    // Update PO status to received
    $purchaseOrder->update(['status' => 'completed']);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('completed');

    // 5. QUALITY CONTROL - Create QC and approve
    $qc = QualityControl::factory()->create([
        'qc_number' => 'QC-20251031-0001',
        'inspected_by' => $user->id,
        'passed_quantity' => 10,
        'rejected_quantity' => 0,
        'status' => 1, // processed
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'from_model_id' => $purchaseReceipt->id,
        'from_model_type' => PurchaseReceipt::class,
    ]);

    expect($qc)
        ->passed_quantity->toBe(10)
        ->status->toBe(1);

    // 6. STOCK INBOUND - Create stock movement
    $stockMovement = StockMovement::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'value' => 10000,
        'type' => 'purchase_in',
        'from_model_type' => QualityControl::class,
        'from_model_id' => $qc->id,
    ]);

    // Update inventory stock
    $inventoryStock = InventoryStock::updateOrCreate(
        ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
        ['qty_available' => 0, 'qty_reserved' => 0]
    );

    $newQty = $inventoryStock->qty_available + 10;

    $inventoryStock->update([
        'qty_available' => $newQty,
    ]);

    expect($stockMovement)
        ->product_id->toBe($product->id)
        ->type->toBe('purchase_in')
        ->quantity->toBe(10);

    expect($inventoryStock->fresh())
        ->qty_available->toBe(10.0);

    // 7. INVOICE MATCHING & POSTING TO AP - Create Purchase Invoice
    $purchaseInvoice = Invoice::factory()->create([
        'invoice_number' => 'PINV-20251031-0001',
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $purchaseOrder->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100000,
        'tax' => 11000,
        'total' => 111000,
        'status' => 'paid',
    ]);

    expect($purchaseInvoice)
        ->from_model_id->toBe($purchaseOrder->id)
        ->status->toBe('paid');

    // Update PO status
    $purchaseOrder->update(['status' => 'invoiced']);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('invoiced');

    // 8. PAYMENT - Create Vendor Payment
    $vendorPayment = VendorPayment::factory()->create([
        'selected_invoices' => [$purchaseInvoice->id],
        'supplier_id' => $supplier->id,
        'payment_date' => now(),
        'total_payment' => 111000,
        'payment_method' => 'bank_transfer',
        'status' => 'Paid',
    ]);

    expect($vendorPayment)
        ->supplier_id->toBe($supplier->id)
        ->total_payment->toBe(111000);

    // Update PO status to paid
    $purchaseOrder->update(['status' => 'paid']);
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('paid');

    // 9. Final status tracking verification
    expect($purchaseOrder->status)->toBe('paid');
    expect(PurchaseOrder::count())->toBe(1);
    expect(PurchaseReceipt::count())->toBe(1);
    expect(QualityControl::count())->toBe(1);
    expect(StockMovement::count())->toBe(1);
    expect(Invoice::count())->toBe(1);
    expect(VendorPayment::count())->toBe(1);
});

it('creates PO with single product', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 5.00,
        'unit_price' => 25000,
    ]);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(1);
    expect($purchaseOrder->purchaseOrderItem->first()->product_id)->toBe($product->id);
    expect($purchaseOrder->purchaseOrderItem->first()->quantity)->toBe(5);
});

it('creates PO with multiple products', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $product3 = Product::factory()->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    // Create multiple items
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product1->id,
        'quantity' => 10.00,
        'unit_price' => 15000,
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product2->id,
        'quantity' => 20.00,
        'unit_price' => 20000,
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product3->id,
        'quantity' => 15.00,
        'unit_price' => 30000,
    ]);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(3);
    expect($purchaseOrder->purchaseOrderItem->pluck('product_id'))->toContain($product1->id, $product2->id, $product3->id);

    // Calculate total
    $total = (10.00 * 15000) + (20.00 * 20000) + (15.00 * 30000);
    expect($purchaseOrder->purchaseOrderItem->sum(function ($item) {
        return $item->quantity * $item->unit_price;
    }))->toBe($total);
});

it('creates PO with multi-currency', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $usdCurrency = Currency::factory()->create(['code' => 'USD', 'to_rupiah' => 15000]);
    $eurCurrency = Currency::factory()->create(['code' => 'EUR', 'to_rupiah' => 16000]);

    // Test USD currency
    $purchaseOrderUSD = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrderUSD->id,
        'product_id' => $product->id,
        'quantity' => 5.00,
        'unit_price' => 10, // USD
        'currency_id' => $usdCurrency->id,
    ]);

    expect($usdCurrency->code)->toBe('USD');
    expect($usdCurrency->to_rupiah)->toBe(15000);

    // Test EUR currency
    $purchaseOrderEUR = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrderEUR->id,
        'product_id' => $product->id,
        'quantity' => 3.00,
        'unit_price' => 25, // EUR
        'currency_id' => $eurCurrency->id,
    ]);

    expect($eurCurrency->code)->toBe('EUR');
    expect($eurCurrency->to_rupiah)->toBe(16000);
});

it('creates PO with additional costs', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $currency = Currency::factory()->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10.00,
        'unit_price' => 10000,
    ]);

    // Create additional costs using PurchaseOrderBiaya
    \App\Models\PurchaseOrderBiaya::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'currency_id' => $currency->id,
        'nama_biaya' => 'Shipping Cost',
        'total' => 50000,
    ]);

    \App\Models\PurchaseOrderBiaya::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'currency_id' => $currency->id,
        'nama_biaya' => 'Insurance Cost',
        'total' => 25000,
    ]);

    expect($purchaseOrder->purchaseOrderBiaya)->toHaveCount(2);
    expect($purchaseOrder->purchaseOrderBiaya->sum('total'))->toBe(75000.0);
});

it('tests PO approval workflow', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    // Create PO in draft status
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    expect($purchaseOrder->status)->toBe('draft');

    // Approve PO directly (PO-level request approval removed in new flow)
    $purchaseOrder->update(['is_asset' => false, 'status' => 'approved']);
    expect($purchaseOrder->fresh()->status)->toBe('approved');
});

it('tests PO status transitions', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $currency = Currency::factory()->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10.00,
        'unit_price' => 10000,
    ]);

    // Test all valid status transitions
    $statuses = [
        'draft',
        'approved',
        'partially_received',
        'completed',
        'invoiced',
        'paid',
        'closed'
    ];

    foreach ($statuses as $status) {
        $purchaseOrder->update(['status' => $status]);
        expect($purchaseOrder->fresh()->status)->toBe($status);
    }
});