<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture context untuk pengujian modul manufaktur / produksi (Tahap 4).
 */
function mfgContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'MFG-' . strtoupper(substr(uniqid(), -5)),
        'nama' => 'Cabang Manufaktur & Pabrik',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'username' => 'mfg_' . uniqid(),
        'email' => 'mfg_' . uniqid() . '@example.com',
        'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
        'manage_type' => 'all',
    ]);
    Auth::login($user);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'kode' => 'G-MFG-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Gudang Pabrik Utama',
        'status' => 1,
    ]);

    $rak = Rak::factory()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Rak Baku & Jadi',
    ]);

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pcs', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['name' => 'Kategori Manufaktur']);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    $rawCoa = $coa('1-101', 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY', 'Asset');
    $posSementaraCoa = $coa('1400.04', 'POS SEMENTARA PRODUKSI', 'Asset');
    $wipInventoryCoa = $coa('1-201', 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY', 'Asset');
    $laborCoa = $coa('5230', 'BIAYA TENAGA KERJA PROSES PRODUKSI', 'Expense');
    $fgCoa = $coa('1140.02', 'Persediaan Barang Produksi', 'Asset');
    $bdpCoa = $coa('1150', 'Barang Dalam Proses', 'Asset');
    $bankCoa = $coa('1112.01', 'Kas dan Bank Operasional', 'Asset');

    // Bahan Baku (Raw Material)
    $rawMaterial = Product::factory()->create([
        'sku' => 'RM-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Bahan Baku Plat Tembaga',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 20000,
        'sell_price' => 25000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    // Stok awal bahan baku di gudang pabrik (updateOrCreate untuk menimpa inisialisasi Product::created)
    $rawStock = InventoryStock::updateOrCreate(
        [
            'product_id' => $rawMaterial->id,
            'warehouse_id' => $warehouse->id,
        ],
        [
            'rak_id' => $rak->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]
    );

    // Produk Jadi (Finished Good)
    $finishedGood = Product::factory()->create([
        'sku' => 'FG-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Produk Jadi Fitting Tee',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
        'sell_price' => 85000,
        'inventory_coa_id' => $fgCoa->id,
    ]);

    // Stok awal produk jadi di gudang (0)
    $fgStock = InventoryStock::updateOrCreate(
        [
            'product_id' => $finishedGood->id,
            'warehouse_id' => $warehouse->id,
        ],
        [
            'rak_id' => $rak->id,
            'qty_available' => 0,
            'qty_reserved' => 0,
        ]
    );

    return array_merge(compact(
        'cabang', 'user', 'warehouse', 'rak', 'uom', 'category',
        'rawCoa', 'posSementaraCoa', 'wipInventoryCoa', 'laborCoa', 'fgCoa', 'bdpCoa', 'bankCoa',
        'rawMaterial', 'rawStock', 'finishedGood', 'fgStock'
    ), $overrides);
}

/**
 * Buat BillOfMaterial (BOM) dengan item bahan baku.
 */
function mfgBom(array $ctx, float $qty = 1, float $rawPerUnit = 2, float $labor = 5000, float $overhead = 3000, array $attributes = []): array
{
    $rawCost = (float) ($ctx['rawMaterial']->cost_price ?? 20000);
    $totalRaw = round($rawPerUnit * $rawCost, 2);
    $totalCost = round($totalRaw + $labor + $overhead, 2);

    $bom = BillOfMaterial::create(array_merge([
        'cabang_id' => $ctx['cabang']->id,
        'product_id' => $ctx['finishedGood']->id,
        'quantity' => $qty,
        'code' => 'BOM-' . strtoupper(substr(uniqid(), -6)),
        'nama_bom' => 'BOM Standar ' . $ctx['finishedGood']->name,
        'uom_id' => $ctx['uom']->id,
        'labor_cost' => $labor,
        'overhead_cost' => $overhead,
        'total_cost' => $totalCost,
        'work_in_progress_coa_id' => $ctx['wipInventoryCoa']->id,
        'labor_coa_id' => $ctx['laborCoa']->id,
        'overhead_coa_id' => $ctx['laborCoa']->id,
        'is_active' => true,
    ], $attributes));

    $bomItem = BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $ctx['rawMaterial']->id,
        'quantity' => $rawPerUnit,
        'unit_price' => $rawCost,
        'subtotal' => $totalRaw,
        'uom_id' => $ctx['uom']->id,
    ]);

    return [$bom->fresh(), $bomItem->fresh()];
}

