<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating quick test data using direct SQL...');

        // Insert Chart of Accounts
        DB::table('chart_of_accounts')->insertOrIgnore([
            ['code' => '1-1000', 'name' => 'Kas', 'type' => 'Asset', 'level' => 1, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1-1200', 'name' => 'Piutang Usaha', 'type' => 'Asset', 'level' => 1, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1-1400', 'name' => 'Penyesuaian Piutang', 'type' => 'Asset', 'level' => 1, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '2-1000', 'name' => 'Hutang Usaha', 'type' => 'Liability', 'level' => 1, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '2-1100', 'name' => 'Penyesuaian Hutang', 'type' => 'Liability', 'level' => 1, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert Currencies
        DB::table('currencies')->insertOrIgnore([
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert Customers with all required fields
        DB::table('customers')->insertOrIgnore([
            [
                'name' => 'PT Test Customer',
                'code' => 'CUST001',
                'address' => 'Jl. Test Customer No. 1',
                'phone' => '021-11111111',
                'telephone' => '021-11111111',
                'email' => 'test@customer.com',
                'perusahaan' => 'PT Test Customer',
                'tipe' => 'PKP',
                'fax' => '021-11111112',
                'isSpecial' => false,
                'tempo_kredit' => 30,
                'kredit_limit' => 50000000,
                'tipe_pembayaran' => 'Kredit',
                'nik_npwp' => '1234567890123456',
                'keterangan' => 'Test customer untuk testing',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Suppliers with all required fields
        DB::table('suppliers')->insertOrIgnore([
            [
                'code' => 'SUPP001',
                'name' => 'PT Test Supplier',
                'perusahaan' => 'PT Test Supplier',
                'address' => 'Jl. Test Supplier No. 1',
                'phone' => '021-22222222',
                'email' => 'test@supplier.com',
                'handphone' => '081234567890',
                'fax' => '021-22222223',
                'npwp' => '1234567890123456',
                'tempo_hutang' => 30,
                'kontak_person' => 'Andi Wijaya',
                'keterangan' => 'Test supplier untuk testing',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Products with all required fields
        DB::table('products')->insertOrIgnore([
            [
                'name' => 'Test Product 1',
                'sku' => 'PRD001',
                'cabang_id' => 1,
                'supplier_id' => null,
                'product_category_id' => 1,
                'cost_price' => 1000000,
                'sell_price' => 1500000,
                'biaya' => 0,
                'harga_batas' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
                'kode_merk' => 'TM001',
                'description' => 'Test product untuk testing',
                'uom_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Test Product 2',
                'sku' => 'PRD002',
                'cabang_id' => 1,
                'supplier_id' => null,
                'product_category_id' => 1,
                'cost_price' => 2000000,
                'sell_price' => 3000000,
                'biaya' => 0,
                'harga_batas' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
                'kode_merk' => 'TM002',
                'description' => 'Test product kedua untuk testing',
                'uom_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Sales Orders (using correct column names)
        DB::table('sale_orders')->insertOrIgnore([
            [
                'so_number' => 'SO-2024-001',
                'customer_id' => DB::table('customers')->where('code', 'CUST001')->first()->id ?? 1,
                'order_date' => now()->subDays(30),
                'delivery_date' => now()->subDays(25),
                'status' => 'completed',
                'total_amount' => 6000000,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $saleOrderId = DB::table('sale_orders')->where('so_number', 'SO-2024-001')->first()->id ?? 1;
        $product1Id = DB::table('products')->where('sku', 'PRD001')->first()->id ?? 1;
        $product2Id = DB::table('products')->where('sku', 'PRD002')->first()->id ?? 2;

        // Insert Sale Order Items
        DB::table('sale_order_items')->insertOrIgnore([
            [
                'sale_order_id' => $saleOrderId,
                'product_id' => $product1Id,
                'quantity' => 2,
                'unit_price' => 1500000,
                'discount' => 0,
                'tax' => 0,
                'warehouse_id' => 1,
                'rak_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'sale_order_id' => $saleOrderId,
                'product_id' => $product2Id,
                'quantity' => 1,
                'unit_price' => 3000000,
                'discount' => 0,
                'tax' => 0,
                'warehouse_id' => 1,
                'rak_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Invoices (simplified structure)
        DB::table('invoices')->insertOrIgnore([
            [
                'invoice_number' => 'INV-2024-001',
                'from_model_type' => 'App\Models\SaleOrder',
                'from_model_id' => $saleOrderId,
                'customer_name' => 'PT Test Customer',
                'customer_phone' => '021-11111111',
                'invoice_date' => now()->subDays(20),
                'due_date' => now()->addDays(10),
                'subtotal' => 6000000,
                'tax' => 0,
                'other_fee' => 0,
                'dpp' => 6000000,
                'ppn_rate' => 0,
                'total' => 6000000,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $invoiceId = DB::table('invoices')->where('invoice_number', 'INV-2024-001')->first()->id ?? 1;
        $customerId = DB::table('customers')->where('code', 'CUST001')->first()->id ?? 1;

        // Insert Invoice Items
        DB::table('invoice_items')->insertOrIgnore([
            [
                'invoice_id' => $invoiceId,
                'product_id' => $product1Id,
                'quantity' => 2,
                'price' => 1500000,
                'total' => 3000000,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'invoice_id' => $invoiceId,
                'product_id' => $product2Id,
                'quantity' => 1,
                'price' => 3000000,
                'total' => 3000000,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Account Receivables (simplified)
        DB::table('account_receivables')->insertOrIgnore([
            [
                'invoice_id' => $invoiceId,
                'customer_id' => $customerId,
                'invoice_number' => 'INV-2024-001',
                'due_date' => now()->addDays(10),
                'original_amount' => 6000000,
                'remaining_amount' => 4200000, // 70% remaining
                'status' => 'partial',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Purchase Orders (simplified)
        $supplierId = DB::table('suppliers')->where('code', 'SUPP001')->first()->id ?? 1;
        $currencyId = DB::table('currencies')->where('code', 'IDR')->first()->id ?? 1;

        DB::table('purchase_orders')->insertOrIgnore([
            [
                'po_number' => 'PO-2024-001',
                'supplier_id' => $supplierId,
                'order_date' => now()->subDays(25),
                'delivery_date' => now()->subDays(20),
                'expected_date' => now()->subDays(20),
                'status' => 'completed',
                'total_amount' => 10000000,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $purchaseOrderId = DB::table('purchase_orders')->where('po_number', 'PO-2024-001')->first()->id ?? 1;

        // Insert Purchase Order Items
        DB::table('purchase_order_items')->insertOrIgnore([
            [
                'purchase_order_id' => $purchaseOrderId,
                'product_id' => $product1Id,
                'quantity' => 10,
                'unit_price' => 1000000,
                'discount' => 0,
                'tax' => 0,
                'tipe_pajak' => 'Non Pajak',
                'refer_item_model_id' => null,
                'refer_item_model_type' => null,
                'currency_id' => $currencyId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Purchase Invoice (simplified)
        DB::table('invoices')->insertOrIgnore([
            [
                'invoice_number' => 'PINV-2024-001',
                'from_model_type' => 'App\Models\PurchaseOrder',
                'from_model_id' => $purchaseOrderId,
                'customer_name' => 'PT Test Supplier',
                'customer_phone' => '021-22222222',
                'invoice_date' => now()->subDays(15),
                'due_date' => now()->addDays(15),
                'subtotal' => 10000000,
                'tax' => 0,
                'other_fee' => 0,
                'dpp' => 10000000,
                'ppn_rate' => 0,
                'total' => 10000000,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $purchaseInvoiceId = DB::table('invoices')->where('invoice_number', 'PINV-2024-001')->first()->id ?? 2;

        // Insert Purchase Invoice Items
        DB::table('invoice_items')->insertOrIgnore([
            [
                'invoice_id' => $purchaseInvoiceId,
                'product_id' => $product1Id,
                'quantity' => 10,
                'price' => 1000000,
                'total' => 10000000,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Insert Account Payables (simplified)
        DB::table('account_payables')->insertOrIgnore([
            [
                'invoice_id' => $purchaseInvoiceId,
                'supplier_id' => $supplierId,
                'invoice_number' => 'PINV-2024-001',
                'due_date' => now()->addDays(15),
                'original_amount' => 10000000,
                'remaining_amount' => 8000000, // 80% remaining
                'status' => 'partial',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $this->command->info('Quick test data created successfully!');
        $this->command->info('Created data for testing Customer Receipt and Vendor Payment functionality.');
        $this->command->info('- Sales Invoice: INV-2024-001 (Rp 6.000.000, remaining: Rp 4.200.000)');
        $this->command->info('- Purchase Invoice: PINV-2024-001 (Rp 10.000.000, remaining: Rp 8.000.000)');
    }
}
