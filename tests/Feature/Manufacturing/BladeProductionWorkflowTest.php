<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeProductionWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected UnitOfMeasure $uomPcs;
    protected ProductCategory $category;
    protected Product $finishedProduct;
    protected Product $rawMaterial;
    protected BillOfMaterial $bom;
    protected ManufacturingOrder $mo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-PROD',
            'nama' => 'Cabang Test Produksi',
            'alamat' => 'Jl. Pabrik No. 9',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-PROD-01',
                'name' => 'Gudang Pabrik Utama',
                'location' => 'Lantai 1 Pabrik',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->uomPcs = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-PROD'],
            ['name' => 'Kategori Produksi Test', 'kenaikan_harga' => 20]
        );

        $this->finishedProduct = Product::create([
            'sku' => 'FG-PROD-' . uniqid(),
            'name' => 'Pintu Baja Premium',
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 500000,
            'sales_price' => 750000,
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->rawMaterial = Product::create([
            'sku' => 'RAW-PROD-' . uniqid(),
            'name' => 'Plat Baja 2mm',
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 150000,
            'sales_price' => 200000,
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->bom = BillOfMaterial::create([
            'code' => 'BOM-PROD-' . uniqid(),
            'nama_bom' => 'Resep Pintu Baja Premium',
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uomPcs->id,
            'quantity' => 1,
            'biaya_tenaga_kerja' => 50000,
            'biaya_overhead' => 25000,
            'is_active' => true,
            'cabang_id' => $this->cabang->id,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 2,
            'uom_id' => $this->uomPcs->id,
            'unit_cost' => 150000,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'prod_tester',
            'first_name' => 'Prod',
            'last_name' => 'Tester',
            'email' => 'prod-tester@dutatunggal.co.id',
            'name' => 'Prod Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $plan = ProductionPlan::create([
            'plan_number' => 'PP-PROD-TEST-' . rand(1000, 9999),
            'name' => 'Rencana Pintu Baja',
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'scheduled',
            'created_by' => $this->user->id,
        ]);

        $this->mo = ManufacturingOrder::create([
            'mo_number' => 'MO-PROD-TEST-' . rand(1000, 9999),
            'production_plan_id' => $plan->id,
            'bill_of_material_id' => $this->bom->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'in_progress',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('manufaktur.production.index'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_production_index_and_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.production.index'));

        $response->assertOk();
        $response->assertViewIs('manufaktur.production.index');
        $response->assertSee('Hasil Produksi');
        $response->assertSee('Total Dokumen');
        $response->assertSee('Draft Produksi');
        $response->assertSee('Selesai (Finished)');
    }

    public function test_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.production.create', [
            'spk_id' => $this->mo->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('manufaktur.production.create');
        $response->assertSee($this->mo->mo_number);
        $response->assertSee($this->finishedProduct->name);
    }

    public function test_api_spk_details_returns_product_and_planned_quantity(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('manufaktur.production.spk-details', [
            'spk_id' => $this->mo->id,
        ]));

        $response->assertOk();
        $response->assertJson([
            'id' => $this->mo->id,
            'mo_number' => $this->mo->mo_number,
            'product_name' => $this->finishedProduct->name,
            'product_sku' => $this->finishedProduct->sku,
            'planned_quantity' => 10,
        ]);
    }

    public function test_can_store_draft_production(): void
    {
        $prodNumber = 'PRO-' . now()->format('Ymd') . '-' . uniqid();

        $payload = [
            'production_number' => $prodNumber,
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.production.store'), $payload);

        $production = Production::where('production_number', $prodNumber)->first();
        $this->assertNotNull($production);
        $response->assertRedirect(route('manufaktur.production.show', $production));
        $this->assertEquals('draft', $production->status);
        $this->assertEquals(10, (float) $production->quantity_produced);
    }

    public function test_can_store_finished_production_which_triggers_qc_creation(): void
    {
        $prodNumber = 'PRO-' . now()->format('Ymd') . '-' . uniqid();

        $payload = [
            'production_number' => $prodNumber,
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 8,
            'production_date' => now()->toDateString(),
            'status' => 'finished',
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.production.store'), $payload);

        $production = Production::where('production_number', $prodNumber)->first();
        $this->assertNotNull($production);
        $this->assertEquals('finished', $production->status);

        // Verifikasi QualityControl otomatis diterbitkan
        $qc = QualityControl::where('from_model_type', Production::class)
            ->where('from_model_id', $production->id)
            ->first();

        $this->assertNotNull($qc, 'QualityControl otomatis diterbitkan saat status finished.');
        $this->assertEquals(0, $qc->status);
        $this->assertEquals(8, (float) $qc->passed_quantity);
    }

    public function test_can_view_production_show_page(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.production.show', $production));

        $response->assertOk();
        $response->assertViewIs('manufaktur.production.show');
        $response->assertSee($production->production_number);
        $response->assertSee($this->finishedProduct->name);
    }

    public function test_can_edit_and_update_draft_production(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.production.edit', $production));
        $response->assertOk();
        $response->assertViewIs('manufaktur.production.edit');

        $updateResponse = $this->actingAs($this->user)->put(route('manufaktur.production.update', $production), [
            'production_number' => $production->production_number,
            'quantity_produced' => 12,
            'production_date' => now()->subDay()->toDateString(),
        ]);

        $updateResponse->assertRedirect(route('manufaktur.production.show', $production));
        $production->refresh();
        $this->assertEquals(12, (float) $production->quantity_produced);
    }

    public function test_can_finish_draft_production_which_triggers_qc_creation(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(route('manufaktur.production.finish', $production));

        $response->assertRedirect(route('manufaktur.production.show', $production));
        $production->refresh();
        $this->assertEquals('finished', $production->status);

        $qc = QualityControl::where('from_model_type', Production::class)
            ->where('from_model_id', $production->id)
            ->first();

        $this->assertNotNull($qc, 'QualityControl otomatis diterbitkan saat draft di-finish.');
    }

    public function test_can_stream_production_pdf(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'finished',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.production.pdf', $production));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_can_delete_draft_production(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.production.destroy', $production));

        $response->assertRedirect(route('manufaktur.production.index'));
        $this->assertSoftDeleted('productions', ['id' => $production->id]);
    }

    public function test_cannot_delete_finished_production(): void
    {
        $production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'finished',
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.production.destroy', $production));

        $response->assertRedirect(route('manufaktur.production.show', $production));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('productions', ['id' => $production->id, 'deleted_at' => null]);
    }
}
