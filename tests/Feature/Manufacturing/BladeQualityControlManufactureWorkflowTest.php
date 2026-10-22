<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeQualityControlManufactureWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Rak $rak;
    protected UnitOfMeasure $uomPcs;
    protected ProductCategory $category;
    protected Product $finishedProduct;
    protected Product $rawMaterial;
    protected BillOfMaterial $bom;
    protected ManufacturingOrder $mo;
    protected Production $production;
    protected QualityControl $qc;
    protected ChartOfAccount $posSementaraCoa;
    protected ChartOfAccount $fgCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-QC',
            'nama' => 'Cabang Test QC',
            'alamat' => 'Jl. Mutu No. 12',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-QC-01',
                'name' => 'Gudang QC Manufaktur',
                'location' => 'Lantai 1 Pabrik',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->rak = Rak::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RAK-QC-01',
            'name' => 'Rak Barang Jadi A',
        ]);

        $this->uomPcs = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-QC'],
            ['name' => 'Kategori QC Test', 'kenaikan_harga' => 20]
        );

        $this->posSementaraCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1400.04'],
            ['name' => 'Pos Sementara Produksi (BDP)', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]
        );

        $this->fgCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.02'],
            ['name' => 'Persediaan Barang Jadi', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]
        );

        $this->finishedProduct = Product::create([
            'sku' => 'FG-QC-' . uniqid(),
            'name' => 'Pipa Galvanis 3 Inch',
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 200000,
            'sales_price' => 300000,
            'inventory_coa_id' => $this->fgCoa->id,
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->rawMaterial = Product::create([
            'sku' => 'RAW-QC-' . uniqid(),
            'name' => 'Bahan Baku Seng Galvanis',
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 80000,
            'sales_price' => 120000,
            'status' => 'active',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->bom = BillOfMaterial::create([
            'code' => 'BOM-QC-' . uniqid(),
            'nama_bom' => 'Resep Pipa Galvanis 3 Inch',
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uomPcs->id,
            'quantity' => 1,
            'biaya_tenaga_kerja' => 20000,
            'biaya_overhead' => 10000,
            'is_active' => true,
            'cabang_id' => $this->cabang->id,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 2,
            'uom_id' => $this->uomPcs->id,
            'unit_cost' => 80000,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'qc_tester',
            'first_name' => 'QC',
            'last_name' => 'Tester',
            'email' => 'qc-tester@dutatunggal.co.id',
            'name' => 'QC Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $plan = ProductionPlan::create([
            'plan_number' => 'PP-QC-TEST-' . uniqid(),
            'name' => 'Rencana Pipa Galvanis',
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
            'mo_number' => 'MO-QC-TEST-' . uniqid(),
            'production_plan_id' => $plan->id,
            'bill_of_material_id' => $this->bom->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'in_progress',
            'cabang_id' => $this->cabang->id,
        ]);

        // Siapkan Production selesai
        $randSuffix = rand(10000, 99999);
        $this->production = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . $randSuffix,
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 10,
            'production_date' => now()->toDateString(),
            'status' => 'finished',
        ]);

        // QualityControl diterbitkan otomatis oleh observer
        $this->qc = QualityControl::where('from_model_type', Production::class)
            ->where('from_model_id', $this->production->id)
            ->first() ?? QualityControl::create([
                'qc_number' => 'QC-M-' . now()->format('Ymd') . '-' . $randSuffix,
                'from_model_type' => Production::class,
                'from_model_id' => $this->production->id,
                'product_id' => $this->finishedProduct->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => $this->rak->id,
                'passed_quantity' => 10,
                'rejected_quantity' => 0,
                'status' => 0,
                'inspected_by' => $this->user->id,
                'cabang_id' => $this->cabang->id,
            ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('manufaktur.qc.index'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_qc_index_and_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.qc.index'));

        $response->assertOk();
        $response->assertViewIs('manufaktur.qc.index');
        $response->assertSee('Kontrol Kualitas Pabrik');
        $response->assertSee('Total Inspeksi');
        $response->assertSee('Belum Diproses');
        $response->assertSee('Sudah Diproses');
    }

    public function test_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.qc.create'));

        $response->assertOk();
        $response->assertViewIs('manufaktur.qc.create');
        $response->assertSee('Terbitkan Quality Control Pabrik');
    }

    public function test_api_raks_returns_warehouse_raks(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('manufaktur.qc.raks', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $this->rak->id,
            'code' => $this->rak->code,
        ]);
    }

    public function test_can_store_manual_qc(): void
    {
        $uniqueSuffix = uniqid();
        $newProd = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . $uniqueSuffix,
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 5,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $qcNumber = 'QC-M-' . now()->format('Ymd') . '-' . $uniqueSuffix;

        $payload = [
            'qc_number' => $qcNumber,
            'from_model_id' => $newProd->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $this->user->id,
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.qc.store'), $payload);

        $savedQc = QualityControl::where('qc_number', $qcNumber)->first();
        $this->assertNotNull($savedQc);
        $response->assertRedirect(route('manufaktur.qc.show', $savedQc));
        $this->assertEquals(0, $savedQc->status);
    }

    public function test_can_view_qc_show_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.qc.show', $this->qc));

        $response->assertOk();
        $response->assertViewIs('manufaktur.qc.show');
        $response->assertSee($this->qc->qc_number);
        $response->assertSee($this->finishedProduct->name);
    }

    public function test_can_view_process_qc_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.qc.process-form', $this->qc));

        $response->assertOk();
        $response->assertViewIs('manufaktur.qc.process');
        $response->assertSee($this->qc->qc_number);
        $response->assertSee('Proses Inspeksi Mutu');
    }

    public function test_validation_rejects_negative_or_over_quantity(): void
    {
        // Kasus A: Kuantitas negatif
        $responseA = $this->actingAs($this->user)->post(route('manufaktur.qc.process', $this->qc), [
            'passed_quantity' => -1,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
            'inspected_by' => $this->user->id,
        ]);
        $responseA->assertSessionHasErrors(['passed_quantity']);

        // Kasus B: Total passed + rejected melebihi target produksi (10)
        $responseB = $this->actingAs($this->user)->post(route('manufaktur.qc.process', $this->qc), [
            'passed_quantity' => 8,
            'rejected_quantity' => 5,
            'warehouse_id' => $this->warehouse->id,
            'inspected_by' => $this->user->id,
        ]);
        $responseB->assertSessionHasErrors(['passed_quantity']);
    }

    public function test_process_qc_with_passed_quantity_adds_inventory_stock_and_generates_finished_goods_journal(): void
    {
        // Berikan material issue completed agar BDP memiliki nilai biaya
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-' . now()->format('Ymd') . '-' . uniqid(),
            'manufacturing_order_id' => $this->mo->id,
            'type' => 'issue',
            'issue_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
            'approved_by' => $this->user->id,
            'approved_at' => now(),
            'cabang_id' => $this->cabang->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 20,
            'uom_id' => $this->uomPcs->id,
            'unit_price' => 80000,
            'subtotal' => 1600000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Rekam stok awal FG
        $stockBefore = InventoryStock::where('product_id', $this->finishedProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available') ?? 0;

        $response = $this->actingAs($this->user)->post(route('manufaktur.qc.process', $this->qc), [
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'inspected_by' => $this->user->id,
            'notes' => 'Inspeksi mutu 100% lulus toleransi dimensi.',
        ]);

        $response->assertRedirect(route('manufaktur.qc.show', $this->qc));
        $this->qc->refresh();

        $this->assertEquals(1, $this->qc->status);
        $this->assertEquals(10, (float) $this->qc->passed_quantity);

        // Verifikasi mutasi stok bertipe manufacture_in
        $movement = StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $this->qc->id)
            ->where('type', 'manufacture_in')
            ->first();

        $this->assertNotNull($movement, 'Mutasi stock manufacture_in wajib tercatat saat QC lolos.');
        $this->assertEquals(10, (float) $movement->quantity);
        $this->assertEquals($this->finishedProduct->id, $movement->product_id);

        // Verifikasi jurnal penyelesaian produksi (FG Journal)
        $journals = JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $this->qc->id)
            ->get();

        $this->assertTrue($journals->isNotEmpty(), 'Jurnal penyelesaian barang jadi harus terbentuk.');
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');
        $this->assertGreaterThan(0, $totalDebit);
        $this->assertEquals($totalDebit, $totalCredit, 'Jurnal penyelesaian produksi harus seimbang.');
    }

    public function test_process_qc_with_rejected_quantity_does_not_add_inventory_stock(): void
    {
        $uniqueReject = uniqid();
        $newProd = Production::create([
            'production_number' => 'PRO-' . now()->format('Ymd') . '-' . $uniqueReject,
            'manufacturing_order_id' => $this->mo->id,
            'quantity_produced' => 5,
            'production_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $qcReject = QualityControl::create([
            'qc_number' => 'QC-M-' . now()->format('Ymd') . '-' . $uniqueReject,
            'from_model_type' => Production::class,
            'from_model_id' => $newProd->id,
            'product_id' => $this->finishedProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'passed_quantity' => 0,
            'rejected_quantity' => 5,
            'status' => 0,
            'inspected_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('manufaktur.qc.process', $qcReject), [
            'passed_quantity' => 0,
            'rejected_quantity' => 5,
            'reason_reject' => 'Pipa bengkok dan lapisan galvanis mengelupas',
            'warehouse_id' => $this->warehouse->id,
            'inspected_by' => $this->user->id,
        ]);

        $response->assertRedirect(route('manufaktur.qc.show', $qcReject));
        $qcReject->refresh();
        $this->assertEquals(1, $qcReject->status);

        // Pastikan tidak ada stock movement manufacture_in untuk produk reject
        $movement = StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qcReject->id)
            ->where('type', 'manufacture_in')
            ->first();

        $this->assertNull($movement, 'Produk reject tidak boleh menghasilkan manufacture_in.');
    }

    public function test_cannot_process_already_completed_qc(): void
    {
        $this->qc->update(['status' => 1]);

        $response = $this->actingAs($this->user)->post(route('manufaktur.qc.process', $this->qc), [
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
            'inspected_by' => $this->user->id,
        ]);

        $response->assertRedirect(route('manufaktur.qc.show', $this->qc));
        $response->assertSessionHas('error');
    }

    public function test_can_stream_qc_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.qc.pdf', $this->qc));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
