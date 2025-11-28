<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductionPlan;

echo "=== Production Plans Terbaru ===\n";
$plans = ProductionPlan::orderBy('created_at', 'desc')->limit(5)->get();
foreach ($plans as $plan) {
    echo "ID: {$plan->id}, Plan Number: {$plan->plan_number}, Status: {$plan->status}, Created At: {$plan->created_at}\n";
}