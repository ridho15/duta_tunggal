<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductionPlan;

echo "Creating Production Plan with status 'draft'...\n";

$plan = ProductionPlan::create([
    'plan_number' => 'TEST-DRAFT-' . time(),
    'name' => 'Test Plan Draft',
    'source_type' => 'manual',
    'bill_of_material_id' => 1, // Assuming BOM exists
    'product_id' => 1, // Assuming product exists
    'quantity' => 10,
    'uom_id' => 1, // Assuming UOM exists
    'start_date' => now(),
    'end_date' => now()->addDays(7),
    'status' => 'draft',
    'created_by' => 1 // Assuming user exists
]);

echo "Production Plan created with ID: {$plan->id} and status: {$plan->status}\n";

echo "\nNow updating status to 'scheduled'...\n";

try {
    $plan->update(['status' => 'scheduled']);

    echo "Production Plan updated to scheduled.\n";

    $plan->refresh(); // Refresh from database

    echo "Actual status in database: {$plan->status}\n";

    if ($plan->status === 'scheduled') {
        echo "SUCCESS: Status updated correctly.\n";
    } else {
        echo "FAILURE: Status not updated. Still: {$plan->status}\n";
    }

    // Check if MaterialIssue was created
    $materialIssue = \App\Models\MaterialIssue::where('production_plan_id', $plan->id)->first();
    if ($materialIssue) {
        echo "MaterialIssue created: {$materialIssue->issue_number} with status: {$materialIssue->status}\n";

        // Now test updating MaterialIssue status to pending_approval
        echo "\nUpdating MaterialIssue status to 'pending_approval'...\n";
        $materialIssue->update(['status' => 'pending_approval']);
        $materialIssue->refresh();
        echo "MaterialIssue status: {$materialIssue->status}\n";

        $plan->refresh();
        echo "ProductionPlan status after MaterialIssue update: {$plan->status}\n";

        // Now test updating to 'approved'
        echo "\nUpdating MaterialIssue status to 'approved'...\n";
        $materialIssue->update(['status' => 'approved']);
        $materialIssue->refresh();
        echo "MaterialIssue status: {$materialIssue->status}\n";

        $plan->refresh();
        echo "ProductionPlan status after approval: {$plan->status}\n";
    } else {
        echo "No MaterialIssue created.\n";
    }
} catch (\Exception $e) {
    echo "Exception during update: " . $e->getMessage() . "\n";
    $plan->refresh();
    echo "Status after exception: {$plan->status}\n";
}