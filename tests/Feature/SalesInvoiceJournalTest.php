<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates sales invoice journal entries automatically when sale order is approved', function () {
    // Create cabang
    $cabang = Cabang::factory()->create([
        'kode' => 'MAIN',
        'nama' => 'Main Branch',
    ]);

    // Create COAs
    $arCoa = ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang']);
    $revenueCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran']);
    $cogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP Barang Dagangan']);
    $goodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);

    // Create product with COAs
    $product = Product::factory()->create([
        'cost_price' => 8500000.00,
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
        'so_number' => 'SO-TEST-001',
        'status' => 'draft',
    ]);

    // Create sale order item
    $soItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 12500000.00,
        'discount' => 0,
        'tax' => 11,
    ]);

    // Update sale order totals
    $saleOrder->update([
        'subtotal' => 62500000.00,
        'total' => 62500000.00,
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
    expect($invoice->total)->toBe('69375000.00'); // 62.5M + 11% tax

    // Assert invoice items created
    $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();
    expect($invoiceItems->count())->toBe(1);
    $item = $invoiceItems->first();
    expect($item->subtotal)->toBe('62500000.00');
    expect($item->tax_amount)->toBe('6875000.00');
    expect($item->total)->toBe('69375000.00');

    // Assert journal entries created
    $journalEntries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();
    expect($journalEntries->count())->toBe(5); // AR debit, Revenue credit, PPn credit, COGS debit, Barang Terkirim credit

    // Check balances
    $totalDebit = $journalEntries->sum('debit');
    $totalCredit = $journalEntries->sum('credit');
    expect($totalDebit)->toBe(111875000.0); // AR 69.375M + COGS 42.5M
    expect($totalCredit)->toBe(111875000.0); // Revenue 62.5M + PPn 6.875M + Barang Terkirim 42.5M

    // Check specific entries
    $arEntry = $journalEntries->where('coa_id', $arCoa->id)->first();
    expect($arEntry->debit)->toBe('69375000.00');
    expect($arEntry->description)->toContain('Accounts Receivable');

    $revenueEntry = $journalEntries->where('coa_id', $revenueCoa->id)->first();
    expect($revenueEntry->credit)->toBe('62500000.00');
    expect($revenueEntry->description)->toContain('Revenue');

    $ppnEntry = $journalEntries->where('coa_id', $ppnKeluaranCoa->id)->first();
    expect($ppnEntry->credit)->toBe('6875000.00');
    expect($ppnEntry->description)->toContain('PPn Keluaran');

    $cogsEntry = $journalEntries->where('coa_id', $cogsCoa->id)->first();
    expect($cogsEntry->debit)->toBe('42500000.00'); // 5 * 8.5M
    expect($cogsEntry->description)->toContain('Cost of Goods Sold');

    $deliveryEntry = $journalEntries->where('coa_id', $goodsDeliveryCoa->id)->first();
    expect($deliveryEntry->credit)->toBe('42500000.00');
    expect($deliveryEntry->description)->toContain('Release Barang Terkirim');
});

it('uses default COAs when product has no specific COAs', function () {
    // Create cabang
    $cabang = Cabang::factory()->create([
        'kode' => 'MAIN',
        'nama' => 'Main Branch',
    ]);

    // Create COAs
    $arCoa = ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang']);
    $defaultRevenueCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran']);
    $defaultCogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP Barang Dagangan']);
    $defaultGoodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim']);

    // Create product WITHOUT specific COAs (should use defaults)
    $product = Product::factory()->create([
        'cost_price' => 8500000.00,
        // No sales_coa_id, cogs_coa_id, goods_delivery_coa_id specified
    ]);

    // Create customer
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);

    // Create sale order
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'so_number' => 'SO-TEST-002',
        'status' => 'draft',
    ]);

    // Create sale order item
    $soItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 12500000.00,
        'discount' => 0,
        'tax' => 11,
    ]);

    // Update sale order totals
    $saleOrder->update([
        'subtotal' => 62500000.00,
        'total' => 62500000.00,
    ]);

    // Approve sale order to trigger invoice creation
    $saleOrder->update(['status' => 'completed']);

    // Assert invoice created
    $invoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->first();
    expect($invoice)->not->toBeNull();

    // Assert journal entries created using default COAs
    $journalEntries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();
    expect($journalEntries->count())->toBe(5);

    // Check that default COAs are used
    $revenueEntry = $journalEntries->where('coa_id', $defaultRevenueCoa->id)->first();
    expect($revenueEntry)->not->toBeNull();
    expect($revenueEntry->credit)->toBe('62500000.00');

    $cogsEntry = $journalEntries->where('coa_id', $defaultCogsCoa->id)->first();
    expect($cogsEntry)->not->toBeNull();
    expect($cogsEntry->debit)->toBe('42500000.00');

    $deliveryEntry = $journalEntries->where('coa_id', $defaultGoodsDeliveryCoa->id)->first();
    expect($deliveryEntry)->not->toBeNull();
    expect($deliveryEntry->credit)->toBe('42500000.00');
});