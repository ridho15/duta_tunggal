#!/usr/bin/env python3
"""Fix navigationSort collisions across Filament resources."""
import os
import re

BASE = 'app/Filament/Resources'

# Target: {filename: new_sort_value}
# Only files that need to CHANGE from their current value
changes = {
    # Delivery Order group: DO=1, SJ=2, ApprovalLog=3
    'DeliveryOrderResource.php': 1,
    'SuratJalanResource.php': 2,
    'DeliveryOrderApprovalLogResource.php': 3,
    # Finance - Akuntansi: JournalEntry=1, BankReconciliation=2, Ageing=3, Voucher=4
    'JournalEntryResource.php': 1,
    'BankReconciliationResource.php': 2,
    'AgeingScheduleResource.php': 3,
    'VoucherRequestResource.php': 4,
    # Finance - Pembayaran: PaymentRequest=1, VendorPayment=2, CustomerReceipt=3, CashBankTransfer=4, Deposit=5
    'PaymentRequestResource.php': 1,
    'VendorPaymentResource.php': 2,
    'CustomerReceiptResource.php': 3,
    'CashBankTransferResource.php': 4,
    'DepositResource.php': 5,
    # Finance - Penjualan: SalesInvoice=1, AccountReceivable=2, OtherSale=3
    'SalesInvoiceResource.php': 1,
    'AccountReceivableResource.php': 2,
    'OtherSaleResource.php': 3,
    # Gudang: WC=1, StockTransfer=2, StockAdjustment=3, InventoryStock=4, StockMovement=5, StockOpname=6, ReturnProduct=7
    'WarehouseConfirmationResource.php': 1,
    'StockTransferResource.php': 2,
    'StockAdjustmentResource.php': 3,
    'InventoryStockResource.php': 4,
    'StockMovementResource.php': 5,
    'StockOpnameResource.php': 6,
    'ReturnProductResource.php': 7,
    # Manufacturing: ManufacturingOrder=1, ProductionPlan=2, Production=3, BOM=4, MaterialIssue=5, QCManufacture=6
    'ManufacturingOrderResource.php': 1,
    'ProductionPlanResource.php': 2,
    'ProductionResource.php': 3,
    'BillOfMaterialResource.php': 4,
    'MaterialIssueResource.php': 5,
    'QualityControlManufactureResource.php': 6,
    # Master Data: Product=1, ProductCategory=2, Supplier=3, Customer=4, Warehouse=5,
    # Cabang=6, Driver=7, Vehicle=8, Rak=9, UOM=10, Currency=11, COA=12, TaxSetting=13
    'ProductResource.php': 1,
    'ProductCategoryResource.php': 2,
    'SupplierResource.php': 3,
    'CustomerResource.php': 4,
    'WarehouseResource.php': 5,
    'CabangResource.php': 6,
    'DriverResource.php': 7,
    'VehicleResource.php': 8,
    'RakResource.php': 9,
    'UnitOfMeasureResource.php': 10,
    'CurrencyResource.php': 11,
    'ChartOfAccountResource.php': 12,
    'TaxSettingResource.php': 13,
    # User Roles Management: User=1, Role=2, Permission=3
    'UserResource.php': 1,
    'RoleResource.php': 2,
    'PermissionResource.php': 3,
    # Pembelian: OR=1 (unchanged), PO=2, QCPurchase=3, PurchaseReceipt=4, PurchaseReturn=5, PurchaseReceiptItem=6
    'PurchaseOrderResource.php': 2,
    'QualityControlPurchaseResource.php': 3,
    'PurchaseReceiptResource.php': 4,
    'PurchaseReturnResource.php': 5,
    'PurchaseReceiptItemResource.php': 6,
    # Penjualan: Quotation=1, SaleOrder=2 (unchanged)
    'QuotationResource.php': 1,
}

pattern = re.compile(r'(protected static \?int \$navigationSort = )(\d+)(;)')

for filename, new_val in changes.items():
    path = os.path.join(BASE, filename)
    if not os.path.exists(path):
        print(f'FILE NOT FOUND: {path}')
        continue
    with open(path, 'r') as f:
        content = f.read()
    new_content = pattern.sub(lambda m: m.group(1) + str(new_val) + m.group(3), content)
    if new_content != content:
        with open(path, 'w') as f:
            f.write(new_content)
        print(f'Updated {filename} -> {new_val}')
    else:
        print(f'NO CHANGE (pattern not matched): {filename}')

print('\nDone.')
