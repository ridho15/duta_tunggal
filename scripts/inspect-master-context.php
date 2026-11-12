<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Models\TaxSetting;
use Spatie\Permission\Models\Role;

echo "=== MASTER CONTEXT INSPECTION ===\n";
echo "Categories:\n";
foreach (ProductCategory::take(5)->get() as $c) {
    echo "  - ID {$c->id}: {$c->name}\n";
}

echo "\nUOMs:\n";
foreach (UnitOfMeasure::take(5)->get() as $u) {
    echo "  - ID {$u->id}: {$u->name}\n";
}

echo "\nCabangs:\n";
foreach (Cabang::take(5)->get() as $b) {
    echo "  - ID {$b->id}: {$b->nama}\n";
}

echo "\nWarehouses:\n";
foreach (Warehouse::take(5)->get() as $w) {
    echo "  - ID {$w->id}: {$w->name}\n";
}

echo "\nTaxes:\n";
foreach (TaxSetting::take(5)->get() as $t) {
    echo "  - ID {$t->id}: {$t->name} ({$t->rate}%)\n";
}

echo "\nRoles:\n";
foreach (Role::all() as $r) {
    echo "  - {$r->name}\n";
}