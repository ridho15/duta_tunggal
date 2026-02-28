<?php
/**
 * Setup script for E2E manufacturing flow test.
 *
 * Ensures:
 *  1. A Bill of Material (BOM) exists for the test product with sufficient raw material stock
 *  2. Inventory stock for raw material is available (>= 20)
 *  3. Any leftover test Production Plans / MOs from prior runs are cleaned up
 *
 * Run: php scripts/setup_manufacturing_test.php
 */

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Product;
use App\Models\InventoryStock;
use App\Models\ProductionPlan;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Production;
use App\Models\UnitOfMeasure;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Models\User;

$log = [];

// ── 0. Ensure e2e test user has manage_type 'all' (needed to see all MI/MO records) ──
// manage_type is stored as comma-separated string (not JSON), handled by getManageTypeAttribute accessor
$e2eUser = User::where('email', 'e2e-test@duta-tunggal.test')->first();
if ($e2eUser) {
    $currentManageType = $e2eUser->manage_type; // accessor returns array via explode(',', value)
    if (!in_array('all', $currentManageType)) {
        DB::table('users')->where('id', $e2eUser->id)->update(['manage_type' => 'all']);
        echo "[Setup] Updated e2e user manage_type to 'all'" . PHP_EOL;
    } else {
        echo "[Setup] e2e user manage_type already includes 'all'" . PHP_EOL;
    }

    // Ensure e2e user has permissions required for the full manufacturing E2E flow:
    //  - view any manufacturing order  → to see the MO list in Step 7
    //  - create manufacturing order    → needed for Buat MO action internals
    //  - request manufacturing order   → to see & click the Produksi action on an MO
    $permissionsToGrant = [
        'view any manufacturing order',
        'create manufacturing order',
        'request manufacturing order',
    ];
    foreach ($permissionsToGrant as $permName) {
        $perm = \Spatie\Permission\Models\Permission::where('name', $permName)->first();
        if ($perm) {
            if (!$e2eUser->hasPermissionTo($permName)) {
                $e2eUser->givePermissionTo($perm);
                echo "[Setup] Granted permission: {$permName}" . PHP_EOL;
            } else {
                echo "[Setup] Already has permission: {$permName}" . PHP_EOL;
            }
        } else {
            echo "[Setup] WARNING: Permission not found in system: {$permName}" . PHP_EOL;
        }
    }
    // Clear permission cache so Filament picks up new assignments immediately
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo "[Setup] Permission cache cleared" . PHP_EOL;
}

// ── 1. Identify test products ─────────────────────────────────────────────
// Finished goods: Product 101 (SKU-001, "Produk sed 1")  — plenty of stock → ok as FG too
// Raw material:   Product 104 (SKU-004, "Produk rerum 4") — has 32 in wh=1

$fgProduct   = Product::find(101); // Produk sed 1 — we'll produce MORE of this
$rawProduct  = Product::find(104); // Produk rerum 4 — used as raw material

abort_if(!$fgProduct,  1, "FG Product id=101 not found\n");
abort_if(!$rawProduct, 1, "Raw material Product id=104 not found\n");

$log[] = "FG product: id={$fgProduct->id} name={$fgProduct->name} sku={$fgProduct->sku}";
$log[] = "Raw product: id={$rawProduct->id} name={$rawProduct->name} sku={$rawProduct->sku}";

// ── 2. Ensure UOM exists ──────────────────────────────────────────────────
$uom = UnitOfMeasure::first();
abort_if(!$uom, 1, "No UOM found\n");
$log[] = "UOM id={$uom->id} name={$uom->name} abbr={$uom->abbreviation}";

// ── 3. Ensure raw material stock >= 20 in warehouse 1 ────────────────────
$rawStock = InventoryStock::firstOrCreate(
    ['product_id' => $rawProduct->id, 'warehouse_id' => 1],
    ['qty_available' => 50, 'qty_on_hand' => 50, 'qty_reserved' => 0]
);
if ($rawStock->qty_available < 20) {
    $rawStock->update(['qty_available' => 50, 'qty_on_hand' => 50]);
    $log[] = "Raw material stock set to 50 (was {$rawStock->qty_available})";
} else {
    $log[] = "Raw material stock OK: qty_available={$rawStock->qty_available}";
}

// ── 4. Ensure BOM exists ──────────────────────────────────────────────────
$cabang = Cabang::first();
abort_if(!$cabang, 1, "No cabang found\n");

