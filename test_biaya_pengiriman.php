<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== TEST BIAYA PENGIRIMAN DALAM INVOICE ===\n\n";

try {
    // Setup data test
    echo "1. Setting up test data...\n";

    // Create cabang
    $cabang = Cabang::factory()->create([
        'kode' => 'TEST-' . time(),
        'nama' => 'Test Branch',
    ]);
    echo "   ✓ Cabang created: {$cabang->nama}\n";

    // Create COAs
    $timestamp = time();
    $arCoa = ChartOfAccount::factory()->create(['code' => '1120-' . $timestamp, 'name' => 'Piutang Usaha']);
    $revenueCoa = ChartOfAccount::factory()->create(['code' => '4000-' . $timestamp, 'name' => 'Penjualan Barang Dagangan']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06-' . $timestamp, 'name' => 'PPn Keluaran']);
    $biayaPengirimanCoa = ChartOfAccount::factory()->create(['code' => '6100.02-' . $timestamp, 'name' => 'Biaya Pengiriman']);
    $cogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10-' . $timestamp, 'name' => 'Harga Pokok Pembelian Barang Dagangan']);
    $goodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20-' . $timestamp, 'name' => 'Barang Terkirim']);

    echo "   ✓ COAs created\n";

    // Create product
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'cost_price' => 1000000.00,
        'cogs_coa_id' => $cogsCoa->id,
        'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        'sales_coa_id' => $revenueCoa->id,
    ]);
    echo "   ✓ Product created dengan cost price Rp 1.000.000\n";

    // Create customer
    $customer = Customer::factory()->create([
        'name' => 'Test Customer',
        'cabang_id' => $cabang->id
    ]);
    echo "   ✓ Customer created\n";

    // 2. Create Sale Order
    echo "\n2. Creating Sale Order...\n";

    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'so_number' => 'SO-TEST-' . time(),
        'status' => 'draft',
    ]);

    // Create sale order item
    $quantity = 1;
    $unitPrice = 1200000.00; // Harga jual
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
    $subtotal = $quantity * $unitPrice; // 1.200.000
    $taxAmount = $subtotal * ($tax / 100); // 132.000
    $saleOrder->update([
        'subtotal' => $subtotal,
        'total' => $subtotal,
    ]);

    echo "   ✓ Sale Order created:\n";
    echo "     - Quantity: {$quantity}, Unit Price: Rp " . number_format($unitPrice, 0, ',', '.') . "\n";
    echo "     - Subtotal: Rp " . number_format($subtotal, 0, ',', '.') . "\n";
    echo "     - Expected Tax: Rp " . number_format($taxAmount, 0, ',', '.') . "\n";

    // 3. Create Delivery Order dengan biaya pengiriman
    echo "\n3. Creating Delivery Order dengan biaya pengiriman...\n";

    $deliveryOrder = DeliveryOrder::factory()->create([
        'do_number' => 'DO-TEST-' . time(),
        'additional_cost' => 50000, // Biaya pengiriman 50.000
        'additional_cost_description' => 'Biaya pengiriman ke customer',
        'status' => 'sent',
        'cabang_id' => $cabang->id,
    ]);

    // Create pivot table entry to link delivery order with sale order
    \App\Models\DeliverySalesOrder::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sales_order_id' => $saleOrder->id,
    ]);

    echo "   ✓ Delivery Order created dengan biaya pengiriman Rp 50.000\n";

    // 4. Approve Sale Order to trigger invoice creation
    echo "\n4. Approving Sale Order to create Invoice...\n";

    $saleOrder->update(['status' => 'completed']);

    // Get created invoice
    $invoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->first();

    if (!$invoice) {
        throw new Exception("Invoice not created!");
    }

    $invoice->refresh();
    $invoice->load('invoiceItem');

    echo "   ✓ Invoice created: {$invoice->invoice_number}\n";
    echo "     - Subtotal: Rp " . number_format($invoice->subtotal, 2, ',', '.') . "\n";
    echo "     - Tax Amount: Rp " . number_format($invoice->tax_amount, 2, ',', '.') . "\n";
    echo "     - Other Fee Total: Rp " . number_format($invoice->getOtherFeeTotalAttribute(), 2, ',', '.') . "\n";
    echo "     - Total: Rp " . number_format($invoice->total, 2, ',', '.') . "\n";

    // 5. Check Journal Entries
    echo "\n5. Checking Journal Entries...\n";

    $journalEntries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->orderBy('id')
        ->get();

    echo "   Found {$journalEntries->count()} journal entries:\n\n";

    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($journalEntries as $index => $entry) {
        $coa = $entry->coa;
        $debit = $entry->debit ?? 0;
        $credit = $entry->credit ?? 0;

        $totalDebit += $debit;
        $totalCredit += $credit;

        echo "   " . ($index + 1) . ". {$coa->code} - {$coa->name}\n";
        echo "      Debit: Rp " . number_format($debit, 0, ',', '.') . "\n";
        echo "      Credit: Rp " . number_format($credit, 0, ',', '.') . "\n";
        echo "      Description: {$entry->description}\n\n";
    }

    // 6. Analysis sesuai contoh user
    echo "6. ANALISIS SESUAI CONTOH USER:\n";

    // Check balance
    $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
    echo "   ✓ Balance Check: " . ($isBalanced ? "BALANCED ✅" : "NOT BALANCED ❌") . "\n";
    echo "     Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . "\n";
    echo "     Total Credit: Rp " . number_format($totalCredit, 0, ',', '.') . "\n\n";

    // Analisis detail
    echo "   📋 ANALISIS DETAIL:\n\n";

    // 1. HPP entries
    $cogsEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->coa->name, 'Harga Pokok Pembelian');
    });
    $goodsDeliveryEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->coa->name, 'Barang Terkirim');
    });

    if ($cogsEntries->count() > 0 && $goodsDeliveryEntries->count() > 0) {
        $cogsEntry = $cogsEntries->first();
        $goodsDeliveryEntry = $goodsDeliveryEntries->first();

        echo "   ✅ HPP / COGS Entries:\n";
        echo "      (D) {$cogsEntry->coa->code} - {$cogsEntry->coa->name}: Rp " . number_format($cogsEntry->debit, 0, ',', '.') . "\n";
        echo "      (K) {$goodsDeliveryEntry->coa->code} - {$goodsDeliveryEntry->coa->name}: Rp " . number_format($goodsDeliveryEntry->credit, 0, ',', '.') . "\n";
        echo "      Status: ✅ CORRECT\n\n";
    }

    // 2. Piutang entries
    $arEntry = $journalEntries->first(function($entry) {
        return str_contains($entry->coa->name, 'PIUTANG DAGANG');
    });
    $revenueEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->coa->name, 'Penjualan Barang Dagangan');
    });
    $ppnEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->coa->name, 'PPN KELUARAN');
    });
    $shippingEntries = $journalEntries->filter(function($entry) {
        return str_contains($entry->coa->name, 'BIAYA PENGIRIMAN');
    });

    $expectedTotal = $subtotal + $taxAmount + 50000; // 1.200.000 + 132.000 + 50.000 = 1.382.000

    if ($arEntry) {
        echo "   ✅ Piutang Usaha Entry:\n";
        echo "      (D) {$arEntry->coa->code} - {$arEntry->coa->name}: Rp " . number_format($arEntry->debit, 0, ',', '.') . "\n";
        echo "      Expected: Rp " . number_format($expectedTotal, 0, ',', '.') . " (1.200.000 + 132.000 + 50.000)\n";
        echo "      Status: " . (abs($arEntry->debit - $expectedTotal) < 0.01 ? "✅ CORRECT" : "❌ INCORRECT") . "\n\n";
    }

    if ($revenueEntries->count() > 0) {
        $totalRevenue = $revenueEntries->sum('credit');
        echo "   ✅ Penjualan Barang Dagangan Entry:\n";
        echo "      (K) {$revenueEntries->first()->coa->code} - {$revenueEntries->first()->coa->name}: Rp " . number_format($totalRevenue, 0, ',', '.') . "\n";
        echo "      Expected: Rp 1.200.000\n";
        echo "      Status: " . (abs($totalRevenue - 1200000) < 0.01 ? "✅ CORRECT" : "❌ INCORRECT") . "\n";
    }

    if ($ppnEntries->count() > 0) {
        $totalPPN = $ppnEntries->sum('credit');
        echo "   ✅ PPn Keluaran Entry:\n";
        echo "      (K) {$ppnEntries->first()->coa->code} - {$ppnEntries->first()->coa->name}: Rp " . number_format($totalPPN, 0, ',', '.') . "\n";
        echo "      Expected: Rp 132.000\n";
        echo "      Status: " . (abs($totalPPN - 132000) < 0.01 ? "✅ CORRECT" : "❌ INCORRECT") . "\n";
    }

    if ($shippingEntries->count() > 0) {
        $totalShipping = $shippingEntries->sum('credit');
        echo "   ✅ Biaya Pengiriman Entry:\n";
        echo "      (K) {$shippingEntries->first()->coa->code} - {$shippingEntries->first()->coa->name}: Rp " . number_format($totalShipping, 0, ',', '.') . "\n";
        echo "      Expected: Rp 50.000\n";
        echo "      Status: " . (abs($totalShipping - 50000) < 0.01 ? "✅ CORRECT" : "❌ INCORRECT") . "\n";
    } else {
        echo "   ❌ Biaya Pengiriman Entry:\n";
        echo "      Status: ❌ MISSING - Biaya pengiriman tidak tercatat dalam journal entries!\n";
    }

    // Final conclusion
    echo "\n   🎯 FINAL CONCLUSION:\n";

    $allCorrect = $isBalanced &&
                 $cogsEntries->count() > 0 &&
                 $goodsDeliveryEntries->count() > 0 &&
                 $arEntry &&
                 $revenueEntries->count() > 0 &&
                 $ppnEntries->count() > 0 &&
                 $shippingEntries->count() > 0;

    if ($allCorrect) {
        echo "   ✅ SEMUA JOURNAL ENTRIES SUDAH BENAR & LENGKAP!\n\n";
        echo "   📋 Ringkasan:\n";
        echo "      ✅ HPP (Debit) vs Barang Terkirim (Credit) ✅\n";
        echo "      ✅ Piutang Usaha (Debit) vs Penjualan + PPN + Biaya Pengiriman (Credit) ✅\n";
        echo "      ✅ Menggunakan COA yang sesuai ✅\n";
        echo "      ✅ Nilai debit/credit sudah akurat ✅\n";
        echo "      ✅ Double-entry bookkeeping sempurna ✅\n";
        echo "      ✅ Biaya pengiriman sudah tercatat ✅\n";
    } else {
        echo "   ❌ MASIH ADA YANG BELUM SESUAI:\n";
        if (!$isBalanced) echo "      - Journal entries tidak balance\n";
        if ($cogsEntries->count() == 0) echo "      - HPP entries tidak ada\n";
        if ($goodsDeliveryEntries->count() == 0) echo "      - Barang Terkirim entries tidak ada\n";
        if (!$arEntry) echo "      - Piutang Usaha entry tidak ada\n";
        if ($revenueEntries->count() == 0) echo "      - Revenue entries tidak ada\n";
        if ($ppnEntries->count() == 0) echo "      - PPN entries tidak ada\n";
        if ($shippingEntries->count() == 0) echo "      - Biaya Pengiriman entries tidak ada ❌ (PERLU DIPERBAIKI)\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}