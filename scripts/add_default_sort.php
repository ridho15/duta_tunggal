<?php

/**
 * Batch-patch script: add ->defaultSort('created_at', 'desc') to all
 * Filament table() methods that are missing it.
 *
 * Run from project root: php scripts/add_default_sort.php
 */

$files = [
    'app/Filament/Resources/AgeingScheduleResource.php',
    'app/Filament/Resources/AssetDisposalResource.php',
    'app/Filament/Resources/AssetTransferResource.php',
    'app/Filament/Resources/BillOfMaterialResource.php',
    'app/Filament/Resources/CabangResource.php',
    'app/Filament/Resources/CashBankAccountResource.php',
    'app/Filament/Resources/ChartOfAccountResource.php',
    'app/Filament/Resources/ChartOfAccountResource/RelationManagers/JournalEntryRelationManager.php',
    'app/Filament/Resources/CurrencyResource.php',
    'app/Filament/Resources/CustomerResource.php',
    'app/Filament/Resources/CustomerResource/RelationManagers/SalesRelationManager.php',
    'app/Filament/Resources/DeliveryOrderResource.php',
    'app/Filament/Resources/DriverResource.php',
    'app/Filament/Resources/DriverResource/RelationManagers/DeliveryOrderRelationManager.php',
    'app/Filament/Resources/InvoiceResource.php',
    'app/Filament/Resources/ManufacturingOrderResource.php',
    'app/Filament/Resources/MaterialIssueResource/RelationManagers/ItemsRelationManager.php',
    'app/Filament/Resources/OtherSaleResource.php',
    'app/Filament/Resources/PermissionResource.php',
    'app/Filament/Resources/ProductCategoryResource.php',
    'app/Filament/Resources/ProductResource.php',
    'app/Filament/Resources/ProductResource/RelationManagers/InventoryStockRelationManager.php',
    'app/Filament/Resources/ProductResource/RelationManagers/StockMovementRelationManager.php',
    'app/Filament/Resources/ProductResource/RelationManagers/SuppliersRelationManager.php',
    'app/Filament/Resources/PurchaseOrderResource.php',
    'app/Filament/Resources/PurchaseOrderResource/RelationManagers/PurchaseOrderItemRelationManager.php',
    'app/Filament/Resources/PurchaseReceiptItemResource.php',
    'app/Filament/Resources/PurchaseReceiptResource.php',
    'app/Filament/Resources/PurchaseReceiptResource/RelationManagers/PurchaseReceiptItemRelationManager.php',
    'app/Filament/Resources/PurchaseReturnResource.php',
    'app/Filament/Resources/QuotationResource.php',
    'app/Filament/Resources/QuotationResource/RelationManagers/QuotationItemRelationManager.php',
    'app/Filament/Resources/RakResource.php',
    'app/Filament/Resources/Reports/BalanceSheetResource.php',
    'app/Filament/Resources/Reports/CashFlowResource.php',
    'app/Filament/Resources/Reports/HppResource.php',
    'app/Filament/Resources/Reports/InventoryCardResource.php',
    'app/Filament/Resources/Reports/ProfitAndLossResource.php',
    'app/Filament/Resources/Reports/StockMutationReportResource.php',
    'app/Filament/Resources/Reports/StockReportResource.php',
    'app/Filament/Resources/ReturnProductResource.php',
    'app/Filament/Resources/RoleResource.php',
    'app/Filament/Resources/SaleOrderResource.php',
    'app/Filament/Resources/SaleOrderResource/RelationManagers/SaleOrderItemRelationManager.php',
    'app/Filament/Resources/StockAdjustmentResource/RelationManagers/StockAdjustmentItemsRelationManager.php',
    'app/Filament/Resources/StockMovementResource.php',
    'app/Filament/Resources/StockOpnameResource.php',
    'app/Filament/Resources/StockOpnameResource/RelationManagers/StockOpnameItemsRelationManager.php',
    'app/Filament/Resources/StockTransferResource.php',
    'app/Filament/Resources/SupplierResource.php',
    'app/Filament/Resources/SupplierResource/RelationManagers/ProductsRelationManager.php',
    'app/Filament/Resources/SupplierResource/RelationManagers/PurchaseOrderRelationManager.php',
    'app/Filament/Resources/SuratJalanResource.php',
    'app/Filament/Resources/TaxSettingResource.php',
    'app/Filament/Resources/UnitOfMeasureResource.php',
    'app/Filament/Resources/UserResource.php',
    'app/Filament/Resources/VehicleResource.php',
    'app/Filament/Resources/VehicleResource/RelationManagers/DeliveryOrderRelationManager.php',
    'app/Filament/Resources/WarehouseConfirmationResource.php',
    'app/Filament/Resources/WarehouseResource.php',
    'app/Filament/Resources/WarehouseResource/RelationManagers/RakRelationManager.php',
    'app/Filament/Widgets/DepositByEntityWidget.php',
    'app/Filament/Widgets/DoBelumSelesaiTable.php',
    'app/Filament/Widgets/MutasiKeluarBelumSelesaiTable.php',
    'app/Filament/Widgets/MutasiMasukBelumSelesaiTable.php',
    'app/Filament/Widgets/PenawaranHargaTable.php',
    'app/Filament/Widgets/PenerimaanBarangBelumSelesaiTable.php',
    'app/Filament/Widgets/PoBelumSelesaiTable.php',
    'app/Filament/Widgets/SoBelumSelesaiTable.php',
    'app/Filament/Widgets/StockMinimumTable.php',
    'app/Filament/Widgets/TopTagihanOutstanding.php',
    'app/Filament/Pages/DepositSummaryPage.php',
    'app/Filament/Pages/IncomeStatementPage.php',
    'app/Filament/Pages/InventoryReportPage.php',
    'app/Filament/Pages/PurchaseReportPage.php',
    'app/Filament/Pages/SalesReportPage.php',
];

$patched = 0;
$skipped = 0;
$noMatch = 0;

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "NOT FOUND: $file\n";
        $skipped++;
        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, 'defaultSort') !== false) {
        echo "ALREADY:  $file\n";
        $skipped++;
        continue;
    }

    // Pattern: "return $table" followed by optional whitespace/newlines then "->"
    // Insert ->defaultSort('created_at', 'desc') after "return $table\n"
    $new = preg_replace(
        '/(return \$table)([ \t]*\r?\n[ \t]*)(->)/',
        "$1$2->defaultSort('created_at', 'desc')\n            $3",
        $content,
        1
    );

    if ($new === null || $new === $content) {
        echo "NO MATCH:  $file\n";
        $noMatch++;
        continue;
    }

    file_put_contents($file, $new);
    echo "PATCHED:  $file\n";
    $patched++;
}

echo "\n";
echo "Total patched:  $patched\n";
echo "Already sorted: $skipped\n";
echo "No match:       $noMatch\n";
