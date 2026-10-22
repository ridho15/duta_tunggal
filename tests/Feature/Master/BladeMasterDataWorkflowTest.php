<?php

namespace Tests\Feature\Master;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeMasterDataWorkflowTest extends TestCase
{
    protected User $superAdmin;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected ProductCategory $category;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-MD',
            'nama' => 'Cabang Test Master Data',
            'alamat' => 'Jl. Master No. 1',
            'status' => true,
        ]);

        $this->category = ProductCategory::withoutGlobalScopes()->where('name', 'Kategori Test MD')->first()
            ?? ProductCategory::create([
                'kode' => 'CAT-TEST-MD',
                'name' => 'Kategori Test MD',
                'kenaikan_harga' => 10,
            ]);

        $this->uom = UnitOfMeasure::withoutGlobalScopes()->where('name', 'Pcs Test MD')->first()
            ?? UnitOfMeasure::create([
                'name' => 'Pcs Test MD',
                'abbreviation' => 'Pcs',
            ]);

        \App\Models\ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            [
                'name' => 'Persediaan Barang Dagangan Test',
                'type' => 'Asset',
                'is_active' => true,
            ]
        );

        $superRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $driverRole = Role::firstOrCreate(['name' => 'Delivery Driver', 'guard_name' => 'web']);

        $this->superAdmin = User::withoutGlobalScopes()->where('email', 'admin.mastertest@dutatunggal.co.id')->first()
            ?? User::create([
                'username' => 'admin_mastertest',
                'first_name' => 'Admin',
                'last_name' => 'Master Test',
                'email' => 'admin.mastertest@dutatunggal.co.id',
                'name' => 'Admin Master Test',
                'password' => bcrypt('password123'),
                'cabang_id' => $this->cabang->id,
                'manage_type' => ['all'],
            ]);

        if (! $this->superAdmin->hasRole('Super Admin')) {
            $this->superAdmin->assignRole($superRole);
        }

        $this->unauthorizedUser = User::withoutGlobalScopes()->where('email', 'driver.mastertest@dutatunggal.co.id')->first()
            ?? User::create([
                'username' => 'driver_mastertest',
                'first_name' => 'Driver',
                'last_name' => 'Master Test',
                'email' => 'driver.mastertest@dutatunggal.co.id',
                'name' => 'Driver Master Test',
                'password' => bcrypt('password123'),
                'cabang_id' => $this->cabang->id,
                'manage_type' => [],
            ]);

        if (! $this->unauthorizedUser->hasRole('Delivery Driver')) {
            $this->unauthorizedUser->syncRoles([$driverRole]);
        }
    }

    public function test_super_admin_can_access_all_master_indexes(): void
    {
        $responseProducts = $this->actingAs($this->superAdmin)->get(route('master.products.index'));
        $responseProducts->assertOk();
        $responseProducts->assertSeeText('Katalog Master Produk');

        $responseCustomers = $this->actingAs($this->superAdmin)->get(route('master.customers.index'));
        $responseCustomers->assertOk();
        $responseCustomers->assertSeeText('Data Pelanggan (Customer)');

        $responseSuppliers = $this->actingAs($this->superAdmin)->get(route('master.suppliers.index'));
        $responseSuppliers->assertOk();
        $responseSuppliers->assertSeeText('Data Pemasok (Supplier)');
    }

    public function test_unauthorized_user_receives_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('master.products.index'));
        $response->assertForbidden();

        $responseSupplier = $this->actingAs($this->unauthorizedUser)->get(route('master.suppliers.index'));
        $responseSupplier->assertForbidden();
    }

    public function test_can_create_new_product_with_sku_and_auto_coa(): void
    {
        $sku = 'TEST-SKU-' . uniqid();
        $postData = [
            'sku' => $sku,
            'name' => 'Baut Baja Hexagonal M12',
            'product_category_id' => $this->category->id,
            'cabang_id' => $this->cabang->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'BOSCH-TEST',
            'cost_price' => 15000,
            'sell_price' => 25000,
            'biaya' => 1000,
            'harga_batas' => 10, // maks 10% diskon
            'tipe_pajak' => 'Inklusif',
            'pajak' => 11,
            'is_active' => '1',
            'description' => 'Produk testing create master',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('master.products.store'), $postData);

        $response->assertRedirect(route('master.products.index'));
        $response->assertSessionHas('success');

        $product = Product::withoutGlobalScopes()->where('sku', $sku)->first();
        $this->assertNotNull($product);
        $this->assertEquals('Baut Baja Hexagonal M12', $product->name);
        $this->assertTrue((bool) $product->is_active);
        $this->assertEquals(15000, (float) $product->cost_price);
        $this->assertEquals(25000, (float) $product->sell_price);
        $this->assertNotNull($product->inventory_coa_id, 'Inventory COA ID harus terisi otomatis via Observer');
    }

    public function test_product_validation_prevents_duplicate_sku(): void
    {
        $sku = 'TEST-DUPLICATE-' . uniqid();

        Product::create([
            'sku' => $sku,
            'name' => 'Produk Awal',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK',
            'cost_price' => 10000,
            'sell_price' => 15000,
            'tipe_pajak' => 'Inklusif',
            'is_active' => true,
        ]);

        $postData = [
            'sku' => $sku,
            'name' => 'Produk Duplikat',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK',
            'cost_price' => 10000,
            'sell_price' => 15000,
            'tipe_pajak' => 'Inklusif',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('master.products.store'), $postData);
        $response->assertSessionHasErrors('sku');
    }

    public function test_can_quick_toggle_product_active_status(): void
    {
        $product = Product::create([
            'sku' => 'TOGGLE-' . uniqid(),
            'name' => 'Produk Toggle Status',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK',
            'cost_price' => 10000,
            'sell_price' => 15000,
            'tipe_pajak' => 'Inklusif',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('master.products.toggle-active', $product));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_active' => false,
        ]);

        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
    }

    public function test_can_update_product_and_view_detail(): void
    {
        $product = Product::create([
            'sku' => 'UPDATE-' . uniqid(),
            'name' => 'Produk Update Asli',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK-ORI',
            'cost_price' => 20000,
            'sell_price' => 30000,
            'tipe_pajak' => 'Inklusif',
            'is_active' => true,
        ]);

        $updateData = [
            'sku' => $product->sku,
            'name' => 'Produk Update Dimodifikasi',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK-MOD',
            'cost_price' => 22000,
            'sell_price' => 35000,
            'tipe_pajak' => 'Inklusif',
            'is_active' => '1',
            'unit_conversions' => [
                [
                    'uom_id' => $this->uom->id,
                    'nilai_konversi' => 12,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin)->put(route('master.products.update', $product), $updateData);
        $response->assertRedirect(route('master.products.index'));

        $product->refresh();
        $this->assertEquals('Produk Update Dimodifikasi', $product->name);
        $this->assertEquals(35000, (float) $product->sell_price);
        $this->assertCount(1, $product->unitConversions);

        // View detail page
        $showResponse = $this->actingAs($this->superAdmin)->get(route('master.products.show', $product));
        $showResponse->assertOk();
        $showResponse->assertSeeText('Produk Update Dimodifikasi');
    }

    public function test_can_create_customer_with_credit_limit(): void
    {
        $code = 'CUST-TEST-' . uniqid();
        $postData = [
            'code' => $code,
            'name' => 'Bpk. Ahmad Fauzi Test',
            'perusahaan' => 'CV Makmur Sejahtera Test',
            'nik_npwp' => '3271012345678901',
            'cabang_id' => $this->cabang->id,
            'address' => 'Jl. Merdeka No. 45 Bandung',
            'telephone' => '022-777888',
            'phone' => '081399887766',
            'email' => 'ahmad.fauzi@example.com',
            'fax' => '022-777889',
            'tipe' => 'PKP',
            'tipe_pembayaran' => 'Kredit',
            'tempo_kredit' => 45,
            'kredit_limit' => 25000000,
            'isSpecial' => '1',
            'keterangan' => 'Pelanggan utama proyek konstruksi',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('master.customers.store'), $postData);

        $response->assertRedirect(route('master.customers.index'));
        $response->assertSessionHas('success');

        $customer = Customer::withoutGlobalScopes()->where('code', $code)->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Bpk. Ahmad Fauzi Test', $customer->name);
        $this->assertEquals(25000000, (float) $customer->kredit_limit);
        $this->assertEquals(45, (int) $customer->tempo_kredit);

        // Show page
        $showResponse = $this->actingAs($this->superAdmin)->get(route('master.customers.show', $customer));
        $showResponse->assertOk();
        $showResponse->assertSeeText('Bpk. Ahmad Fauzi Test');
    }

    public function test_customer_duplicate_check_endpoint_detects_matches(): void
    {
        Customer::create([
            'code' => 'CUST-DUP-' . uniqid(),
            'name' => 'PT Dayamega Teknik Medika',
            'perusahaan' => 'PT Dayamega Teknik Medika',
            'nik_npwp' => '0123456789123456',
            'cabang_id' => $this->cabang->id,
            'address' => 'Jakarta Barat',
            'telephone' => '021-998877',
            'phone' => '081233445566',
            'email' => 'dayamega@example.com',
            'fax' => '-',
            'tipe' => 'PKP',
            'tipe_pembayaran' => 'Kredit',
            'tempo_kredit' => 30,
            'kredit_limit' => 50000000,
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson(route('master.customers.check-duplicates', [
            'name' => 'Dayamega Teknik',
            'perusahaan' => 'Dayamega',
        ]));

        $response->assertOk();
        $response->assertJson([
            'has_duplicates' => true,
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('count'));
    }

    public function test_can_create_supplier_with_bank_account_and_top(): void
    {
        $code = 'SUP-TEST-' . uniqid();
        $postData = [
            'code' => $code,
            'perusahaan' => 'PT Surya Baja Sentosa',
            'kontak_person' => 'Ibu Maya',
            'npwp' => '02.456.789.1-012.000',
            'cabang_id' => $this->cabang->id,
            'address' => 'Kawasan Industri MM2100 Cikarang',
            'phone' => '021-8989123',
            'handphone' => '081122334455',
            'email' => 'sales@suryabaja.co.id',
            'fax' => '021-8989124',
            'tempo_hutang' => 60,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '7112233445',
            'nama_rekening' => 'PT Surya Baja Sentosa',
            'keterangan' => 'Pemasok utama plat dan profil baja',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('master.suppliers.store'), $postData);

        $response->assertRedirect(route('master.suppliers.index'));
        $response->assertSessionHas('success');

        $supplier = Supplier::withoutGlobalScopes()->where('code', $code)->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('PT Surya Baja Sentosa', $supplier->perusahaan);
        $this->assertEquals('BCA', $supplier->nama_bank);
        $this->assertEquals('7112233445', $supplier->nomor_rekening);
        $this->assertEquals(60, (int) $supplier->tempo_hutang);

        // Show page
        $showResponse = $this->actingAs($this->superAdmin)->get(route('master.suppliers.show', $supplier));
        $showResponse->assertOk();
        $showResponse->assertSeeText('7112233445');
        $showResponse->assertSeeText('BCA');
    }

    public function test_supplier_duplicate_check_endpoint(): void
    {
        Supplier::create([
            'code' => 'SUP-DUP-' . uniqid(),
            'perusahaan' => 'PT Krakatau Mitra Steel',
            'kontak_person' => 'Bpk. Ridwan',
            'npwp' => '01.999.888.7-654.000',
            'cabang_id' => $this->cabang->id,
            'address' => 'Cilegon',
            'phone' => '0254-123456',
            'handphone' => '081298765432',
            'email' => 'info@krakataumitra.co.id',
            'fax' => '-',
            'tempo_hutang' => 30,
            'nama_bank' => 'Mandiri',
            'nomor_rekening' => '1230009876543',
            'nama_rekening' => 'PT Krakatau Mitra Steel',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson(route('master.suppliers.check-duplicates', [
            'perusahaan' => 'Krakatau Mitra',
        ]));

        $response->assertOk();
        $response->assertJson([
            'has_duplicates' => true,
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('count'));
    }
}
