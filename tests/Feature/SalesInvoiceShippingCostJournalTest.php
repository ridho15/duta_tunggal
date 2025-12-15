<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliverySalesOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates sales invoice journal entries with shipping costs when delivery order has additional costs', function () {
    // Create cabang
    $cabang = Cabang::factory()->create([
        'kode' => 'MAIN',
        'nama' => 'Main Branch',
    ]);

    // Create COAs
    $arCoa = ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang']);
    $revenueCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan Barang Dagangan']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran']);
    $biayaPengirimanCoa = ChartOfAccount::factory()->create(['code' => '6100.02', 'name' => 'Biaya Pengiriman / Pengangkutan']);
    $cogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'Harga Pokok Pembelian Barang Dagangan']);
    $goodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);

    // Create product with COAs
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'cost_price' => '1000000.00', // Rp 1.000.000
        'cogs_coa_id' => $cogsCoa->id,
        'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        'sales_coa_id' => $revenueCoa->id,
    ]);

    // Create customer
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);

    // Create sale order
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'so_number' => 'SO-TEST-SHIPPING-001',
        'status' => 'draft',
    ]);

    // Create sale order item
    $quantity = 1;
    $unitPrice = '1200000.00'; // Rp 1.200.000
    $tax = 11;

    $soItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount' => 0,
        'tax' => $tax,
    ]);

    // Update sale order totals
    $subtotal = $quantity * 1200000; // 1.200.000
    $saleOrder->update([
        'subtotal' => $subtotal,
        'total' => $subtotal,
    ]);

    // Create delivery order with shipping costs
    $shippingCost = '50000.00'; // Rp 50.000
    $deliveryOrder = DeliveryOrder::factory()->create([
        'do_number' => 'DO-TEST-SHIPPING-001',
        'additional_cost' => $shippingCost,
        'additional_cost_description' => 'Biaya pengiriman ke customer',
        'status' => 'sent',
        'cabang_id' => $cabang->id,
    ]);

    // Link delivery order to sale order via pivot table
    DeliverySalesOrder::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sales_order_id' => $saleOrder->id,
    ]);

    // Approve sale order to trigger invoice creation
    $saleOrder->update(['status' => 'completed']);

    // Assert invoice created
    $invoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->first();
    expect($invoice)->not->toBeNull();
    $invoice->refresh();
    $invoice->load('invoiceItem');

    // Calculate expected values
    $expectedSubtotal = '1200000.00'; // 1.200.000
    $expectedTaxAmount = 132000; // 1.200.000 * 11% (integer for invoice)
    $expectedTaxAmountJournal = '132000.00'; // 1.200.000 * 11% (string for journal)
    $expectedShippingCost = 50000; // 50.000
    $expectedTotal = '1382000.00'; // 1.382.000

    expect($invoice->subtotal)->toBe($expectedSubtotal);
    expect($invoice->tax)->toBe($expectedTaxAmount);
    expect($invoice->getOtherFeeTotalAttribute())->toBe($expectedShippingCost);
    expect($invoice->total)->toBe($expectedTotal);

    // Assert invoice items created
    $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
    expect($invoiceItems->count())->toBe(1);
    $item = $invoiceItems->first();
    expect($item->subtotal)->toBe($expectedSubtotal);
    expect($item->tax_amount)->toBe('132000.00');
    expect($item->total)->toBe('1332000.00');

    // Assert journal entries created (6 entries: AR, Revenue, PPn, Shipping, COGS, Barang Terkirim)
    $journalEntries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->orderBy('id')
        ->get();
    expect($journalEntries->count())->toBe(6);

    // Check balances - should be balanced
    $totalDebit = $journalEntries->sum('debit');
    $totalCredit = $journalEntries->sum('credit');
    expect($totalDebit)->toBe($totalCredit);

    // Expected totals:
    // Debit: AR (1.382.000) + COGS (1.000.000) = 2.382.000
    // Credit: Revenue (1.200.000) + PPn (132.000) + Shipping (50.000) + Barang Terkirim (1.000.000) = 2.382.000
    $expectedTotalAmount = 2382000.00; // 1.382.000 + 1.000.000 = 2.382.000
    expect($totalDebit)->toBe($expectedTotalAmount);
    expect($totalCredit)->toBe($expectedTotalAmount);

    // Check specific entries
    // 1. Accounts Receivable (Debit)
    $arEntry = $journalEntries->where('coa_id', $arCoa->id)->first();
    expect($arEntry)->not->toBeNull();
    expect($arEntry->debit)->toBe($expectedTotal);
    expect($arEntry->credit)->toBe('0.00');
    expect($arEntry->description)->toContain('Accounts Receivable');

    // 2. Revenue (Credit)
    $revenueEntry = $journalEntries->where('coa_id', $revenueCoa->id)->first();
    expect($revenueEntry)->not->toBeNull();
    expect($revenueEntry->debit)->toBe('0.00');
    expect($revenueEntry->credit)->toBe($expectedSubtotal);
    expect($revenueEntry->description)->toContain('Revenue');

    // 3. PPn Keluaran (Credit)
    $ppnEntry = $journalEntries->where('coa_id', $ppnKeluaranCoa->id)->first();
    expect($ppnEntry)->not->toBeNull();
    expect($ppnEntry->debit)->toBe('0.00');
    expect($ppnEntry->credit)->toBe($expectedTaxAmountJournal);
    expect($ppnEntry->description)->toContain('PPn Keluaran');

    // 4. Biaya Pengiriman (Credit) - THIS IS THE KEY TEST
    $shippingEntry = $journalEntries->where('coa_id', $biayaPengirimanCoa->id)->first();
    expect($shippingEntry)->not->toBeNull();
    expect($shippingEntry->debit)->toBe('0.00');
    expect($shippingEntry->credit)->toBe('50000.00');
    expect($shippingEntry->description)->toContain('Biaya Pengiriman');

    // 5. COGS (Debit)
    $cogsEntry = $journalEntries->where('coa_id', $cogsCoa->id)->first();
    expect($cogsEntry)->not->toBeNull();
    expect($cogsEntry->debit)->toBe('1000000.00');
    expect($cogsEntry->credit)->toBe('0.00');
    expect($cogsEntry->description)->toContain('Cost of Goods Sold');

    // 6. Barang Terkirim (Credit)
    $deliveryEntry = $journalEntries->where('coa_id', $goodsDeliveryCoa->id)->first();
    expect($deliveryEntry)->not->toBeNull();
    expect($deliveryEntry->debit)->toBe('0.00');
    expect($deliveryEntry->credit)->toBe('1000000.00');
    expect($deliveryEntry->description)->toContain('Release Barang Terkirim');
});

