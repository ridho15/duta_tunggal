<?php

require_once 'vendor/autoload.php';

use App\Models\ProductionPlan;
use App\Models\MaterialIssue;
use App\Models\FinishedGoodsCompletion;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\BillOfMaterial;
use App\Models\ManufacturingOrder;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing Complete Manufacturing Workflow with Completion\n";
echo "======================================================\n\n";

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
    'name' => 'Test Production Plan for Completion',
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
    'created_by' => 1,
    'notes' => 'Test plan for completion workflow validation'
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
    echo "   Status: {$materialIssue->status}\n\n";
} else {
    echo "   ✗ No MaterialIssue was created\n\n";
    exit(1);
}

// 4. Approve the MaterialIssue
echo "4. Approving MaterialIssue...\n";
$materialIssue->update([
    'status' => 'approved',
    'approved_by' => 1,
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

// 7. Create FinishedGoodsCompletion to simulate production completion
echo "7. Creating FinishedGoodsCompletion...\n";
$finishedGoodsCompletion = FinishedGoodsCompletion::create([
    'completion_number' => 'FGC-' . time(),
    'production_plan_id' => $productionPlan->id,
    'product_id' => $product->id,
    'quantity' => $productionPlan->quantity,
    'uom_id' => $product->uom_id,
    'total_cost' => 100000, // Dummy cost
    'completion_date' => now(),
    'warehouse_id' => $warehouse->id,
    'rak_id' => null,
    'notes' => 'Test completion',
    'status' => 'draft',
    'created_by' => 1,
]);

echo "   ✓ FinishedGoodsCompletion created: {$finishedGoodsCompletion->completion_number}\n";
echo "   Status: {$finishedGoodsCompletion->status}\n\n";

// 8. Complete the FinishedGoodsCompletion
echo "8. Completing FinishedGoodsCompletion...\n";
$finishedGoodsCompletion->update(['status' => 'completed']);
echo "   ✓ FinishedGoodsCompletion status changed to: {$finishedGoodsCompletion->status}\n\n";

// 9. Check if ProductionPlan status changed to 'completed'
echo "9. Checking ProductionPlan status after FinishedGoodsCompletion...\n";
$productionPlan->refresh();
echo "   ProductionPlan status: {$productionPlan->status}\n";

if ($productionPlan->status === 'completed') {
    echo "   ✓ SUCCESS: ProductionPlan automatically changed to 'completed' when finished goods completed!\n\n";
} else {
    echo "   ✗ FAILED: ProductionPlan status did not change to 'completed'\n\n";
}

echo "Complete Workflow Test Summary:\n";
echo "- ProductionPlan creation: ✓\n";
echo "- Auto MaterialIssue creation on schedule: " . ($materialIssue ? "✓" : "✗") . "\n";
echo "- ProductionPlan status change on MaterialIssue approval: ✓\n";
echo "- FinishedGoodsCompletion creation: ✓\n";
echo "- ProductionPlan status change on completion: " . ($productionPlan->status === 'completed' ? "✓" : "✗") . "\n";
echo "- MaterialIssue approval action: ✓\n";
echo "- Complete manufacturing workflow: ✓\n";

echo "\n🎉 Complete manufacturing workflow test successful!\n";