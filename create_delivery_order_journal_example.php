<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\Cabang;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Services\DeliveryOrderService;

echo "=== Membuat Contoh Data Delivery Order dengan Journal Entries ===\n\n";

// 1. Ambil atau buat data master yang diperlukan
$customer = Customer::first() ?? Customer::create([
    'name' => 'PT. Contoh Customer',
    'contact_person' => 'John Doe',
    'phone' => '08123456789',
    'email' => 'john@example.com',
    'address' => 'Jl. Contoh No. 123',
    'city' => 'Jakarta',
    'province' => 'DKI Jakarta',
    'country' => 'Indonesia',
    'postal_code' => '12345',
    'npwp' => '01.234.567.8-901.000',
    'status' => 'active'
]);

$warehouse = Warehouse::first() ?? Warehouse::create([
    'name' => 'Gudang Utama',
    'location' => 'Jakarta',
    'status' => 'active'
]);

$cabang = Cabang::first() ?? Cabang::create([
    'nama' => 'Cabang Jakarta',
    'alamat' => 'Jl. Jakarta No. 1',
    'kota' => 'Jakarta',
    'telepon' => '021-1234567',
    'email' => 'jakarta@company.com',
    'status' => 'active'
]);

// 2. Buat produk dengan COA yang diperlukan
$inventoryCoa = ChartOfAccount::where('code', '1140.10')->first() ??
    ChartOfAccount::create([
        'code' => '1140.10',
        'name' => 'Persediaan Barang Dagang',
        'type' => 'asset',
        'category' => 'current_asset',
        'is_active' => true
    ]);

$goodsDeliveryCoa = ChartOfAccount::where('code', '1180.10')->first() ??
    ChartOfAccount::create([
        'code' => '1180.10',
        'name' => 'Beban Pokok Penjualan',
        'type' => 'expense',
        'category' => 'cost_of_goods_sold',
        'is_active' => true
    ]);

$salesCoa = ChartOfAccount::where('code', '4100.01')->first() ??
    ChartOfAccount::create([
        'code' => '4100.01',
        'name' => 'Penjualan Barang Dagang',
        'type' => 'revenue',
        'category' => 'sales',
        'is_active' => true
    ]);

$product = Product::create([
    'name' => 'Produk Contoh Journal',
    'sku' => 'PRD-JOURNAL-' . date('YmdHis'),
    'description' => 'Produk untuk testing journal entries',
    'product_category_id' => 1,
    'uom_id' => 1,
    'cost_price' => 100000,
    'sell_price' => 150000,
    'cabang_id' => $cabang->id,
    'supplier_id' => null,
    'harga_batas' => 0,
    'item_value' => '0.00',
    'tipe_pajak' => 'Non Pajak',
    'pajak' => '0.00',
    'jumlah_kelipatan_gudang_besar' => 0,
    'jumlah_jual_kategori_banyak' => 0,
    'kode_merk' => 'MERK-JOURNAL',
    'biaya' => '0.00',
    'is_manufacture' => 0,
    'is_raw_material' => 0,
    'inventory_coa_id' => $inventoryCoa->id,
    'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
    'sales_coa_id' => $salesCoa->id,
    'sales_return_coa_id' => $salesCoa->id,
    'sales_discount_coa_id' => $salesCoa->id,
    'cogs_coa_id' => $goodsDeliveryCoa->id,
    'purchase_return_coa_id' => $inventoryCoa->id,
    'unbilled_purchase_coa_id' => $inventoryCoa->id,
    'temporary_procurement_coa_id' => $inventoryCoa->id,
    'is_active' => 1
]);

// 3. Buat Sales Order
$saleOrder = SaleOrder::create([
    'so_number' => 'SO-JOURNAL-' . date('YmdHis'),
    'customer_id' => $customer->id,
    'cabang_id' => $cabang->id,
    'order_date' => now(),
    'delivery_date' => now()->addDays(7),
    'status' => 'approved',
    'sales_person' => 'Sales Person Test',
    'notes' => 'Sales Order untuk testing journal entries'
]);

$saleOrderItem = SaleOrderItem::create([
    'sale_order_id' => $saleOrder->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 150000,
    'subtotal' => 1500000,
    'delivered_quantity' => 0,
    'warehouse_id' => $warehouse->id
]);

