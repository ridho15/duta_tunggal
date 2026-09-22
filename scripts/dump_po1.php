<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$po = App\Models\PurchaseOrder::with(['purchaseOrderItem'])->find(1);
if (!$po) {
    echo json_encode(null);
    exit(0);
}
echo json_encode($po->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
