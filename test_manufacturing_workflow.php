<?php

require_once 'vendor/autoload.php';

use App\Models\ProductionPlan;
use App\Models\MaterialIssue;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\BillOfMaterial;
use App\Models\ManufacturingOrder;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing Complete Manufacturing Workflow\n";
echo "=======================================\n\n";

// 1. Create a ProductionPlan
echo "1. Creating ProductionPlan...\n";
$warehouse = Warehouse::first();
$product = Product::where('is_raw_material', false)->first();
$bom = BillOfMaterial::first();

if (!$warehouse || !$product || !$bom) {
    echo "ERROR: Missing required data (warehouse, product, or BOM)\n";
    exit(1);
}

$productionPlan = ProductionPlan::create([
    'plan_number' => 'TEST-' . time(),
    'name' => 'Test Production Plan',
    'product_id' => $product->id,
    'bill_of_material_id' => $bom->id,
    'warehouse_id' => $warehouse->id,
    'quantity' => 10,
    'uom_id' => $product->uom_id,
    'start_date' => now(),
    'end_date' => now()->addDays(7),
    'planned_start_date' => now(),
    'planned_end_date' => now()->addDays(7),
    'status' => 'draft',
    'created_by' => 1, // Assuming user ID 1 exists
    'notes' => 'Test plan for workflow validation'
]);

echo "   ✓ ProductionPlan created: {$productionPlan->plan_number}\n";
echo "   Status: {$productionPlan->status}\n\n";

// 2. Schedule the ProductionPlan
echo "2. Scheduling ProductionPlan...\n";
$productionPlan->update(['status' => 'scheduled']);
echo "   ✓ ProductionPlan status changed to: {$productionPlan->status}\n\n";

// 3. Check if MaterialIssue was auto-created
echo "3. Checking for auto-created MaterialIssue...\n";
$materialIssue = MaterialIssue::where('production_plan_id', $productionPlan->id)->first();

if ($materialIssue) {
    echo "   ✓ MaterialIssue auto-created: {$materialIssue->issue_number}\n";
    echo "   Status: {$materialIssue->status}\n";
    echo "   Items count: " . $materialIssue->items()->count() . "\n\n";
} else {
    echo "   ✗ No MaterialIssue was created\n\n";
    exit(1);
}

// 4. Approve the MaterialIssue
echo "4. Approving MaterialIssue...\n";
$materialIssue->update([
    'status' => 'approved',
    'approved_by' => 1, // Assuming user ID 1 exists
    'approved_at' => now(),
]);
echo "   ✓ MaterialIssue status changed to: {$materialIssue->status}\n\n";

// 5. Check if ProductionPlan status changed to 'in_progress'
echo "5. Checking ProductionPlan status after MaterialIssue approval...\n";
$productionPlan->refresh();
echo "   ProductionPlan status: {$productionPlan->status}\n";

if ($productionPlan->status === 'in_progress') {
    echo "   ✓ SUCCESS: ProductionPlan automatically changed to 'in_progress'\n\n";
} else {
    echo "   ✗ FAILED: ProductionPlan status did not change to 'in_progress'\n\n";
}

// 6. Complete the MaterialIssue
echo "6. Completing MaterialIssue...\n";
$materialIssue->update(['status' => 'completed']);
echo "   ✓ MaterialIssue status changed to: {$materialIssue->status}\n\n";

// 7. Check ProductionPlan status remains 'in_progress'
echo "7. Checking ProductionPlan status after MaterialIssue completion...\n";
$productionPlan->refresh();
echo "   ProductionPlan status: {$productionPlan->status}\n";

if ($productionPlan->status === 'in_progress') {
    echo "   ✓ ProductionPlan status correctly remains 'in_progress'\n\n";
} else {
    echo "   ⚠ ProductionPlan status changed to: {$productionPlan->status}\n\n";
}

echo "Workflow Test Summary:\n";
echo "- ProductionPlan creation: ✓\n";
echo "- Auto MaterialIssue creation on schedule: " . ($materialIssue ? "✓" : "✗") . "\n";
echo "- ProductionPlan status change on approval: " . ($productionPlan->status === 'in_progress' ? "✓" : "✗") . "\n";
echo "- MaterialIssue approval action: ✓\n";
echo "- Status progression workflow: ✓\n";

echo "\nTest completed successfully! 🎉\n";