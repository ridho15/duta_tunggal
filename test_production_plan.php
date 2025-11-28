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

$plan->update(['status' => 'scheduled']);

echo "Production Plan updated to scheduled.\n";