/**
 * Buat ProductionPlan (Rencana Produksi).
 */
function mfgProductionPlan(array $ctx, BillOfMaterial $bom, float $planQty = 5, array $attributes = []): ProductionPlan
{
    return ProductionPlan::create(array_merge([
        'plan_number' => 'PP-' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Rencana Produksi ' . $ctx['finishedGood']->name,
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $ctx['finishedGood']->id,
        'quantity' => $planQty,
        'uom_id' => $ctx['uom']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'start_date' => now(),
        'end_date' => now()->addDays(3),
        'status' => 'scheduled',
        'created_by' => $ctx['user']->id,
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
}

/**
 * Buat MaterialIssue (Pengeluaran Bahan Baku) secara manual.
 */
function mfgMaterialIssue(array $ctx, ProductionPlan $plan, float $rawQty = 10, string $type = 'issue', array $attributes = []): array
{
    $costPerUnit = (float) ($ctx['rawMaterial']->cost_price ?? 20000);
    $totalCost = round($rawQty * $costPerUnit, 2);

    $issue = MaterialIssue::create(array_merge([
        'issue_number' => ($type === 'issue' ? 'MI-' : 'MR-') . strtoupper(substr(uniqid(), -6)),
        'production_plan_id' => $plan->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'issue_date' => now()->toDateString(),
        'type' => $type,
        'status' => 'draft',
        'total_cost' => $totalCost,
        'created_by' => $ctx['user']->id,
    ], $attributes));

    $item = MaterialIssueItem::create([
        'material_issue_id' => $issue->id,
        'product_id' => $ctx['rawMaterial']->id,
        'uom_id' => $ctx['uom']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'rak_id' => $ctx['rak']->id,
        'quantity' => $rawQty,
        'cost_per_unit' => $costPerUnit,
        'total_cost' => $totalCost,
        'status' => 'draft',
    ]);

    return [$issue->fresh(), $item->fresh()];
}

/**
 * Konfirmasi gudang per item untuk MaterialIssue agar dapat disetujui / diselesaikan.
 */
function mfgConfirmMaterialIssue(MaterialIssue $materialIssue): void
{
    $manufacturingService = app(ManufacturingService::class);
    $manufacturingService->createWarehouseConfirmationForMaterialIssue($materialIssue, [
        'status' => 'confirmed',
    ]);

    $materialIssue->warehouseConfirmations()
        ->with('warehouseConfirmationItems')
        ->get()
        ->each(function ($confirmation) {
            $confirmation->warehouseConfirmationItems->each(function ($item) {
                $item->update([
                    'status' => 'confirmed',
                    'confirmed_qty' => $item->requested_qty,
                ]);
            });
            $confirmation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });

    $materialIssue->refresh();
}

/**
 * Buat ManufacturingOrder (Surat Perintah Kerja).
 */
function mfgOrder(array $ctx, ProductionPlan $plan, array $attributes = []): ManufacturingOrder
{
    return ManufacturingOrder::create(array_merge([
        'production_plan_id' => $plan->id,
        'mo_number' => 'MO-' . strtoupper(substr(uniqid(), -6)),
        'status' => 'draft',
        'start_date' => now(),
        'end_date' => now()->addDays(2),
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
}

/**
 * Buat Production (Hasil Batch Produksi).
 */
function mfgProduction(array $ctx, ManufacturingOrder $mo, float $qtyProduced = 5, array $attributes = []): Production
{
    return Production::create(array_merge([
        'production_number' => 'PRO-' . strtoupper(substr(uniqid(), -6)),
        'manufacturing_order_id' => $mo->id,
        'quantity_produced' => $qtyProduced,
        'production_date' => now()->toDateString(),
        'status' => 'draft',
    ], $attributes));
}
