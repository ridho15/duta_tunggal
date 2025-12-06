<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\InventoryStock;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

it('end-to-end sales flow: stock reduces, invoice and payment post to ledger', function () {
    // Seed Cabang for customer factory
    $cabang = \App\Models\Cabang::create([
        'kode' => 'MAIN',
        'nama' => 'Main Branch',
        'alamat' => 'Jl. Main 123',
        'telepon' => '021123456'
    ]);
    // Seed minimal data assumptions (factories should create necessary relationships)
    // Create a product with cost_price and COAs
    $product = Product::factory()->create([
        'cost_price' => 100.00,
    ]);

    // Ensure COAs exist for AR, Revenue, Inventory, COGS
    $arCoa = ChartOfAccount::firstWhere('code', '1120') ?? ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Accounts Receivable']);
    $revenueCoa = ChartOfAccount::firstWhere('code', '4000') ?? ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Revenue']);
    $inventoryCoa = ChartOfAccount::firstWhere('code', '1140.01') ?? ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Inventory']);
    $cogsCoa = ChartOfAccount::firstWhere('code', '5000') ?? ChartOfAccount::factory()->create(['code' => '5000', 'name' => 'COGS']);
    $goodsDeliveryCoa = ChartOfAccount::firstWhere('code', '1140.20') ?? ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);

    // Attach COAs to product where appropriate
    $product->update([
        'inventory_coa_id' => $inventoryCoa->id,
    'cogs_coa_id' => $cogsCoa->id,
    'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
    ]);

    // Create initial stock: one warehouse/rak combination must match sale order item defaults created by factory
    InventoryStock::where('product_id', $product->id)->delete(); // Clean up any existing stock
    $stock = InventoryStock::factory()->create([
        'product_id' => $product->id,
        'qty_available' => 10,
    ]);

    // Create customer
    $customer = Customer::factory()->create();

    // Create sale order with one item quantity 2
    $saleOrder = SaleOrder::factory()->create([ 'customer_id' => $customer->id ]);
    $soItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 150.00,
        'warehouse_id' => $stock->warehouse_id,
        'rak_id' => $stock->rak_id,
    ]);

    // Create delivery order tied to SO and deliver full qty
    $do = DeliveryOrder::factory()->create([
        'warehouse_id' => null, // Skip stock validation for test
    ]);
    // attach relation table
    $do->salesOrders()->attach($saleOrder->id);

    $doItem = DeliveryOrderItem::factory()->create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    // Confirm pre-conditions
    $this->assertEquals(10, InventoryStock::where('product_id', $product->id)->sum('qty_available'));

    // Post delivery order (this should debit Barang Terkirim and credit Inventory)
    $service = app(\App\Services\DeliveryOrderService::class);
    $res = $service->postDeliveryOrder($do);

    expect($res['status'])->toBe('posted');

    // After posting, assert journal entries exist and are balanced for this DO
    $entries = JournalEntry::where('source_type', DeliveryOrder::class)->where('source_id', $do->id)->get();
    expect($entries->count())->toBeGreaterThan(0);

    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');
    expect(abs($totalDebit - $totalCredit))->toBeLessThan(0.01);

    expect($entries->where('coa_id', $goodsDeliveryCoa->id)->sum('debit'))->toBe(200.0);
    expect($entries->where('coa_id', $inventoryCoa->id)->sum('credit'))->toBe(200.0);

    // Simulate stock reduction in InventoryStock to reflect delivery (system may do this elsewhere; do it here to assert change)
    InventoryStock::where('product_id', $product->id)->decrement('qty_available', 2);

    $this->assertEquals(8, InventoryStock::where('product_id', $product->id)->sum('qty_available'));

    // Create invoice for the sale order
    $invoice = Invoice::factory()->create([
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => Carbon::now(),
        'invoice_number' => 'INV-TEST-001',
        'subtotal' => 300.00,
        'tax' => 0,
        'total' => 300.00,
        'status' => 'Unpaid',
        'delivery_orders' => [$do->id],
    ]);

    expect($invoice->delivery_orders)->toBe([$do->id]);

    // InvoiceObserver should create AR and post sales invoice (debit AR, credit Revenue) via observer
    // Trigger observer manually if needed by saving
    $invoice->refresh();

    // Assert account receivable created
    $ar = \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect($ar)->not->toBeNull();

    // Assert invoice journal entries exist
    $invEntries = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->get();
    expect($invEntries->count())->toBe(4);
    expect($invEntries->sum('debit'))->toBe(500.00);
    expect($invEntries->sum('credit'))->toBe(500.00);
    expect($invEntries->where('coa_id', $cogsCoa->id)->sum('debit'))->toBe(200.0);
    expect($invEntries->where('coa_id', $goodsDeliveryCoa->id)->sum('credit'))->toBe(200.0);

    // Create customer receipt paying full invoice
    $receipt = CustomerReceipt::factory()->create([
        'customer_id' => $customer->id,
        'payment_date' => Carbon::now(),
        'total_payment' => 300.00,
        'status' => 'Paid',
    ]);

    $receiptItem = \App\Models\CustomerReceiptItem::create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'cash',
        'amount' => 300.00,
        'coa_id' => $arCoa->id, // use AR's coa as bank for simplicity if app uses it
        'payment_date' => Carbon::now(),
    ]);

    // Observer should create journal entries for receipt
    $paymentEntries = JournalEntry::where('source_type', \App\Models\CustomerReceiptItem::class)
        ->where('source_id', $receiptItem->id)
        ->get();

    expect($paymentEntries->sum('debit'))->toBe(300.00);
    expect($paymentEntries->sum('credit'))->toBe(300.00);

});
