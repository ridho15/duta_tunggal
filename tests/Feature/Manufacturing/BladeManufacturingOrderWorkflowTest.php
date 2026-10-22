<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeManufacturingOrderWorkflowTest extends TestCase
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
    protected BillOfMaterial $bom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-SPK',
            'nama' => 'Cabang Test SPK',
            'alamat' => 'Jl. Pabrik No. 2',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-SPK-01',
                'name' => 'Gudang Pabrik Utama',
                'location' => 'Lantai 1 Pabrik',
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
            ['kode' => 'CAT-SPK'],
            ['name' => 'Kategori SPK Test', 'kenaikan_harga' => 20]
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'spk_tester',
            'first_name' => 'SPK',
            'last_name' => 'Tester',
            'email' => 'spk-tester@dutatunggal.co.id',
            'name' => 'SPK Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->finishedProduct = Product::create([
            'name' => 'Semen Perekat Instan 25Kg',
            'sku' => 'PROD-SMN-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 60000,
            'sales_price' => 85000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);

        $this->rawMaterial1 = Product::create([
            'name' => 'Pasir Silika Murni',
            'sku' => 'RAW-SLK-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 2000,
            'sales_price' => 3000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);

        $this->rawMaterial2 = Product::create([
            'name' => 'Semen Portland Komposit',
            'sku' => 'RAW-PC-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 1800,
            'sales_price' => 2500,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
        ]);

        // Buat BOM standar: 1 Zak Semen Instan = 15 Kg Pasir + 10 Kg Semen PC
        $this->bom = BillOfMaterial::create([
            'code' => 'BOM-SMN-' . uniqid(),
            'nama_bom' => 'Formula Semen Instan 25Kg',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 3000,
            'overhead_cost' => 2000,
            'total_cost' => 53000,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 15, // 15 Kg per 1 Pcs
            'unit_price' => 2000,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial2->id,
            'quantity' => 10, // 10 Kg per 1 Pcs
            'unit_price' => 1800,
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('manufaktur.spk.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_spk_index_and_kpis(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Uji SPK',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 20,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-TEST-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(2),
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.spk.index'));
        $response->assertStatus(200);
        $response->assertSee('Perintah Kerja (SPK)');
        $response->assertSee($mo->mo_number);

        // Tab filter draft
        $responseDraft = $this->actingAs($this->user)->get(route('manufaktur.spk.index', ['tab' => 'draft']));
        $responseDraft->assertStatus(200);
        $responseDraft->assertSee($mo->mo_number);
    }

    public function test_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.spk.create'));
        $response->assertStatus(200);
        $response->assertSee('Terbitkan Surat Perintah Kerja (SPK) Baru');
        $response->assertSee($this->bom->nama_bom);
    }

    public function test_api_bom_details_calculates_components_and_free_stock(): void
    {
        // Isi stok di gudang:
        // Pasir Silika: 500 Kg
        // Semen PC: 50 Kg (kurang untuk produksi 10 unit = 10 * 10 = 100 Kg)
        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial1->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial2->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );

        $response = $this->actingAs($this->user)->getJson(
            route('manufaktur.spk.bom-details', [
                'billOfMaterial' => $this->bom->id,
                'quantity' => 10,
                'warehouse_id' => $this->warehouse->id,
            ])
        );

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'product_name' => $this->finishedProduct->name,
            'uom_name' => $this->uomPcs->name,
            'all_materials_sufficient' => false, // karena Semen PC hanya ada 50, butuh 100
        ]);

        $items = $response->json('items');
        $this->assertCount(2, $items);
        $this->assertEquals(150, $items[0]['required_quantity']); // 10 * 15 = 150
        $this->assertEquals(500, $items[0]['free_stock']);
        $this->assertTrue($items[0]['is_sufficient']);

        $this->assertEquals(100, $items[1]['required_quantity']); // 10 * 10 = 100
        $this->assertEquals(50, $items[1]['free_stock']);
        $this->assertFalse($items[1]['is_sufficient']);
    }

    public function test_can_store_spk_with_auto_generated_number_and_production_plan(): void
    {
        $payload = [
            'bill_of_material_id' => $this->bom->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 15,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'notes' => 'Batch pesanan toko bangunan Berkah',
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.spk.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Pastikan SPK tersimpan
        $mo = ManufacturingOrder::where('cabang_id', $this->cabang->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($mo);
        $this->assertStringStartsWith('MO-', $mo->mo_number);
        $this->assertEquals('draft', $mo->status);
        $this->assertEquals(15, $mo->quantity);

        // Pastikan ProductionPlan otomatis tersambung
        $this->assertNotNull($mo->productionPlan);
        $this->assertEquals($this->bom->id, $mo->productionPlan->bill_of_material_id);
        $this->assertEquals(15, $mo->productionPlan->quantity);
    }

    public function test_can_view_spk_show_page_with_stepper_and_material_checklist(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Detail SPK',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-SHOW-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(1),
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.spk.show', $mo));
        $response->assertStatus(200);
        $response->assertSee($mo->mo_number);
        $response->assertSee($this->finishedProduct->name);
        $response->assertSee('Kebutuhan Bahan Baku');
    }

    public function test_can_edit_and_update_draft_spk(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Edit SPK',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-EDIT-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(2),
        ]);

        $responseEdit = $this->actingAs($this->user)->get(route('manufaktur.spk.edit', $mo));
        $responseEdit->assertStatus(200);
        $responseEdit->assertSee($mo->mo_number);

        $updatePayload = [
            'quantity' => 25,
            'warehouse_id' => $this->warehouse->id,
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'notes' => 'Revisi kuantitas produksi',
        ];

        $responseUpdate = $this->actingAs($this->user)->put(route('manufaktur.spk.update', $mo), $updatePayload);
        $responseUpdate->assertRedirect(route('manufaktur.spk.show', $mo));

        $this->assertEquals(25, $mo->fresh()->quantity);
        $this->assertEquals(25, $mo->fresh()->productionPlan->quantity);
    }

    public function test_can_start_spk_when_stock_is_sufficient(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Start Sukses',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-START-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(2),
        ]);

        // Cukupi stok di gudang:
        // Butuh: 10 * 15 = 150 Kg Pasir, 10 * 10 = 100 Kg Semen PC
        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial1->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 300, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial2->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 200, 'qty_reserved' => 0]
        );

        $response = $this->actingAs($this->user)->post(route('manufaktur.spk.start', $mo));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('in_progress', $mo->fresh()->status);
        $this->assertEquals('in_progress', $mo->fresh()->productionPlan->status);
    }

    public function test_cannot_start_spk_when_stock_is_insufficient(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Start Gagal',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-START-FAIL-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(2),
        ]);

        // Stok 0 atau tidak mencukupi
        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial1->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 5, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial2->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 0, 'qty_reserved' => 0]
        );

        $response = $this->actingAs($this->user)->post(route('manufaktur.spk.start', $mo));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Status harus tetap draft
        $this->assertEquals('draft', $mo->fresh()->status);
    }

    public function test_can_cancel_draft_spk(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Batal',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 5,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-CANCEL-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(route('manufaktur.spk.cancel', $mo));
        $response->assertRedirect(route('manufaktur.spk.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('manufacturing_orders', ['id' => $mo->id]);
        $this->assertEquals('cancelled', $plan->fresh()->status);
    }

    public function test_can_stream_spk_pdf(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Cetak PDF',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-PDF-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.spk.pdf', $mo));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_can_delete_draft_spk(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Hapus',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 5,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-DEL-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.spk.destroy', $mo));
        $response->assertRedirect(route('manufaktur.spk.index'));
        $this->assertSoftDeleted('manufacturing_orders', ['id' => $mo->id]);
    }

    public function test_cannot_delete_or_edit_in_progress_spk(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana In Progress',
            'source_type' => 'manual',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 5,
            'uom_id' => $this->uomPcs->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'in_progress',
            'created_by' => $this->user->id,
        ]);

        $mo = ManufacturingOrder::create([
            'mo_number' => 'MO-IN-PROG-' . uniqid(),
            'production_plan_id' => $plan->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'in_progress',
        ]);

        // Coba edit -> redirect dengan error
        $responseEdit = $this->actingAs($this->user)->get(route('manufaktur.spk.edit', $mo));
        $responseEdit->assertRedirect(route('manufaktur.spk.show', $mo));
        $responseEdit->assertSessionHas('error');

        // Coba delete -> redirect dengan error
        $responseDelete = $this->actingAs($this->user)->delete(route('manufaktur.spk.destroy', $mo));
        $responseDelete->assertRedirect();
        $responseDelete->assertSessionHas('error');
        $this->assertDatabaseHas('manufacturing_orders', ['id' => $mo->id, 'deleted_at' => null]);
    }
}
