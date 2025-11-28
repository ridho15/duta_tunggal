<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ChartOfAccount;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\Currency;
use App\Models\InventoryStock;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating master data...');

        // Create currencies - MOVED TO FinanceSeeder
        // $currencies = [
        //     ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1],
        //     ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'to_rupiah' => 15000],
        //     ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'to_rupiah' => 16250],
        //     ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'to_rupiah' => 100],
        // ];

        // foreach ($currencies as $currencyData) {
        //     Currency::updateOrCreate(['code' => $currencyData['code']], $currencyData);
        // }

        $idr = Currency::where('code', 'IDR')->first();

        // Create Chart of Accounts
        $accounts = [
            // Assets
            ['code' => '1-1000', 'name' => 'Kas', 'type' => 'Asset', 'parent_id' => null],
            ['code' => '1-1100', 'name' => 'Bank', 'type' => 'Asset', 'parent_id' => null],
            ['code' => '1-1200', 'name' => 'Piutang Usaha', 'type' => 'Asset', 'parent_id' => null],
            ['code' => '1-1300', 'name' => 'Persediaan', 'type' => 'Asset', 'parent_id' => null, 'is_current' => 1],
            ['code' => '1-1400', 'name' => 'Penyesuaian Piutang', 'type' => 'Asset', 'parent_id' => null],
            
            // Liabilities
            ['code' => '2-1000', 'name' => 'Hutang Usaha', 'type' => 'Liability', 'parent_id' => null],
            ['code' => '2-1100', 'name' => 'Penyesuaian Hutang', 'type' => 'Liability', 'parent_id' => null],
            
            // Revenue
            ['code' => '4-1000', 'name' => 'Pendapatan Penjualan', 'type' => 'Revenue', 'parent_id' => null],
            ['code' => '4-1100', 'name' => 'Diskon Penjualan', 'type' => 'Revenue', 'parent_id' => null],
            
            // Expense
            ['code' => '5-1000', 'name' => 'Harga Pokok Penjualan', 'type' => 'Expense', 'parent_id' => null],
            ['code' => '5-1100', 'name' => 'Beban Operasional', 'type' => 'Expense', 'parent_id' => null],
        ];

        foreach ($accounts as $accountData) {
            ChartOfAccount::updateOrCreate(['code' => $accountData['code']], $accountData);
        }

        // Create warehouses
        $warehouses = [
            [
                'name' => 'Gudang Utama',
                'location' => 'Jl. Raya Industri No. 1',
                'kode' => 'GU001',
                'cabang_id' => 1,
                'tipe' => 'Besar',
                'telepon' => '021-12345678',
                'status' => true
            ],
            [
                'name' => 'Gudang Cabang A',
                'location' => 'Jl. Cabang A No. 10',
                'kode' => 'GCA001',
                'cabang_id' => 1,
                'tipe' => 'Kecil',
                'telepon' => '021-87654321',
                'status' => true
            ],
        ];

        foreach ($warehouses as $warehouseData) {
            $warehouse = Warehouse::firstOrCreate(['name' => $warehouseData['name']], $warehouseData);
            
            // Create racks for each warehouse
            $racks = [
                ['name' => 'Rak A1', 'code' => 'RAK-A1', 'description' => 'Rak bagian depan'],
                ['name' => 'Rak A2', 'code' => 'RAK-A2', 'description' => 'Rak bagian tengah'],
                ['name' => 'Rak A3', 'code' => 'RAK-A3', 'description' => 'Rak bagian belakang'],
            ];

            foreach ($racks as $rackData) {
                $rackData['warehouse_id'] = $warehouse->id;
                Rak::firstOrCreate(['name' => $rackData['name'], 'warehouse_id' => $warehouse->id], $rackData);
            }
        }

        // Create customers
        $customers = [
            [
                'name' => 'PT Maju Bersama',
                'code' => 'CUST001',
                'email' => 'info@majubersama.co.id',
                'phone' => '021-11111111',
                'telephone' => '021-11111111',
                'fax' => '021-11111112',
                'nik_npwp' => '0123456789012345',
                'address' => 'Jl. Maju No. 1, Jakarta',
                'perusahaan' => 'PT Maju Bersama',
                'tipe' => 'PKP',
                'tempo_kredit' => 30,
                'kredit_limit' => 50000000,
                'tipe_pembayaran' => 'Kredit'
            ],
            [
                'name' => 'CV Sukses Makmur',
                'code' => 'CUST002',
                'email' => 'admin@suksesmakmur.co.id',
                'phone' => '021-22222222',
                'telephone' => '021-22222222',
                'fax' => '021-22222223',
                'nik_npwp' => '1234567890123456',
                'address' => 'Jl. Sukses No. 2, Bekasi',
                'perusahaan' => 'CV Sukses Makmur',
                'tipe' => 'PKP',
                'tempo_kredit' => 15,
                'kredit_limit' => 25000000,
                'tipe_pembayaran' => 'Kredit'
            ],
            [
                'name' => 'Budi Santoso',
                'code' => 'CUST003',
                'email' => 'budi.santoso@gmail.com',
                'phone' => '081234567890',
                'telephone' => '021-33333333',
                'fax' => '021-33333334',
                'nik_npwp' => '3171234567890001',
                'address' => 'Jl. Budi No. 3, Tangerang',
                'perusahaan' => '',
                'tipe' => 'PRI',
                'tempo_kredit' => 0,
                'kredit_limit' => 5000000,
                'tipe_pembayaran' => 'COD (Bayar Lunas)'
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(['email' => $customerData['email']], $customerData);
        }

        // Create suppliers
        $suppliers = [
            [
                'name' => 'PT Supplier Utama',
                'code' => 'SUPP001',
                'email' => 'sales@supplierutama.co.id',
                'phone' => '021-33333333',
                'fax' => '021-33333334',
                'handphone' => '081234567891',
                'npwp' => '0123456789012346',
                'address' => 'Jl. Supplier No. 1, Jakarta',
                'perusahaan' => 'PT Supplier Utama',
                'tempo_hutang' => 30,
                'kontak_person' => 'Andi Wijaya'
            ],
            [
                'name' => 'CV Distributor Jaya',
                'code' => 'SUPP002',
                'email' => 'order@distributorejaya.co.id',
                'phone' => '021-44444444',
                'fax' => '021-44444445',
                'handphone' => '081234567892',
                'npwp' => '1234567890123457',
                'address' => 'Jl. Distributor No. 2, Surabaya',
                'perusahaan' => 'CV Distributor Jaya',
                'tempo_hutang' => 45,
                'kontak_person' => 'Sari Indah'
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate(['email' => $supplierData['email']], $supplierData);
        }

        // Create product categories
        $productCategories = [
            [
                'name' => 'Elektronik',
                'kode' => 'ELEC',
                'cabang_id' => 1,
                'kenaikan_harga' => 0.00,
            ],
            [
                'name' => 'Komputer & Aksesoris',
                'kode' => 'COMP',
                'cabang_id' => 1,
                'kenaikan_harga' => 0.00,
            ],
        ];

        foreach ($productCategories as $categoryData) {
            \App\Models\ProductCategory::firstOrCreate(['kode' => $categoryData['kode']], $categoryData);
        }

        // Create products
        $products = [
            [
                'sku' => 'PRD001',
                'name' => 'Laptop Asus ROG',
                'description' => 'Laptop gaming dengan spesifikasi tinggi',
                'product_category_id' => 2, // Komputer & Aksesoris
                'cabang_id' => 1,
                'cost_price' => 12000000,
                'sell_price' => 15000000,
                'uom_id' => 1, // Assuming UOM exists
                'kode_merk' => 'ASUS',
                'harga_batas' => 0,
                'item_value' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0.00,
                'jumlah_kelipatan_gudang_besar' => 0,
                'jumlah_jual_kategori_banyak' => 0,
            ],
            [
                'sku' => 'PRD002',
                'name' => 'Mouse Wireless Logitech',
                'description' => 'Mouse wireless dengan sensor presisi tinggi',
                'product_category_id' => 2, // Komputer & Aksesoris
                'cabang_id' => 1,
                'cost_price' => 500000,
                'sell_price' => 750000,
                'uom_id' => 1,
                'kode_merk' => 'LOGITECH',
                'harga_batas' => 0,
                'item_value' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0.00,
                'jumlah_kelipatan_gudang_besar' => 0,
                'jumlah_jual_kategori_banyak' => 0,
            ],
            [
                'sku' => 'PRD003',
                'name' => 'Keyboard Mechanical',
                'description' => 'Keyboard mechanical dengan blue switch',
                'product_category_id' => 2, // Komputer & Aksesoris
                'cabang_id' => 1,
                'cost_price' => 800000,
                'sell_price' => 1200000,
                'uom_id' => 1,
                'kode_merk' => 'GENERIC',
                'harga_batas' => 0,
                'item_value' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0.00,
                'jumlah_kelipatan_gudang_besar' => 0,
                'jumlah_jual_kategori_banyak' => 0,
            ],
            [
                'sku' => 'PRD004',
                'name' => 'Monitor LED 24 inch',
                'description' => 'Monitor LED full HD 24 inch',
                'product_category_id' => 2, // Komputer & Aksesoris
                'cabang_id' => 1,
                'cost_price' => 2000000,
                'sell_price' => 2800000,
                'uom_id' => 1,
                'kode_merk' => 'GENERIC',
                'harga_batas' => 0,
                'item_value' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0.00,
                'jumlah_kelipatan_gudang_besar' => 0,
                'jumlah_jual_kategori_banyak' => 0,
            ],
            [
                'sku' => 'PRD005',
                'name' => 'Printer Canon',
                'description' => 'Printer inkjet Canon untuk kantor',
                'product_category_id' => 1, // Elektronik
                'cabang_id' => 1,
                'cost_price' => 1500000,
                'sell_price' => 2200000,
                'uom_id' => 1,
                'kode_merk' => 'CANON',
                'harga_batas' => 0,
                'item_value' => 0,
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0.00,
                'jumlah_kelipatan_gudang_besar' => 0,
                'jumlah_jual_kategori_banyak' => 0,
            ],
        ];

        $warehouse = Warehouse::first();
        $rak = Rak::first();

        foreach ($products as $productData) {
            Product::firstOrCreate(['sku' => $productData['sku']], $productData);
        }

        $this->command->info('Master data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . Currency::count() . ' currencies');
        $this->command->info('- ' . ChartOfAccount::count() . ' chart of accounts');
        $this->command->info('- ' . Warehouse::count() . ' warehouses');
        $this->command->info('- ' . Rak::count() . ' racks');
        $this->command->info('- ' . Customer::count() . ' customers');
        $this->command->info('- ' . Supplier::count() . ' suppliers');
        $this->command->info('- ' . Product::count() . ' products');
        $this->command->info('- ' . InventoryStock::count() . ' inventory stocks');
    }
}