// COA for finished goods and WIP
$fgCoa  = ChartOfAccount::where('code', '1140.02')->first();
$wipCoa = ChartOfAccount::where('code', '1140.02')->first();

$bom = BillOfMaterial::withoutGlobalScopes()->withTrashed()
    ->where('product_id', $fgProduct->id)
    ->where('is_active', true)
    ->first();

if ($bom && $bom->trashed()) {
    $bom->restore();
    $log[] = "BOM id={$bom->id} restored from soft-delete";
}

if (!$bom) {
    DB::transaction(function () use ($fgProduct, $rawProduct, $uom, $cabang, $fgCoa, $wipCoa, &$bom, &$log) {
        $bom = BillOfMaterial::create([
            'cabang_id'              => $cabang->id,
            'product_id'             => $fgProduct->id,
            'quantity'               => 1,
            'uom_id'                 => $uom->id,
            'code'                   => 'BOM-E2E-001',
            'nama_bom'               => 'BOM E2E Test',
            'note'                   => 'Auto-generated for E2E test',
            'is_active'              => true,
            'labor_cost'             => 0,
            'overhead_cost'          => 0,
            'total_cost'             => 1000,
            'finished_goods_coa_id'  => $fgCoa?->id,
            'work_in_progress_coa_id' => $wipCoa?->id,
        ]);

        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id'          => $rawProduct->id,
            'quantity'            => 2,
            'unit_price'          => 500,
            'subtotal'            => 1000,
            'uom_id'              => $uom->id,
            'note'                => 'Raw material for E2E test',
        ]);

        $log[] = "BOM created: id={$bom->id} code={$bom->code}";
    });
} else {
    // Ensure BOM has at least one item with raw material
    $item = BillOfMaterialItem::withoutGlobalScopes()->withTrashed()
        ->where('bill_of_material_id', $bom->id)
        ->first();

    if ($item && $item->trashed()) {
        $item->restore();
        $log[] = "BOM item id={$item->id} restored";
    }

    if (!$item) {
        BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id'          => $rawProduct->id,
            'quantity'            => 2,
            'unit_price'          => 500,
            'subtotal'            => 1000,
            'uom_id'              => $uom->id,
            'note'                => 'Raw material for E2E test',
        ]);
        $log[] = "BOM item added (was missing)";
    }

    $log[] = "BOM id={$bom->id} code={$bom->code} already exists";
}

// Reload BOM with items
$bom->refresh();
$bomItemCount = BillOfMaterialItem::withoutGlobalScopes()
    ->where('bill_of_material_id', $bom->id)
    ->count();
$log[] = "BOM items count: {$bomItemCount}";

// ── 5. Clean up ALL E2E test data (Production Plans, MOs, MI, Productions) ─
// This ensures each test run starts from a clean slate
Production::withoutGlobalScopes()->withTrashed()->forceDelete();
ManufacturingOrder::withoutGlobalScopes()->withTrashed()->forceDelete();

// Clean material issue items first (FK constraint)
DB::table('material_issue_items')->delete();
MaterialIssue::withoutGlobalScopes()->withTrashed()->forceDelete();
ProductionPlan::withoutGlobalScopes()->withTrashed()->forceDelete();

$log[] = "Cleaned all Production Plans, MOs, Material Issues, Productions";

// ── 6. Output summary ─────────────────────────────────────────────────────
foreach ($log as $line) {
    echo "[Setup] {$line}" . PHP_EOL;
}

// JSON state for Playwright
$state = [
    'bomId'       => $bom->id,
    'bomCode'     => $bom->code,
    'bomName'     => $bom->nama_bom,
    'fgProductId' => $fgProduct->id,
    'fgProductName' => $fgProduct->name,
    'fgSku'       => $fgProduct->sku,
    'rawProductId' => $rawProduct->id,
    'rawProductName' => $rawProduct->name,
    'rawSku'      => $rawProduct->sku,
    'uomId'       => $uom->id,
    'uomName'     => $uom->name,
    'warehouseId' => 1,
    'cabangId'    => $cabang->id,
    'cabangName'  => $cabang->nama,
    'rawStockAvailable' => (int) InventoryStock::where('product_id', $rawProduct->id)->where('warehouse_id', 1)->value('qty_available'),
];

file_put_contents('/tmp/e2e-manufacturing-setup.json', json_encode($state, JSON_PRETTY_PRINT));
echo "[Setup] State saved to /tmp/e2e-manufacturing-setup.json" . PHP_EOL;
echo "[Setup] DONE" . PHP_EOL;
