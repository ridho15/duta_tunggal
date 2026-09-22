<?php

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Models\CustomerReceipt;
use App\Models\Warehouse;
use App\Models\Cabang;
use App\Services\BalanceSheetService;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('balance sheet remains balanced after purchase transaction with payment', function () {
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

    $cabang = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

    // Create currencies
    $idr = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $usd = Currency::factory()->create(['code' => 'USD', 'to_rupiah' => 16000]);

    // Create supplier and product
    $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
    $product = Product::factory()->create();

    // Get or create bank COA
    $bankCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1112.01'],
        ['name' => 'Bank Account', 'type' => 'Asset', 'is_active' => true]
    );

    // Check initial balance sheet
    $initialBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($initialBS['is_balanced'])->toBeTrue();
    $initialDifference = (float) ($initialBS['difference'] ?? 0);
    expect($initialDifference)->toBe(0.0);

    // Create Purchase Order with USD
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => 100,
    ]);
    $po->update(['cabang_id' => $cabang->id]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $po->id,
        'currency_id' => $usd->id,
        'nominal' => 16000,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'currency_id' => $usd->id,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
    ]);

    // Create Purchase Receipt
    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'RC-TEST-' . time(),
        'purchase_order_id' => $po->id,
        'receipt_date' => now(),
        'received_by' => 1,
        'currency_id' => $usd->id,
        'status' => 'completed',
        'cabang_id' => $cabang->id,
    ]);

    PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'qty_received' => 2,
        'qty_accepted' => 2,
        'warehouse_id' => $warehouse->id,
        'status' => 'completed',
    ]);

    $receipt->refresh();
    $receipt->load('purchaseReceiptItem.purchaseOrderItem', 'purchaseOrder');

    // Create invoice from receipt (this posts journal entries)
    app(PurchaseReceiptService::class)->createAutomaticInvoiceFromReceipt($receipt);

    // Check balance sheet after invoice creation
    $afterInvoiceBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($afterInvoiceBS['is_balanced'])->toBeTrue();
    $afterInvoiceDifference = (float) ($afterInvoiceBS['difference'] ?? 0);
    expect($afterInvoiceDifference)->toBe(0.0);

    // Verify all journal entries for purchase are balanced
    $purchaseJournals = JournalEntry::where('source_type', 'App\Models\Invoice')
        ->orWhere('source_type', 'App\Models\PurchaseReceipt')
        ->orWhere('source_type', 'App\Models\PurchaseReceiptItem')
        ->get();

    $totalDebit = (float) $purchaseJournals->sum('debit');
    $totalCredit = (float) $purchaseJournals->sum('credit');

    // Allow for small rounding difference (0.02)
    expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.02);

    // Create vendor payment
    $invoice = Invoice::where('from_model_type', 'App\Models\PurchaseReceipt')
        ->where('from_model_id', $receipt->id)
        ->first();

    if ($invoice) {
        $payment = VendorPayment::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'payment_date' => now(),
            'amount_paid' => (float) $invoice->total,
            'payment_method' => 'Bank Transfer',
            'coa_id' => $bankCoa->id,
            'status' => 'paid',
            'cabang_id' => $cabang->id,
        ]);

        // Check balance sheet after payment
        $afterPaymentBS = app(BalanceSheetService::class)->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => $cabang->id,
        ]);

        expect($afterPaymentBS['is_balanced'])->toBeTrue();
        $afterPaymentDifference = (float) ($afterPaymentBS['difference'] ?? 0);
        expect($afterPaymentDifference)->toBe(0.0);

        // Verify all purchase + payment journals are balanced
        $allJournals = JournalEntry::where('source_type', 'App\Models\Invoice')
            ->orWhere('source_type', 'App\Models\PurchaseReceipt')
            ->orWhere('source_type', 'App\Models\PurchaseReceiptItem')
            ->orWhere('source_type', 'App\Models\VendorPayment')
            ->get();

        $totalDebit = (float) $allJournals->sum('debit');
        $totalCredit = (float) $allJournals->sum('credit');

        expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.02);
    }
});

