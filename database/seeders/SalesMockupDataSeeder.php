<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesMockupDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $cabang = Cabang::first() ?? Cabang::create([
            'kode' => 'DUTA-JKT',
            'nama' => 'DUTA KENARI',
            'alamat' => 'Jl. Kenari No. 11, Jakarta',
            'status' => true,
        ]);

        $currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Pieces',
            'abbreviation' => 'PCS',
        ]);

        // 1. Gudang Mockup
        $wh1 = Warehouse::firstOrCreate(
            ['name' => 'KENARI 11 LT 4'],
            ['kode' => 'KENARI-LT4', 'cabang_id' => $cabang->id, 'location' => 'Gedung Kenari Lt 4']
        );
        $wh2 = Warehouse::firstOrCreate(
            ['name' => 'DUTA-K01.2'],
            ['kode' => 'DUTA-K01.2', 'cabang_id' => $cabang->id, 'location' => 'Gudang Duta K01.2']
        );

        $rak1 = Rak::firstOrCreate(['warehouse_id' => $wh1->id, 'name' => 'Rak A-01'], ['code' => 'RAK-A01']);
        $rak2 = Rak::firstOrCreate(['warehouse_id' => $wh2->id, 'name' => 'Rak B-01'], ['code' => 'RAK-B01']);

        // 2. Customer Mockup
        $custDaya = Customer::firstOrCreate(
            ['name' => 'DAYA TEKNIK MEDIKA'],
            [
                'code' => 'CUST-DTM',
                'perusahaan' => 'PT DAYA TEKNIK MEDIKA',
                'address' => 'Kawasan Industri Pulogadung Blok B No. 12',
                'phone' => '021-4601234',
                'telephone' => '021-4601234',
                'email' => 'daya@teknikmedika.com',
                'fax' => '021-4601235',
                'nik_npwp' => '0123456789012345',
                'isSpecial' => false,
                'tempo_kredit' => 30,
                'kredit_limit' => 50000000,
                'tipe_pembayaran' => 'Kredit',
                'tipe' => 'PKP',
                'cabang_id' => $cabang->id,
            ]
        );

        $custB = Customer::firstOrCreate(
            ['name' => 'PT Bintang Teknik'],
            [
                'code' => 'CUST-BT',
                'perusahaan' => 'PT Bintang Teknik Perkasa',
                'address' => 'Jl. Daan Mogot KM 11',
                'phone' => '021-5401122',
                'telephone' => '021-5401122',
                'email' => 'bintang@teknik.com',
                'fax' => '021-5401123',
                'nik_npwp' => '0123456789012346',
                'isSpecial' => false,
                'tempo_kredit' => 14,
                'kredit_limit' => 20000000,
                'tipe_pembayaran' => 'Kredit',
                'tipe' => 'PKP',
                'cabang_id' => $cabang->id,
            ]
        );

        $category = \App\Models\ProductCategory::first();
        $supplier = \App\Models\Supplier::first();

        // 3. Produk Mockup
        $prod1 = Product::firstOrCreate(
            ['sku' => 'ETBG000016'],
            [
                'name' => "COPPER ELBO 5/8''",
                'sell_price' => 7000,
                'cost_price' => 5000,
                'harga_batas' => 5667,
                'product_category_id' => $category?->id,
                'supplier_id' => $supplier?->id,
                'uom_id' => $uom->id,
                'is_active' => true,
                'cabang_id' => $cabang->id,
                'kode_merk' => 'MRK-ETB',
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'biaya' => 0,
                'item_value' => 5000,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
            ]
        );

        $prod2 = Product::firstOrCreate(
            ['sku' => 'ETBG000013'],
            [
                'name' => "COPPER ELBO 1/2''",
                'sell_price' => 4500,
                'cost_price' => 3000,
                'harga_batas' => 3600,
                'product_category_id' => $category?->id,
                'supplier_id' => $supplier?->id,
                'uom_id' => $uom->id,
                'is_active' => true,
                'cabang_id' => $cabang->id,
                'kode_merk' => 'MRK-ETB',
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'biaya' => 0,
                'item_value' => 3000,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
            ]
        );

        // Set Stok Bebas pada Gudang
        InventoryStock::updateOrCreate(
            ['product_id' => $prod1->id, 'warehouse_id' => $wh1->id, 'rak_id' => $rak1->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $prod1->id, 'warehouse_id' => $wh1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $prod2->id, 'warehouse_id' => $wh1->id, 'rak_id' => $rak1->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $prod2->id, 'warehouse_id' => $wh1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $prod2->id, 'warehouse_id' => $wh2->id, 'rak_id' => $rak2->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $prod2->id, 'warehouse_id' => $wh2->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        // 4. Buat SO Mockup sesuai Mockup B1 & E2
        // SO-00011: Perlu Atasan (Request Approve)
        $so11 = SaleOrder::updateOrCreate(
            ['so_number' => 'SO-00011'],
            [
                'customer_id' => $custDaya->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->subDays(2),
                'tipe_pengiriman' => 'Kirim Langsung',
                'currency_id' => $currency->id,
                'exchange_rate' => 1,
                'tempo_pembayaran' => 30,
                'shipping_cost' => 15000,
                'status' => 'request_approve',
                'created_by' => $user->id,
                'total_amount' => 65000,
            ]
        );
        SaleOrderItem::updateOrCreate(
            ['sale_order_id' => $so11->id, 'product_id' => $prod1->id],
            ['quantity' => 5, 'delivered_quantity' => 0, 'unit_price' => 7000]
        );
        SaleOrderItem::updateOrCreate(
            ['sale_order_id' => $so11->id, 'product_id' => $prod2->id],
            ['quantity' => 10, 'delivered_quantity' => 0, 'unit_price' => 3000] // di bawah min 3600
        );

        // SO-00010: Draft
        $so10 = SaleOrder::updateOrCreate(
            ['so_number' => 'SO-00010'],
            [
                'customer_id' => $custB->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->subDays(2),
                'tipe_pengiriman' => 'Kirim Langsung',
                'currency_id' => $currency->id,
                'exchange_rate' => 1,
                'tempo_pembayaran' => 14,
                'shipping_cost' => 50000,
                'status' => 'draft',
                'created_by' => $user->id,
                'total_amount' => 2400000,
            ]
        );
        SaleOrderItem::updateOrCreate(
            ['sale_order_id' => $so10->id, 'product_id' => $prod1->id],
            ['quantity' => 200, 'delivered_quantity' => 0, 'unit_price' => 7000]
        );

        // SO-00007: Sebagian DO (Approved, partially delivered)
        $so7 = SaleOrder::updateOrCreate(
            ['so_number' => 'SO-00007'],
            [
                'customer_id' => $custDaya->id,
                'cabang_id' => $cabang->id,
                'order_date' => now()->subDays(3),
                'tipe_pengiriman' => 'Kirim Langsung',
                'currency_id' => $currency->id,
                'exchange_rate' => 1,
                'tempo_pembayaran' => 30,
                'shipping_cost' => 15000,
                'status' => 'approved',
                'approve_at' => now()->subDays(3),
                'approve_by' => $user->id,
                'created_by' => $user->id,
                'total_amount' => 80000,
            ]
        );
        SaleOrderItem::updateOrCreate(
            ['sale_order_id' => $so7->id, 'product_id' => $prod1->id],
            ['quantity' => 5, 'delivered_quantity' => 0, 'unit_price' => 7000]
        );
        SaleOrderItem::updateOrCreate(
            ['sale_order_id' => $so7->id, 'product_id' => $prod2->id],
            ['quantity' => 10, 'delivered_quantity' => 0, 'unit_price' => 4500]
        );

        // Driver & Vehicle
        $driver = \App\Models\Driver::firstOrCreate(
            ['name' => 'Budi Santoso'],
            ['phone' => '08123456789', 'cabang_id' => $cabang->id]
        );
        $vehicle = \App\Models\Vehicle::firstOrCreate(
            ['plate' => 'B 9876 DTA'],
            ['type' => 'Blind Van GranMax', 'capacity' => 1000, 'cabang_id' => $cabang->id]
        );

        // DO-0007-01 untuk SO-00007
        $do7 = DeliveryOrder::updateOrCreate(
            ['do_number' => 'DO-0007-01'],
            [
                'delivery_date' => now()->subDays(1),
                'warehouse_id' => $wh1->id,
                'cabang_id' => $cabang->id,
                'status' => 'sent',
                'created_by' => $user->id,
            ]
        );
        $do7->salesOrders()->sync([$so7->id]);

        $soItem1 = SaleOrderItem::where('sale_order_id', $so7->id)->where('product_id', $prod1->id)->first();
        $soItem2 = SaleOrderItem::where('sale_order_id', $so7->id)->where('product_id', $prod2->id)->first();

        DeliveryOrderItem::updateOrCreate(
            ['delivery_order_id' => $do7->id, 'product_id' => $prod1->id],
            ['quantity' => 5, 'sale_order_item_id' => $soItem1?->id]
        );
        DeliveryOrderItem::updateOrCreate(
            ['delivery_order_id' => $do7->id, 'product_id' => $prod2->id],
            ['quantity' => 4, 'sale_order_item_id' => $soItem2?->id]
        );

        // DO-0007-02 untuk sisa kiriman (Siap dicek di gudang DUTA-K01.2)
        $do7_2 = DeliveryOrder::updateOrCreate(
            ['do_number' => 'DO-0007-02'],
            [
                'delivery_date' => now(),
                'warehouse_id' => $wh2->id,
                'cabang_id' => $cabang->id,
                'status' => 'approved',
                'created_by' => $user->id,
            ]
        );
        $do7_2->salesOrders()->sync([$so7->id]);
        DeliveryOrderItem::updateOrCreate(
            ['delivery_order_id' => $do7_2->id, 'product_id' => $prod2->id],
            ['quantity' => 6, 'sale_order_item_id' => $soItem2?->id]
        );

        // Surat Jalan SJ-0007-01
        $sj7 = \App\Models\SuratJalan::updateOrCreate(
            ['sj_number' => 'SJ-0007-01'],
            [
                'issued_at' => now()->subDays(1),
                'status' => \App\Models\SuratJalan::STATUS_ISSUED,
                'signed_by' => $user->id,
                'created_by' => $user->id,
                'cabang_id' => $cabang->id,
            ]
        );
        $sj7->deliveryOrder()->sync([$do7->id]);

        // Schedule
        $sch7 = \App\Models\DeliverySchedule::updateOrCreate(
            ['schedule_number' => 'SCH-0007-01'],
            [
                'scheduled_date' => now()->subDays(1)->format('Y-m-d'),
                'delivery_method' => 'internal',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'driver_name' => $driver->name,
                'vehicle_info' => $vehicle->plate,
                'status' => 'delivered',
                'created_by' => $user->id,
                'cabang_id' => $cabang->id,
            ]
        );
        $sj7->deliverySchedules()->sync([$sch7->id]);

        // Sales Invoice INV-0007-01
        $inv7 = \App\Models\Invoice::updateOrCreate(
            ['invoice_number' => 'INV-0007-01'],
            [
                'from_model_type' => \App\Models\SuratJalan::class,
                'from_model_id' => $sj7->id,
                'surat_jalan_ids' => [$sj7->id],
                'delivery_orders' => [$do7->id],
                'invoice_date' => now()->subDays(1),
                'due_date' => now()->addDays(29),
                'cabang_id' => $cabang->id,
                'currency_id' => $currency->id,
                'exchange_rate' => 1,
                'customer_name' => $custDaya->name,
                'customer_phone' => $custDaya->phone,
                'subtotal' => 53000,
                'dpp' => 53000,
                'tax' => 0,
                'other_fee' => 15000,
                'total' => 68000,
                'status' => \App\Models\Invoice::STATUS_SENT,
            ]
        );
        \App\Models\InvoiceItem::updateOrCreate(
            ['invoice_id' => $inv7->id, 'product_id' => $prod1->id],
            ['quantity' => 5, 'price' => 7000, 'discount' => 0, 'subtotal' => 35000, 'total' => 35000]
        );
        \App\Models\InvoiceItem::updateOrCreate(
            ['invoice_id' => $inv7->id, 'product_id' => $prod2->id],
            ['quantity' => 4, 'price' => 4500, 'discount' => 0, 'subtotal' => 18000, 'total' => 18000]
        );
    }
}