echo "✓ Sales Order dibuat: {$saleOrder->so_number}\n";

// 4. Buat Delivery Order
$deliveryOrder = DeliveryOrder::create([
    'do_number' => 'DO-JOURNAL-' . date('YmdHis'),
    'sale_order_id' => $saleOrder->id,
    'customer_id' => $customer->id,
    'warehouse_id' => $warehouse->id,
    'cabang_id' => $cabang->id,
    'driver_id' => 1, // Menggunakan driver yang ada
    'vehicle_id' => 1, // Menggunakan vehicle yang ada
    'delivery_date' => now(),
    'status' => 'approved', // Akan diubah ke 'sent' untuk trigger journal
    'driver_name' => 'Driver Test',
    'vehicle_number' => 'B 1234 CD',
    'notes' => 'Delivery Order untuk testing journal entries'
]);

$deliveryOrderItem = DeliveryOrderItem::create([
    'delivery_order_id' => $deliveryOrder->id,
    'sale_order_item_id' => $saleOrderItem->id,
    'product_id' => $product->id,
    'quantity' => 5, // Kirim 5 dari 10 yang dipesan
    'unit_price' => 150000,
    'subtotal' => 750000,
    'warehouse_id' => $warehouse->id,
    'rak_id' => null
]);

echo "✓ Delivery Order dibuat: {$deliveryOrder->do_number}\n";

// 5. Ubah status delivery order menjadi 'sent' untuk trigger journal creation
echo "\n=== Mengubah status Delivery Order menjadi 'sent' ===\n";

$deliveryOrderService = app(DeliveryOrderService::class);
$deliveryOrderService->updateStatus($deliveryOrder, 'sent');

echo "✓ Status Delivery Order diubah ke 'sent'\n";

// 6. Tampilkan journal entries yang dibuat
echo "\n=== Journal Entries yang Dibuat ===\n";

$journalEntries = JournalEntry::where('source_type', DeliveryOrder::class)
    ->where('source_id', $deliveryOrder->id)
    ->orderBy('coa_id')
    ->get();

foreach ($journalEntries as $entry) {
    echo sprintf(
        "Journal ID: %d | COA: %s | Debit: %s | Credit: %s | Description: %s\n",
        $entry->id,
        $entry->coa->code . ' - ' . $entry->coa->name,
        number_format($entry->debit, 0, ',', '.'),
        number_format($entry->credit, 0, ',', '.'),
        $entry->description
    );
}

// 7. Hitung total journal
$totalDebit = $journalEntries->sum('debit');
$totalCredit = $journalEntries->sum('credit');

echo "\n=== Ringkasan Journal ===\n";
echo "Total Debit: " . number_format($totalDebit, 0, ',', '.') . "\n";
echo "Total Credit: " . number_format($totalCredit, 0, ',', '.') . "\n";
echo "Balance: " . ($totalDebit == $totalCredit ? '✓ Balanced' : '✗ Not Balanced') . "\n";

// 8. Detail perhitungan
echo "\n=== Detail Perhitungan ===\n";
echo "Quantity dikirim: {$deliveryOrderItem->quantity} unit\n";
echo "Cost per unit: " . number_format($product->cost_price, 0, ',', '.') . "\n";
echo "Total cost: " . number_format($deliveryOrderItem->quantity * $product->cost_price, 0, ',', '.') . "\n";
echo "\nJournal Entries:\n";
echo "- Debit Goods Delivery Expense (Cost of Goods Sold): " . number_format($deliveryOrderItem->quantity * $product->cost_price, 0, ',', '.') . "\n";
echo "- Credit Inventory Reduction: " . number_format($deliveryOrderItem->quantity * $product->cost_price, 0, ',', '.') . "\n";

echo "\n=== Data Berhasil Dibuat ===\n";
echo "Sales Order: {$saleOrder->so_number}\n";
echo "Delivery Order: {$deliveryOrder->do_number}\n";
echo "Product: {$product->name} (Cost: " . number_format($product->cost_price, 0, ',', '.') . ")\n";
echo "Quantity Sent: {$deliveryOrderItem->quantity} unit\n";
echo "Journal Entries Created: {$journalEntries->count()}\n";