test('balance sheet remains balanced after sales transaction with payment', function () {
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

    $cabang = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

    // Create currencies
    $idr = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

    // Create customer and product
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $product = Product::factory()->create(['sell_price' => 75000]);

    // Get or create bank COA
    $bankCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1112.01'],
        ['name' => 'Bank Account', 'type' => 'Asset', 'is_active' => true]
    );

    // Check initial balance sheet
    $initialBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($initialBS['is_balanced'])->toBeTrue();

    // Create Sale Order
    $so = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'total_amount' => 150000,
        'status' => 'approved',
    ]);
    $so->update(['cabang_id' => $cabang->id]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 75000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'warehouse_id' => $warehouse->id,
    ]);

    $so->update(['status' => 'completed']);

    // Create Delivery Order
    $do = DeliveryOrder::factory()->create([
        'status' => 'completed',
    ]);
    $do->update([
        'sale_order_id' => $so->id,
        'cabang_id' => $cabang->id,
    ]);

    DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'product_id' => $product->id,
        'qty_ordered' => 2,
        'qty_delivered' => 2,
        'warehouse_id' => $warehouse->id,
    ]);

    // Check balance sheet after delivery
    $afterDeliveryBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($afterDeliveryBS['is_balanced'])->toBeTrue();
    $afterDeliveryDifference = (float) ($afterDeliveryBS['difference'] ?? 0);
    expect($afterDeliveryDifference)->toBe(0.0);

    // Verify all journal entries for sales are balanced
    $salesJournals = JournalEntry::where('source_type', 'App\Models\Invoice')
        ->orWhere('source_type', 'App\Models\DeliveryOrder')
        ->orWhere('source_type', 'App\Models\DeliveryOrderItem')
        ->orWhere('source_type', 'App\Models\SaleOrder')
        ->get();

    $totalDebit = (float) $salesJournals->sum('debit');
    $totalCredit = (float) $salesJournals->sum('credit');

    expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.02);

    // Create customer payment
    $invoice = Invoice::where('from_model_type', 'App\Models\SaleOrder')
        ->where('from_model_id', $so->id)
        ->first();

    if ($invoice) {
        $receipt = CustomerReceipt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_date' => now(),
            'total_payment' => (float) $invoice->total,
            'payment_method' => 'Cash',
            'coa_id' => $bankCoa->id,
            'status' => 'paid',
            'selected_invoices' => [$invoice->id],
            'cabang_id' => $cabang->id,
        ]);

        // Check balance sheet after payment
        $afterPaymentBS = app(BalanceSheetService::class)->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => $cabang->id,
        ]);

        expect($afterPaymentBS['is_balanced'])->toBeTrue();
        $afterPaymentDifference = (float) ($afterPaymentBS['difference'] ?? 0);
        expect($afterPaymentDifference)->toBe(0.0);

        // Verify all sales + payment journals are balanced
        $allJournals = JournalEntry::where('source_type', 'App\Models\Invoice')
            ->orWhere('source_type', 'App\Models\DeliveryOrder')
            ->orWhere('source_type', 'App\Models\DeliveryOrderItem')
            ->orWhere('source_type', 'App\Models\SaleOrder')
            ->orWhere('source_type', 'App\Models\CustomerReceipt')
            ->get();

        $totalDebit = (float) $allJournals->sum('debit');
        $totalCredit = (float) $allJournals->sum('credit');

        expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.02);
    }
});

test('balance sheet remains balanced after multiple purchase and sales transactions', function () {
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

    $cabang = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

    // Create currencies
    $idr = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $usd = Currency::factory()->create(['code' => 'USD', 'to_rupiah' => 16000]);

    // Create suppliers, customers, products
    $supplier1 = Supplier::factory()->create(['cabang_id' => $cabang->id]);
    $supplier2 = Supplier::factory()->create(['cabang_id' => $cabang->id]);
    $customer1 = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $customer2 = Customer::factory()->create(['cabang_id' => $cabang->id]);

    $product1 = Product::factory()->create(['sell_price' => 50000]);
    $product2 = Product::factory()->create(['sell_price' => 75000]);

    $bankCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1112.01'],
        ['name' => 'Bank Account', 'type' => 'Asset', 'is_active' => true]
    );

    // Initial check
    $initialBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($initialBS['is_balanced'])->toBeTrue();

    // Execute multiple purchase transactions
    for ($i = 1; $i <= 2; $i++) {
        $supplier = $i === 1 ? $supplier1 : $supplier2;
        $product = $i === 1 ? $product1 : $product2;

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => 100 * $i,
        ]);
        $po->update(['cabang_id' => $cabang->id]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $i,
            'unit_price' => 100,
            'currency_id' => $usd->id,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'RC-' . uniqid(),
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => 1,
            'currency_id' => $usd->id,
            'status' => 'completed',
            'cabang_id' => $cabang->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'qty_received' => $i,
            'qty_accepted' => $i,
            'warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);

        $receipt->refresh();
        $receipt->load('purchaseReceiptItem.purchaseOrderItem', 'purchaseOrder');

        app(PurchaseReceiptService::class)->createAutomaticInvoiceFromReceipt($receipt);

        // Verify balance sheet after each purchase
        $bs = app(BalanceSheetService::class)->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => $cabang->id,
        ]);

        expect($bs['is_balanced'])->toBeTrue();
        expect((float) ($bs['difference'] ?? 0))->toBe(0.0);
    }

    // Execute multiple sales transactions
    for ($i = 1; $i <= 2; $i++) {
        $customer = $i === 1 ? $customer1 : $customer2;
        $product = $i === 1 ? $product1 : $product2;

        $so = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100000 * $i,
            'status' => 'approved',
        ]);
        $so->update(['cabang_id' => $cabang->id]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => $i,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
            'warehouse_id' => $warehouse->id,
        ]);

        $so->update(['status' => 'completed']);

        $do = DeliveryOrder::factory()->create([
            'status' => 'completed',
        ]);
        $do->update([
            'sale_order_id' => $so->id,
            'cabang_id' => $cabang->id,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $product->id,
            'qty_ordered' => $i,
            'qty_delivered' => $i,
            'warehouse_id' => $warehouse->id,
        ]);

        // Verify balance sheet after each sale
        $bs = app(BalanceSheetService::class)->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => $cabang->id,
        ]);

        expect($bs['is_balanced'])->toBeTrue();
        expect((float) ($bs['difference'] ?? 0))->toBe(0.0);
    }

    // Final verification: all journal entries should be balanced
    $allJournals = JournalEntry::get();

    $totalDebit = (float) $allJournals->sum('debit');
    $totalCredit = (float) $allJournals->sum('credit');

    expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.02);

    // Final balance sheet check
    $finalBS = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $cabang->id,
    ]);

    expect($finalBS['is_balanced'])->toBeTrue();
    expect((float) ($finalBS['difference'] ?? 0))->toBe(0.0);
});
