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
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeMaterialIssueWorkflowTest extends TestCase
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
    protected ManufacturingOrder $mo;
    protected ChartOfAccount $posSementaraCoa;
    protected ChartOfAccount $rawCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-MI',
            'nama' => 'Cabang Test Material Issue',
            'alamat' => 'Jl. Pabrik No. 5',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-MI-01',
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
            ['kode' => 'CAT-MI'],
            ['name' => 'Kategori MI Test', 'kenaikan_harga' => 20]
        );

        // Siapkan COA Akuntansi untuk Pos Sementara Produksi & Persediaan Bahan Baku
        $this->posSementaraCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1400.04'],
            ['name' => 'Pos Sementara Produksi (BDP)', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]
        );

        $this->rawCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1-101'],
            ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'mi_tester',
            'first_name' => 'MI',
            'last_name' => 'Tester',
            'email' => 'mi-tester@dutatunggal.co.id',
            'name' => 'MI Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->finishedProduct = Product::create([
            'name' => 'Paving Block Premium',
            'sku' => 'PROD-PVG-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomPcs->id,
            'cost_price' => 50000,
            'sales_price' => 75000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
            'is_manufacture' => true,
        ]);

        $this->rawMaterial1 = Product::create([
            'name' => 'Pasir Cor Pilihan',
            'sku' => 'RAW-PSR-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 1500,
            'sales_price' => 2000,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
            'is_raw_material' => true,
            'inventory_coa_id' => $this->rawCoa->id,
        ]);

        $this->rawMaterial2 = Product::create([
            'name' => 'Semen Portland Gresik',
            'sku' => 'RAW-SMN-' . uniqid(),
            'kode_merk' => 'TEST',
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uomKg->id,
            'cost_price' => 2000,
            'sales_price' => 2500,
            'cabang_id' => $this->cabang->id,
            'status' => 'active',
            'is_raw_material' => true,
            'inventory_coa_id' => $this->rawCoa->id,
        ]);

        // Stok fisik awal di gudang: Pasir 500 Kg, Semen 300 Kg
        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial1->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->rawMaterial2->id, 'warehouse_id' => $this->warehouse->id],
            ['qty_available' => 300, 'qty_reserved' => 0]
        );

        // Buat BOM & SPK
        $this->bom = BillOfMaterial::create([
            'code' => 'BOM-PVG-' . uniqid(),
            'nama_bom' => 'Resep Paving Block',
            'product_id' => $this->finishedProduct->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'uom_id' => $this->uomPcs->id,
            'labor_cost' => 5000,
            'overhead_cost' => 3000,
            'total_cost' => 45500,
            'is_active' => true,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial1->id,
            'quantity' => 15, // 15 Kg Pasir per pcs
            'unit_price' => 1500,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $this->bom->id,
            'product_id' => $this->rawMaterial2->id,
            'quantity' => 10, // 10 Kg Semen per pcs
            'unit_price' => 2000,
        ]);

        $productionPlan = ProductionPlan::create([
            'plan_number' => 'PLAN-' . uniqid(),
            'name' => 'Rencana Paving Block 10 Pcs',
            'product_id' => $this->finishedProduct->id,
            'bill_of_material_id' => $this->bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'uom_id' => $this->uomPcs->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'scheduled',
            'created_by' => $this->user->id,
        ]);

        $this->mo = ManufacturingOrder::create([
            'mo_number' => 'SPK-PVG-' . uniqid(),
            'production_plan_id' => $productionPlan->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('manufaktur.material-issue.index'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_material_issue_index_and_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.material-issue.index'));

        $response->assertOk();
        $response->assertViewIs('manufaktur.material_issue.index');
        $response->assertSee('Pengeluaran Bahan Baku (Material Issue & Return)');
        $response->assertSee('Total Transaksi');
        $response->assertSee('Menunggu Gudang');
        $response->assertSee('Nilai BDP Bahan');
    }

    public function test_can_view_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('manufaktur.material-issue.create', [
            'spk_id' => $this->mo->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('manufaktur.material_issue.create');
        $response->assertSee($this->mo->mo_number);
        $response->assertSee('Catat Pengeluaran / Retur Bahan Baku');
    }

    public function test_api_spk_materials_returns_bom_items_and_free_stock(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('manufaktur.material-issue.spk-materials', $this->mo)
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'mo_id',
            'mo_number',
            'items' => [
                '*' => [
                    'product_id',
                    'product_name',
                    'quantity',
                    'cost_per_unit',
                    'free_stock',
                    'is_stock_sufficient',
                ],
            ],
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['items']);
        // 10 Pcs target * 15 Kg = 150 Kg pasir
        $this->assertEquals(150, $data['items'][0]['quantity']);
        $this->assertTrue($data['items'][0]['is_stock_sufficient']);
    }

    public function test_api_product_details_returns_pricing_and_stock(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('manufaktur.material-issue.product-details', [
                'product' => $this->rawMaterial1->id,
                'warehouse_id' => $this->warehouse->id,
            ])
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'id' => $this->rawMaterial1->id,
            'cost_price' => 1500,
            'free_stock' => 500,
        ]);
    }

    public function test_can_store_material_issue_with_auto_generated_number(): void
    {
        $payload = [
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'notes' => 'Pengeluaran bahan batch 1',
            'items' => [
                [
                    'product_id' => $this->rawMaterial1->id,
                    'uom_id' => $this->uomKg->id,
                    'quantity' => 150,
                    'cost_per_unit' => 1500,
                ],
                [
                    'product_id' => $this->rawMaterial2->id,
                    'uom_id' => $this->uomKg->id,
                    'quantity' => 100,
                    'cost_per_unit' => 2000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.material-issue.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('material_issues', [
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'total_cost' => (150 * 1500) + (100 * 2000), // 225.000 + 200.000 = 425.000
        ]);

        $issue = MaterialIssue::where('manufacturing_order_id', $this->mo->id)->first();
        $this->assertNotNull($issue);
        $this->assertStringStartsWith('MI-', $issue->issue_number);
        $this->assertCount(2, $issue->items);
    }

    public function test_can_store_material_return_with_auto_generated_number(): void
    {
        $payload = [
            'type' => 'return',
            'manufacturing_order_id' => $this->mo->id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'notes' => 'Retur sisa pasir 20 Kg',
            'items' => [
                [
                    'product_id' => $this->rawMaterial1->id,
                    'uom_id' => $this->uomKg->id,
                    'quantity' => 20,
                    'cost_per_unit' => 1500,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('manufaktur.material-issue.store'), $payload);

        $response->assertRedirect();

        $issue = MaterialIssue::where('type', 'return')->latest('id')->first();
        $this->assertNotNull($issue);
        $this->assertStringStartsWith('MR-', $issue->issue_number);
        $this->assertEquals(30000, $issue->total_cost);
    }

    public function test_can_view_material_issue_show_page_with_stock_status(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-TEST-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 150000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 100,
            'cost_per_unit' => 1500,
            'total_cost' => 150000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.material-issue.show', $issue));

        $response->assertOk();
        $response->assertViewIs('manufaktur.material_issue.show');
        $response->assertSee($issue->issue_number);
        $response->assertSee($this->rawMaterial1->name);
        $response->assertSee('Stok Tersedia');
    }

    public function test_can_edit_and_update_draft_material_issue(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-DRAFT-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 150000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 100,
            'cost_per_unit' => 1500,
            'total_cost' => 150000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.material-issue.edit', $issue));
        $response->assertOk();

        // Update kuantitas menjadi 120 Kg
        $updatePayload = [
            'type' => 'issue',
            'issue_number' => $issue->issue_number,
            'manufacturing_order_id' => $this->mo->id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'notes' => 'Diperbarui',
            'items' => [
                [
                    'product_id' => $this->rawMaterial1->id,
                    'uom_id' => $this->uomKg->id,
                    'quantity' => 120,
                    'cost_per_unit' => 1500,
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->user)->put(
            route('manufaktur.material-issue.update', $issue),
            $updatePayload
        );

        $updateResponse->assertRedirect(route('manufaktur.material-issue.show', $issue));

        $issue->refresh();
        $this->assertEquals(180000, $issue->total_cost); // 120 * 1500
        $this->assertEquals('Diperbarui', $issue->notes);
    }

    public function test_can_submit_approval_when_stock_is_sufficient(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-SUBMIT-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 150000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 50, // Stok tersedia 500
            'cost_per_unit' => 1500,
            'total_cost' => 75000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('manufaktur.material-issue.submit-approval', $issue)
        );

        $response->assertRedirect(route('manufaktur.material-issue.show', $issue));

        $issue->refresh();
        $this->assertEquals('pending_approval', $issue->status);
        $this->assertTrue($issue->warehouseConfirmations()->exists());
    }

    public function test_cannot_submit_approval_when_stock_is_insufficient(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-OVER-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 1500000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 9999, // Melebihi stok 500
            'cost_per_unit' => 1500,
            'total_cost' => 14998500,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('manufaktur.material-issue.submit-approval', $issue)
        );

        $response->assertSessionHas('error');

        $issue->refresh();
        $this->assertEquals('draft', $issue->status);
    }

    public function test_can_confirm_warehouse_and_complete_material_issue_generating_wip_journal(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-CONFIRM-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 150000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 100, // dari 500
            'cost_per_unit' => 1500,
            'total_cost' => 150000,
            'status' => 'draft',
        ]);

        // Submit approval
        $this->actingAs($this->user)->post(route('manufaktur.material-issue.submit-approval', $issue));

        // Confirm warehouse
        $response = $this->actingAs($this->user)->post(
            route('manufaktur.material-issue.confirm-warehouse', $issue)
        );

        $response->assertRedirect(route('manufaktur.material-issue.show', $issue));

        $issue->refresh();
        // Ketika seluruh konfirmasi gudang confirmed, sistem otomatis menyelesaikan issue dan menjurnal BDP!
        $this->assertTrue(in_array($issue->status, ['approved', 'completed']));

        // Jika status masih approved, panggil complete
        if ($issue->status === 'approved') {
            $this->actingAs($this->user)->post(route('manufaktur.material-issue.complete', $issue));
            $issue->refresh();
        }

        $this->assertEquals('completed', $issue->status);

        // Verifikasi Jurnal BDP (1400.04 Debit, 1-101 Kredit)
        $journals = JournalEntry::where('source_type', MaterialIssue::class)
            ->where('source_id', $issue->id)
            ->get();

        $this->assertNotEmpty($journals);
        $debitEntry = $journals->firstWhere('debit', '>', 0);
        $this->assertNotNull($debitEntry);
        $this->assertEquals($this->posSementaraCoa->id, $debitEntry->coa_id);
        $this->assertEquals(150000, (float) $debitEntry->debit);

        $creditEntry = $journals->firstWhere('credit', '>', 0);
        $this->assertNotNull($creditEntry);
        $this->assertEquals($this->rawCoa->id, $creditEntry->coa_id);
        $this->assertEquals(150000, (float) $creditEntry->credit);
    }

    public function test_can_complete_material_return_generating_reversed_wip_journal(): void
    {
        $returnIssue = MaterialIssue::create([
            'issue_number' => 'MR-RETURN-' . uniqid(),
            'type' => 'return',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 30000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $returnIssue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 20,
            'cost_per_unit' => 1500,
            'total_cost' => 30000,
            'status' => 'draft',
        ]);

        // Submit & Confirm
        $this->actingAs($this->user)->post(route('manufaktur.material-issue.submit-approval', $returnIssue));
        $this->actingAs($this->user)->post(route('manufaktur.material-issue.confirm-warehouse', $returnIssue));

        $returnIssue->refresh();
        if ($returnIssue->status === 'approved') {
            $this->actingAs($this->user)->post(route('manufaktur.material-issue.complete', $returnIssue));
            $returnIssue->refresh();
        }

        $this->assertEquals('completed', $returnIssue->status);

        // Verifikasi Jurnal Retur BDP (Persediaan Debit, 1400.04 Kredit)
        $journals = JournalEntry::where('source_type', MaterialIssue::class)
            ->where('source_id', $returnIssue->id)
            ->get();

        $this->assertNotEmpty($journals);

        $debitEntry = $journals->firstWhere('debit', '>', 0);
        $this->assertEquals($this->rawCoa->id, $debitEntry->coa_id);
        $this->assertEquals(30000, (float) $debitEntry->debit);

        $creditEntry = $journals->firstWhere('credit', '>', 0);
        $this->assertEquals($this->posSementaraCoa->id, $creditEntry->coa_id);
        $this->assertEquals(30000, (float) $creditEntry->credit);
    }

    public function test_can_reject_material_issue_with_reason(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-REJ-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'pending_approval',
            'total_cost' => 50000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('manufaktur.material-issue.reject', $issue), [
            'rejection_reason' => 'Bahan baku karung basah terkena hujan',
        ]);

        $response->assertRedirect(route('manufaktur.material-issue.show', $issue));

        $issue->refresh();
        $this->assertEquals('rejected', $issue->status);
        $this->assertStringContainsString('Bahan baku karung basah terkena hujan', $issue->notes);
    }

    public function test_can_stream_material_issue_pdf(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-PDF-' . uniqid(),
            'type' => 'issue',
            'manufacturing_order_id' => $this->mo->id,
            'production_plan_id' => $this->mo->production_plan_id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 50000,
            'created_by' => $this->user->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $issue->id,
            'product_id' => $this->rawMaterial1->id,
            'uom_id' => $this->uomKg->id,
            'quantity' => 10,
            'cost_per_unit' => 1500,
            'total_cost' => 15000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('manufaktur.material-issue.pdf', $issue));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_can_delete_draft_material_issue(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-DEL-' . uniqid(),
            'type' => 'issue',
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 50000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('manufaktur.material-issue.destroy', $issue));

        $response->assertRedirect(route('manufaktur.material-issue.index'));
        $this->assertSoftDeleted('material_issues', ['id' => $issue->id]);
    }

    public function test_cannot_edit_or_delete_non_draft_material_issue(): void
    {
        $issue = MaterialIssue::create([
            'issue_number' => 'MI-NON-DRAFT-' . uniqid(),
            'type' => 'issue',
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'completed',
            'total_cost' => 50000,
            'created_by' => $this->user->id,
        ]);

        // Attempt edit
        $editResponse = $this->actingAs($this->user)->get(route('manufaktur.material-issue.edit', $issue));
        $editResponse->assertRedirect(route('manufaktur.material-issue.show', $issue));

        // Attempt delete
        $deleteResponse = $this->actingAs($this->user)->delete(route('manufaktur.material-issue.destroy', $issue));
        $deleteResponse->assertRedirect(route('manufaktur.material-issue.show', $issue));
        $deleteResponse->assertSessionHas('error');

        $this->assertDatabaseHas('material_issues', [
            'id' => $issue->id,
            'deleted_at' => null,
        ]);
    }
}
