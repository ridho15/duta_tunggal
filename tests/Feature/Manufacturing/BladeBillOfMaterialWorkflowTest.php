<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeBillOfMaterialWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected UnitOfMeasure $uomPcs;
    protected UnitOfMeasure $uomKg;
    protected ProductCategory $category;
    protected Product $finishedProduct;
    protected Product $rawMaterial1;
    protected Product $rawMaterial2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-BOM',
            'nama' => 'Cabang Test BOM',
            'alamat' => 'Jl. Industri No. 1',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-BOM-01',
                'name' => 'Gudang Bahan Baku Test',
                'location' => 'Area Produksi',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->uomPcs = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $this->uomKg = UnitOfMeasure::firstOrCreate(
            ['name' => 'Kilogram'],
            ['abbreviation' => 'Kg']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-MFG'],
            ['name' => 'Kategori Manufaktur Test', 'kenaikan_harga' => 15]
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'mfg_tester',
            'first_name' => 'Mfg',
            'last_name' => 'Tester',
            'email' => 'mfg-tester@dutatunggal.co.id',
            'name' => 'Mfg Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        // Setup test products
        $this->finishedProduct = Product::create([
            'name' => 'Cat Tembok Super White 5Kg',
            'sku' => 'PROD-FG-TEST-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 75000,
            'sales_price' => 110000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);

        $this->rawMaterial1 = Product::create([
            'name' => 'Titanium Dioxide Powder',
            'sku' => 'MAT-RAW-01-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 25000,
            'sales_price' => 30000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);

        $this->rawMaterial2 = Product::create([
            'name' => 'Emulsion Binder Liquid',
            'sku' => 'MAT-RAW-02-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 15000,
            'sales_price' => 20000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('manufaktur.bom.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_bom_index_and_kpis(): void
    {
        // Buat BOM sampel
        $bom = BillOfMaterial::create([
            'code' => 'BOM-TEST-' . uniqid(),
            'nama_bom' => 'Formula Standar Cat Tembok',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 5000,
            'overhead_cost' => 3000,
            'total_cost' => 73000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.bom.index'));
        $response->assertStatus(200);
        $response->assertSee('Resep Produksi (BOM)');
        $response->assertSee($bom->nama_bom);
        $response->assertSee($bom->code);

        // Filter tab aktif
        $responseActive = $this->actingAs($this->user)->get(route('manufaktur.bom.index', ['tab' => 'aktif']));
        $responseActive->assertStatus(200);
        $responseActive->assertSee($bom->code);

        // Filter search
        $responseSearch = $this->actingAs($this->user)->get(route('manufaktur.bom.index', ['q' => 'Formula Standar']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertSee($bom->code);
    }

    public function test_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.bom.create'));
        $response->assertStatus(200);
        $response->assertSee('Buat Resep Produksi (BOM) Baru');
        $response->assertSee('Komponen Bahan Baku');
    }

    public function test_api_generate_code_returns_standard_format(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('manufaktur.bom.generate-code'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'code']);
        $this->assertStringStartsWith('BOM-', $response->json('code'));
    }

    public function test_api_product_details_returns_uom_and_cost(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('manufaktur.bom.product-details', $this->rawMaterial1)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 25000,
        ]);
    }

    public function test_can_store_valid_bom_with_material_items(): void
    {
        $bomCode = 'BOM-TEST-' . strtoupper(uniqid());

        $payload = [
            'code' => $bomCode,
            'nama_bom' => 'Resep Master Cat Super White',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 5000,
            'overhead_cost' => 2500,
            'is_active' => '1',
            'note' => 'Formula standar laboratorium',
            'items' => [
                [
                    'product_id' => $this->rawMaterial1->id,
                    'quantity' => 2, // 2 kg * 25.000 = 50.000
                    'unit_price' => 25000,
                    'note' => 'Pigmen utama',
                ],
                [
                    'product_id' => $this->rawMaterial2->id,
                    'quantity' => 3, // 3 kg * 15.000 = 45.000
                    'unit_price' => 15000,
                    'note' => 'Binder emulsi',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.bom.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Total biaya: 50.000 + 45.000 + 5.000 + 2.500 = 102.500
        $this->assertDatabaseHas('bill_of_materials', [
            'code' => $bomCode,
            'nama_bom' => 'Resep Master Cat Super White',
            'product_id' => $this->finishedProduct->id,
            'total_cost' => 102500,
            'is_active' => 1,
        ]);

        $createdBom = BillOfMaterial::where('code', $bomCode)->first();
        $this->assertNotNull($createdBom);
        $this->assertCount(2, $createdBom->items);
    }

    public function test_store_validation_rejects_missing_items(): void
    {
        $payload = [
            'code' => 'BOM-INVALID-' . uniqid(),
            'nama_bom' => 'Resep Tanpa Bahan',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'items' => [], // Kosong
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.bom.store'), $payload);
        $response->assertSessionHasErrors(['items']);
    }

    public function test_can_view_bom_details(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-VIEW-' . uniqid(),
            'nama_bom' => 'Resep Detail Uji',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 4000,
            'overhead_cost' => 2000,
            'total_cost' => 56000,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 2,
            'unit_price' => 25000,
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.bom.show', $bom));
        $response->assertStatus(200);
        $response->assertSee('Resep Detail Uji');
        $response->assertSee($bom->code);
        $response->assertSee($this->rawMaterial1->name);
    }

    public function test_can_view_edit_page(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-EDIT-' . uniqid(),
            'nama_bom' => 'Resep Sebelum Edit',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 1000,
            'overhead_cost' => 1000,
            'total_cost' => 27000,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 1,
            'unit_price' => 25000,
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.bom.edit', $bom));
        $response->assertStatus(200);
        $response->assertSee('Resep Sebelum Edit');
        $response->assertSee($bom->code);
    }

    public function test_can_update_bom(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-UPDATE-' . uniqid(),
            'nama_bom' => 'Resep Awal',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 2000,
            'overhead_cost' => 1000,
            'total_cost' => 28000,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 1,
            'unit_price' => 25000,
        ]);

        $payload = [
            'code' => $bom->code,
            'nama_bom' => 'Resep Setelah Diperbarui',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 6000,
            'overhead_cost' => 4000,
            'is_active' => '1',
            'items' => [
                [
                    'product_id' => $this->rawMaterial2->id,
                    'quantity' => 4, // 4 * 15.000 = 60.000
                    'unit_price' => 15000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('manufaktur.bom.update', $bom), $payload);
        $response->assertRedirect(route('manufaktur.bom.show', $bom));

        // Total: 60.000 + 6.000 + 4.000 = 70.000
        $this->assertDatabaseHas('bill_of_materials', [
            'id' => $bom->id,
            'nama_bom' => 'Resep Setelah Diperbarui',
            'total_cost' => 70000,
        ]);

        $this->assertDatabaseHas('bill_of_material_items', [
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->rawMaterial2->id,
            'quantity' => 4,
        ]);
    }

    public function test_can_toggle_active_status(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-TOGGLE-' . uniqid(),
            'nama_bom' => 'Resep Toggle Status',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'is_active' => true,
        ]);

        // Toggle ke non-aktif
        $response = $this->actingAs($this->user)->post(route('manufaktur.bom.toggle-active', $bom));
        $response->assertRedirect();
        $this->assertFalse((bool) $bom->fresh()->is_active);

        // Toggle kembali ke aktif
        $this->actingAs($this->user)->post(route('manufaktur.bom.toggle-active', $bom));
        $this->assertTrue((bool) $bom->fresh()->is_active);
    }

    public function test_can_stream_bom_pdf(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-PDF-' . uniqid(),
            'nama_bom' => 'Resep Cetak PDF',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 5000,
            'overhead_cost' => 3000,
            'total_cost' => 33000,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 1,
            'unit_price' => 25000,
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.bom.pdf', $bom));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_can_delete_unused_bom(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-DEL-' . uniqid(),
            'nama_bom' => 'Resep Hapus',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.bom.destroy', $bom));
        $response->assertRedirect(route('manufaktur.bom.index'));
        $this->assertSoftDeleted('bill_of_materials', ['id' => $bom->id]);
    }

    public function test_cannot_delete_bom_in_use(): void
    {
        $bom = BillOfMaterial::create([
            'code' => 'BOM-IN-USE-' . uniqid(),
            'nama_bom' => 'Resep Sedang Dipakai',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'is_active' => true,
        ]);

        // Buat ProductionPlan yang menggunakan BOM ini dengan status scheduled
        ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Produksi Aktif',
            'source_type' => 'manual',
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 50,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'start_date' => \Carbon\Carbon::now(),
            'end_date' => \Carbon\Carbon::now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.bom.destroy', $bom));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bill_of_materials', ['id' => $bom->id, 'deleted_at' => null]);
    }
}
