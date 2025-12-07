<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$qc = App\Models\QualityControl::with(['fromModel.purchaseOrderItem.purchaseOrder.purchaseOrderCurrency', 'product.inventoryCoa', 'product.temporaryProcurementCoa', 'product.unbilledPurchaseCoa'])->find(1);

if ($qc) {
    $fromModel = $qc->fromModel;
    $product = $qc->product;
    $passedQuantity = $qc->passed_quantity;

    echo 'QC Details:' . PHP_EOL;
    echo '  Passed Quantity: ' . $passedQuantity . PHP_EOL;

    // Get unit price based on model type
    if ($qc->from_model_type === 'App\Models\PurchaseOrderItem') {
        $unitPrice = $fromModel?->unit_price ?? 0;
        echo '  Unit Price (PO Item): ' . $unitPrice . PHP_EOL;
    } elseif ($qc->from_model_type === 'App\Models\PurchaseReceiptItem') {
        $unitPrice = $fromModel?->purchaseOrderItem?->unit_price ?? 0;
        echo '  Unit Price (Receipt Item -> PO Item): ' . $unitPrice . PHP_EOL;
    } else {
        $unitPrice = 0;
        echo '  Unit Price: ' . $unitPrice . PHP_EOL;
    }

    if ($passedQuantity <= 0 || $unitPrice <= 0) {
        echo '  SKIP: passedQuantity <= 0 or unitPrice <= 0' . PHP_EOL;
    } else {
        $amount = round($passedQuantity * $unitPrice, 2);
        echo '  Amount: ' . $amount . PHP_EOL;
    }

    // Get COA accounts
    $inventoryCoa = $product->inventoryCoa ?? null;
    $temporaryProcurementCoa = $product->temporaryProcurementCoa ?? null;
    $unbilledPurchaseCoa = $product->unbilledPurchaseCoa ?? null;

    echo '  Inventory COA: ' . ($inventoryCoa ? $inventoryCoa->name : 'NULL') . PHP_EOL;
    echo '  Temp Procurement COA: ' . ($temporaryProcurementCoa ? $temporaryProcurementCoa->name : 'NULL') . PHP_EOL;
    echo '  Unbilled Purchase COA: ' . ($unbilledPurchaseCoa ? $unbilledPurchaseCoa->name : 'NULL') . PHP_EOL;

    if (!$inventoryCoa || !$temporaryProcurementCoa || !$unbilledPurchaseCoa) {
        echo '  SKIP: Missing COA accounts' . PHP_EOL;
    }
}