<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductionPlan;
use App\Models\MaterialFulfillment;

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

$fulfillments = MaterialFulfillment::where('production_plan_id', $plan->id)->get();

echo "Material Fulfillments count: {$fulfillments->count()}\n";

if ($fulfillments->count() > 0) {
    echo "Fulfillments exist!\n";
    foreach ($fulfillments as $f) {
        echo "ID: {$f->id}, Material: {$f->material_id}, Issued: {$f->issued_quantity}, Availability: {$f->availability_percentage}%\n";
    }
} else {
    echo "No fulfillments created.\n";
}

echo "\nNow updating status to 'scheduled'...\n";

$plan->update(['status' => 'scheduled']);

$fulfillmentsAfter = MaterialFulfillment::where('production_plan_id', $plan->id)->get();

echo "Material Fulfillments count after scheduled: {$fulfillmentsAfter->count()}\n";

if ($fulfillmentsAfter->count() > 0) {
    echo "Fulfillments after scheduled:\n";
    foreach ($fulfillmentsAfter as $f) {
        echo "ID: {$f->id}, Material: {$f->material_id}, Issued: {$f->issued_quantity}, Availability: {$f->availability_percentage}%\n";
    }
} else {
    echo "No fulfillments after scheduled.\n";
}