it('does not create shipping cost journal entry when delivery order has no additional costs', function () {
    // Create cabang
    $cabang = Cabang::factory()->create([
        'kode' => 'MAIN',
        'nama' => 'Main Branch',
    ]);

    // Create COAs
    $arCoa = ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang']);
    $revenueCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan Barang Dagangan']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran']);
    $biayaPengirimanCoa = ChartOfAccount::factory()->create(['code' => '6100.02', 'name' => 'Biaya Pengiriman / Pengangkutan']);
    $cogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'Harga Pokok Pembelian Barang Dagangan']);
    $goodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);

    // Create product with COAs
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'cost_price' => '1000000.00',
        'cogs_coa_id' => $cogsCoa->id,
        'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        'sales_coa_id' => $revenueCoa->id,
    ]);

    // Create customer
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);

    // Create sale order
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'so_number' => 'SO-TEST-NO-SHIPPING-001',
        'status' => 'draft',
    ]);

    // Create sale order item
    $quantity = 1;
    $unitPrice = 1200000.00;
    $tax = 11;

    $soItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'discount' => 0,
        'tax' => $tax,
    ]);

    // Update sale order totals
    $subtotal = $quantity * $unitPrice;
    $saleOrder->update([
        'subtotal' => $subtotal,
        'total' => $subtotal,
    ]);

    // Create delivery order WITHOUT shipping costs
    $deliveryOrder = DeliveryOrder::factory()->create([
        'do_number' => 'DO-TEST-NO-SHIPPING-001',
        'additional_cost' => 0, // No shipping cost
        'status' => 'sent',
        'cabang_id' => $cabang->id,
    ]);

    // Link delivery order to sale order via pivot table
    DeliverySalesOrder::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sales_order_id' => $saleOrder->id,
    ]);

    // Approve sale order to trigger invoice creation
    $saleOrder->update(['status' => 'completed']);

    // Assert invoice created
    $invoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->first();
    expect($invoice)->not->toBeNull();
    $invoice->refresh();

    // Assert no shipping cost in invoice
    expect($invoice->getOtherFeeTotalAttribute())->toBe(0);

    // Assert journal entries created (5 entries: AR, Revenue, PPn, COGS, Barang Terkirim - NO SHIPPING)
    $journalEntries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();
    expect($journalEntries->count())->toBe(5);

    // Assert NO shipping cost journal entry
    $shippingEntry = $journalEntries->where('coa_id', $biayaPengirimanCoa->id)->first();
    expect($shippingEntry)->toBeNull();

    // Check balances still balanced
    $totalDebit = $journalEntries->sum('debit');
    $totalCredit = $journalEntries->sum('credit');
    expect($totalDebit)->toBe($totalCredit);
});