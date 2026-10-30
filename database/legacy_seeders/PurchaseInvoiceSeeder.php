<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountPayable;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Cabang;
use Carbon\Carbon;

class PurchaseInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Suppliers (only if they don't exist)
        $suppliers = [
            [
                'code' => 'PUR-SUP001',
                'name' => 'PT Supplier Utama',
                'perusahaan' => 'PT Supplier Utama',
                'email' => 'admin@supplierutama.com',
                'phone' => '021-1234567',
                'handphone' => '0812-3456-7890',
                'fax' => '021-1234568',
                'npwp' => '12.345.678.9-012.345',
                'address' => 'Jl. Industri No. 123, Jakarta Barat',
                'kontak_person' => 'Budi Santoso',
                'tempo_hutang' => 30,
                'keterangan' => 'Supplier utama untuk bahan baku',
            ],
            [
                'code' => 'PUR-SUP002',
                'name' => 'CV Mitra Sejahtera',
                'perusahaan' => 'CV Mitra Sejahtera',
                'email' => 'info@mitrasejahtera.com',
                'phone' => '021-7654321',
                'handphone' => '0812-7654-3210',
                'fax' => '021-7654322',
                'npwp' => '23.456.789.0-123.456',
                'address' => 'Jl. Perdagangan No. 456, Jakarta Pusat',
                'kontak_person' => 'Siti Rahayu',
                'tempo_hutang' => 14,
                'keterangan' => 'Supplier komponen elektronik',
            ],
            [
                'code' => 'PUR-SUP003',
                'name' => 'PT Global Trading',
                'perusahaan' => 'PT Global Trading',
                'email' => 'purchase@globaltrading.com',
                'phone' => '021-9876543',
                'handphone' => '0812-9876-5432',
                'fax' => '021-9876544',
                'npwp' => '34.567.890.1-234.567',
                'address' => 'Jl. Internasional No. 789, Jakarta Selatan',
                'kontak_person' => 'Ahmad Wijaya',
                'tempo_hutang' => 21,
                'keterangan' => 'Supplier material import',
            ]
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate(
                ['code' => $supplierData['code']],
                $supplierData
            );
        }

        // 2. Create Products (only if they don't exist)
        $products = [
            [
                'name' => 'Bahan Baku A',
                'sku' => 'BB001',
                'description' => 'Bahan baku utama untuk produksi',
                'cost_price' => 15000,
                'sell_price' => 20000,
                'cabang_id' => 1,
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'MERK-BB001',
                'biaya' => 0,
                'harga_batas' => 10000,
                'tipe_pajak' => 'Eksklusif',
                'pajak' => 11,
            ],
            [
                'name' => 'Komponen B',
                'sku' => 'KB001',
                'description' => 'Komponen elektronik',
                'cost_price' => 25000,
                'sell_price' => 35000,
                'cabang_id' => 1,
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'MERK-KB001',
                'biaya' => 0,
                'harga_batas' => 20000,
                'tipe_pajak' => 'Eksklusif',
                'pajak' => 11,
            ],
            [
                'name' => 'Material C',
                'sku' => 'MC001',
                'description' => 'Material kain berkualitas',
                'cost_price' => 12000,
                'sell_price' => 18000,
                'cabang_id' => 1,
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'MERK-MC001',
                'biaya' => 0,
                'harga_batas' => 8000,
                'tipe_pajak' => 'Eksklusif',
                'pajak' => 11,
            ],
            [
                'name' => 'Tools D',
                'sku' => 'TD001',
                'description' => 'Set alat kerja lengkap',
                'cost_price' => 150000,
                'sell_price' => 200000,
                'cabang_id' => 1,
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'MERK-TD001',
                'biaya' => 0,
                'harga_batas' => 120000,
                'tipe_pajak' => 'Eksklusif',
                'pajak' => 11,
            ]
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );
        }

        // 3. Ensure Chart of Accounts exist
        $coaData = [
            [
                'code' => '1101',
                'name' => 'Kas',
                'type' => 'Asset',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
                'description' => 'Kas perusahaan',
            ],
            [
                'code' => '1102',
                'name' => 'Bank BCA',
                'type' => 'Asset',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
                'description' => 'Rekening Bank BCA',
            ],
            [
                'code' => '1201',
                'name' => 'Piutang Usaha',
                'type' => 'Asset',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
                'description' => 'Piutang dari pelanggan',
            ],
            [
                'code' => '2101',
                'name' => 'Hutang Usaha',
                'type' => 'Liability',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
                'description' => 'Hutang kepada supplier',
            ],
            [
                'code' => '5101',
                'name' => 'Pembelian',
                'type' => 'Expense',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
                'description' => 'Biaya pembelian barang',
            ]
        ];

        foreach ($coaData as $coa) {
            ChartOfAccount::firstOrCreate(
                ['code' => $coa['code']],
                $coa
            );
        }

        // 4. Get first available Cabang or create simple one
        $cabang = Cabang::first();
        if (!$cabang) {
            $cabang = Cabang::create([
                'kode' => 'HO',
                'nama' => 'Head Office',
                'alamat' => 'Jakarta',
                'telepon' => '021-1111111',
                'status' => 1,
                'kenaikan_harga' => 0,
            ]);
        }

        // 5. Create Purchase Orders with Items
        $suppliers = Supplier::where('code', 'like', 'PUR-SUP%')->get(); // Use new suppliers
        $products = Product::all();
        $hutangUsahaCoa = ChartOfAccount::where('code', '2101')->first();
        $pembelianCoa = ChartOfAccount::where('code', '5101')->first();

        $purchaseData = [
            [
                'supplier_id' => $suppliers[0]->id,
                'date' => Carbon::now()->subDays(30),
                'due_date' => Carbon::now()->subDays(0),
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 100, 'price' => 15000],
                    ['product_id' => $products[1]->id, 'quantity' => 50, 'price' => 25000],
                ]
            ],
            [
                'supplier_id' => $suppliers[1]->id,
                'date' => Carbon::now()->subDays(25),
                'due_date' => Carbon::now()->addDays(5),
                'items' => [
                    ['product_id' => $products[2]->id, 'quantity' => 200, 'price' => 12000],
                    ['product_id' => $products[3]->id, 'quantity' => 10, 'price' => 150000],
                ]
            ],
            [
                'supplier_id' => $suppliers[2]->id,
                'date' => Carbon::now()->subDays(20),
                'due_date' => Carbon::now()->addDays(10),
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 75, 'price' => 15000],
                    ['product_id' => $products[2]->id, 'quantity' => 150, 'price' => 12000],
                ]
            ],
            [
                'supplier_id' => $suppliers[0]->id,
                'date' => Carbon::now()->subDays(15),
                'due_date' => Carbon::now()->addDays(15),
                'items' => [
                    ['product_id' => $products[1]->id, 'quantity' => 30, 'price' => 25000],
                    ['product_id' => $products[3]->id, 'quantity' => 5, 'price' => 150000],
                ]
            ],
            [
                'supplier_id' => $suppliers[1]->id,
                'date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->addDays(20),
                'items' => [
                    ['product_id' => $products[0]->id, 'quantity' => 120, 'price' => 15000],
                ]
            ]
        ];

        foreach ($purchaseData as $index => $purchaseInfo) {
            // Create Purchase Order
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => 'PO' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $purchaseInfo['supplier_id'],
                'order_date' => $purchaseInfo['date'],
                'expected_date' => $purchaseInfo['due_date'],
                'status' => 'completed',
                'note' => 'Purchase order untuk kebutuhan produksi',
                'total_amount' => 0, // Will be updated later
                'tempo_hutang' => 30,
                'warehouse_id' => 1, // Default warehouse
                'is_asset' => false,
                'created_at' => $purchaseInfo['date'],
                'updated_at' => $purchaseInfo['date'],
            ]);

            $subtotal = 0;

            // Create Purchase Order Items
            foreach ($purchaseInfo['items'] as $item) {
                $total = $item['quantity'] * $item['price'];
                $subtotal += $total;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount' => 0,
                    'tax' => 0,
                    'tipe_pajak' => 'Non Pajak',
                    'currency_id' => 1,
                ]);
            }

            // Calculate totals
            $tax = $subtotal * 0.11; // PPN 11%
            $total = $subtotal + $tax;

            // Update purchase order totals
            $purchaseOrder->update([
                'total_amount' => $total,
            ]);

            // Create Invoice
            $invoice = Invoice::create([
                'invoice_number' => 'INV-PUR-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'from_model_type' => 'App\Models\Supplier',
                'from_model_id' => $purchaseInfo['supplier_id'],
                'invoice_date' => $purchaseInfo['date']->addDays(1),
                'due_date' => $purchaseInfo['due_date'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'draft',
                'ppn_rate' => 11,
                'dpp' => $subtotal,
                'supplier_name' => $suppliers->find($purchaseInfo['supplier_id'])->name,
                'supplier_phone' => $suppliers->find($purchaseInfo['supplier_id'])->phone,
                'created_at' => $purchaseInfo['date']->addDays(1),
                'updated_at' => $purchaseInfo['date']->addDays(1),
            ]);

            // Create Invoice Items
            foreach ($purchaseInfo['items'] as $item) {
                $total = $item['quantity'] * $item['price'];

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $total,
                ]);
            }

            // Create Account Payable
            AccountPayable::create([
                'invoice_id' => $invoice->id,
                'supplier_id' => $purchaseInfo['supplier_id'],
                'total' => $total,
                'paid' => 0,
                'remaining' => $total,
                'status' => 'Belum Lunas',
            ]);
        }

        // 6. Create some partial payments for testing
        $firstInvoice = Invoice::where('from_model_type', 'App\Models\Supplier')->first();
        if ($firstInvoice) {
            $accountPayable = AccountPayable::where('invoice_id', $firstInvoice->id)->first();
            if ($accountPayable) {
                // Create partial payment
                $partialPayment = $accountPayable->total * 0.5; // 50% payment
                $accountPayable->update([
                    'paid' => $partialPayment,
                    'remaining' => $accountPayable->total - $partialPayment,
                    'status' => 'Belum Lunas'
                ]);
            }
        }

        $this->command->info('Purchase Invoice seeder completed successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . Supplier::count() . ' Suppliers');
        $this->command->info('- ' . Product::count() . ' Products');
        $this->command->info('- ' . PurchaseOrder::count() . ' Purchase Orders');
        $this->command->info('- ' . Invoice::where('from_model_type', 'App\Models\Supplier')->count() . ' Purchase Invoices');
        $this->command->info('- ' . AccountPayable::count() . ' Account Payables');
        $this->command->info('');
        $this->command->info('You can now test Vendor Payment creation with these invoices!');
    }
}
