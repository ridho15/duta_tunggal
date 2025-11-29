<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Production;
use App\Models\ManufacturingOrder;
use App\Models\ProductionPlan;
use App\Models\Product;
use App\Models\User;
use App\Models\Cabang;
use App\Models\Warehouse;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing Auto QC Creation\n";
echo "========================\n";

// Get existing data
$user = User::first();
$branch = Cabang::first();
$warehouse = Warehouse::first();
$product = Product::where('is_manufacture', true)->first();

if (!$user || !$branch || !$warehouse || !$product) {
    echo "Missing required data. Please ensure database has users, branches, warehouses, and manufactured products.\n";
    exit(1);
}

echo "Creating test data...\n";

// Create production plan
$timestamp = time();
$plan = ProductionPlan::create([
    'plan_number' => 'PLAN-AUTO-QC-TEST-' . $timestamp,
    'name' => 'Auto QC Test Plan',
    'source_type' => 'manual',
    'product_id' => $product->id,
    'quantity' => 10,
    'uom_id' => $product->uom_id,
    'start_date' => now(),
    'end_date' => now()->addDay(),
    'status' => 'scheduled',
    'warehouse_id' => $warehouse->id, // Add warehouse_id
    'created_by' => $user->id,
]);

// Create manufacturing order
$mo = ManufacturingOrder::create([
    'mo_number' => 'MO-AUTO-QC-TEST-' . $timestamp,
    'production_plan_id' => $plan->id,
    'status' => 'in_progress',
    'start_date' => now(),
    'end_date' => null,
]);

// Create production with draft status
$production = Production::create([
    'production_number' => 'PROD-AUTO-QC-TEST-' . $timestamp,
    'manufacturing_order_id' => $mo->id,
    'production_date' => now(),
    'status' => 'draft',
    'quantity_produced' => 10,
    'warehouse_id' => $warehouse->id,
]);

echo "Production created with ID: {$production->id}\n";
echo "Initial status: {$production->status}\n";

// Check if QC exists before finish
$qcExistsBefore = $production->qualityControl()->exists();
echo "QC exists before finish: " . ($qcExistsBefore ? 'Yes' : 'No') . "\n";

// Update to finished
$production->update(['status' => 'finished']);
echo "Status updated to: {$production->status}\n";

// Check if QC exists after finish
$production->refresh();
$qcExistsAfter = $production->qualityControl()->exists();
echo "QC exists after finish: " . ($qcExistsAfter ? 'Yes' : 'No') . "\n";

if ($qcExistsAfter) {
    $qc = $production->qualityControl;
    echo "QC Number: {$qc->qc_number}\n";
    echo "QC Status: {$qc->status}\n";
    echo "Passed Quantity: {$qc->passed_quantity}\n";
    echo "From Model Type: {$qc->from_model_type}\n";
    echo "From Model ID: {$qc->from_model_id}\n";
    echo "\n✅ SUCCESS: QC created automatically when production finished!\n";
} else {
    echo "❌ FAILED: QC was not created automatically!\n";
}