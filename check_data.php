<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductionPlan;
use App\Models\MaterialFulfillment;

echo "=== Production Plans Terbaru ===\n";
$plans = ProductionPlan::orderBy('created_at', 'desc')->limit(5)->get();
foreach ($plans as $plan) {
    echo "ID: {$plan->id}, Plan Number: {$plan->plan_number}, Status: {$plan->status}, Created At: {$plan->created_at}\n";
}

echo "\n=== Material Fulfillments Terbaru ===\n";
$fulfillments = MaterialFulfillment::orderBy('created_at', 'desc')->limit(5)->get();
foreach ($fulfillments as $f) {
    echo "ID: {$f->id}, Production Plan ID: {$f->production_plan_id}, Material ID: {$f->material_id}, Created At: {$f->created_at}\n";
}

echo "\n=== Fulfillments untuk Production Plan Terbaru ===\n";
if ($plans->count() > 0) {
    $latestPlan = $plans->first();
    $fulfillmentsForPlan = MaterialFulfillment::where('production_plan_id', $latestPlan->id)->get();
    echo "Production Plan ID: {$latestPlan->id}, Fulfillments Count: {$fulfillmentsForPlan->count()}\n";
    foreach ($fulfillmentsForPlan as $f) {
        echo "  Fulfillment ID: {$f->id}, Created At: {$f->created_at}\n";
    }
}