# DUTA TUNGGAL ERP — API & Service Reference
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## 1. HTTP ROUTES REFERENCE

### 1.1 Web Routes (`routes/web.php`)

| Method | Path | Controller | Keterangan |
|--------|------|------------|-----------|
| GET | `/` | redirect | Redirect ke `/admin` (Filament Dashboard) |
| GET | `/login` | redirect | Redirect ke `/admin` |
| POST | `/logout` | Auth::logout | Named: `logout` |
| GET | `/dashboard` | view('dashboard') | Dashboard view |
| GET | `/settings/profile` | Livewire\Settings\Profile | Edit profil user |
| GET | `/settings/password` | Livewire\Settings\Password | Ubah password |
| GET | `/settings/appearance` | Livewire\Settings\Appearance | Tema/appearance |
| GET | `/exports/download/{filename}` | serve file | Hanya `local` env |
| GET | `/reports/stock-report/preview` | StockReportController@preview | Preview stock report |
| GET | `/reports/inventory-card/print` | InventoryCardController@printView | Print kartu persediaan |
| GET | `/reports/inventory-card/download-pdf` | InventoryCardController@downloadPdf | Download PDF |
| GET | `/reports/inventory-card/download-excel` | InventoryCardController@downloadExcel | Download Excel |

### 1.2 Auth Routes (`routes/auth.php`)

| Method | Path | Keterangan |
|--------|------|-----------|
| GET | `/register` | Halaman registrasi |
| GET | `/forgot-password` | Lupa password |
| POST | `/forgot-password` | Kirim reset link |
| GET | `/reset-password/{token}` | Form reset password |
| POST | `/reset-password` | Proses reset password |
| GET | `/verify-email` | Halaman verifikasi email |
| GET | `/email/verify/{id}/{hash}` | Konfirmasi email (signed) |
| POST | `/email/verification-notification` | Kirim ulang email verifikasi |
| GET | `/confirm-password` | Konfirmasi password untuk aksi penting |
| POST | `/confirm-password` | Proses konfirmasi |

### 1.3 Filament Admin Routes (`/admin/*`)

**Panel Root:** `/admin`

| Grup | Path Pattern | Keterangan |
|------|-------------|-----------|
| Sales | `/admin/sale-orders/*` | CRUD Sales Order |
| Sales | `/admin/quotations/*` | CRUD Quotation |
| Sales | `/admin/delivery-orders/*` | CRUD Delivery Order |
| Sales | `/admin/surat-jalans/*` | CRUD Surat Jalan |
| Sales | `/admin/customer-returns/*` | CRUD Customer Return |
| Sales | `/admin/other-sales/*` | CRUD Other Sales |
| Procurement | `/admin/order-requests/*` | CRUD Order Request |
| Procurement | `/admin/purchase-orders/*` | CRUD Purchase Order |
| Procurement | `/admin/purchase-receipts/*` | CRUD GRN |
| Procurement | `/admin/quality-control-purchases/*` | QC Pembelian |
| Procurement | `/admin/purchase-returns/*` | CRUD Purchase Return |
| Procurement | `/admin/payment-requests/*` | CRUD Payment Request |
| Procurement | `/admin/vendor-payments/*` | CRUD Vendor Payment |
| Manufacturing | `/admin/bill-of-materials/*` | CRUD BOM |
| Manufacturing | `/admin/production-plans/*` | CRUD Production Plan |
| Manufacturing | `/admin/manufacturing-orders/*` | CRUD Manufacturing Order |
| Manufacturing | `/admin/material-issues/*` | CRUD Material Issue |
| Manufacturing | `/admin/productions/*` | CRUD Production |
| Manufacturing | `/admin/quality-control-manufactures/*` | QC Manufaktur |
| Inventory | `/admin/inventory-stocks/*` | View Inventory Stock |
| Inventory | `/admin/stock-movements/*` | View Stock Movements |
| Inventory | `/admin/stock-transfers/*` | CRUD Stock Transfer |
| Inventory | `/admin/stock-adjustments/*` | CRUD Stock Adjustment |
| Inventory | `/admin/stock-opnames/*` | CRUD Stock Opname |
| Finance | `/admin/invoices/*` | View Invoices |
| Finance | `/admin/sales-invoices/*` | CRUD Sales Invoice |
| Finance | `/admin/purchase-invoices/*` | CRUD Purchase Invoice |
| Finance | `/admin/account-receivables/*` | AR Management |
| Finance | `/admin/account-payables/*` | AP Management |
| Finance | `/admin/customer-receipts/*` | CRUD Customer Receipt |
| Finance | `/admin/deposits/*` | CRUD Deposit |
| Finance | `/admin/journal-entries/*` | View/Create Journal |
| Finance | `/admin/cash-bank-accounts/*` | CRUD Cash/Bank Accounts |
| Finance | `/admin/cash-bank-transactions/*` | CRUD Cash/Bank Transactions |
| Finance | `/admin/cash-bank-transfers/*` | CRUD Transfers |
| Finance | `/admin/bank-reconciliations/*` | CRUD Bank Reconciliation |
| Finance | `/admin/voucher-requests/*` | CRUD Voucher Request |
| Assets | `/admin/assets/*` | CRUD Fixed Assets |
| Assets | `/admin/asset-disposals/*` | CRUD Disposal |
| Assets | `/admin/asset-transfers/*` | CRUD Transfer Aset |
| Reports | `/admin/balance-sheet-page` | Balance Sheet |
| Reports | `/admin/buku-besar-page` | Buku Besar (General Ledger) |
| Reports | `/admin/alk-grafik` | ALK Grafik |
| Reports | `/admin/ar-ap-management` | AR/AP Management |
| Reports | `/admin/hpp-page` | HPP Report |
| Reports | `/admin/cash-flow-page` | Cash Flow |
| Reports | `/admin/profit-and-loss-page` | Laba Rugi |
| Reports | `/admin/ageing-schedules/*` | Ageing Schedule |
| Master | `/admin/customers/*` | CRUD Customer |
| Master | `/admin/suppliers/*` | CRUD Supplier |
| Master | `/admin/products/*` | CRUD Product |
| Master | `/admin/product-categories/*` | CRUD Category |
| Master | `/admin/drivers/*` | CRUD Driver |
| Master | `/admin/vehicles/*` | CRUD Vehicle |
| Master | `/admin/warehouses/*` | CRUD Warehouse |
| Master | `/admin/cabangs/*` | CRUD Cabang |
| Master | `/admin/raks/*` | CRUD Rak/Shelf |
| Master | `/admin/unit-of-measures/*` | CRUD UOM |
| Master | `/admin/currencies/*` | CRUD Currency |
| Master | `/admin/chart-of-accounts/*` | CRUD COA |
| Master | `/admin/cash-bank-accounts/*` | CRUD Cash/Bank Account |
| Admin | `/admin/users/*` | CRUD User |
| Admin | `/admin/roles/*` | CRUD Role |
| Admin | `/admin/permissions/*` | CRUD Permission |
| Admin | `/admin/app-settings` | App Settings Page |

---

## 2. SERVICE API REFERENCE

### 2.1 TaxService

**File:** `app/Services/TaxService.php`

```php
// Hitung PPN Exclusive (PPN ditambahkan ke harga)
calculateExclusive(float $amount, float $ratePercent): array
// Returns: ['dpp' => float, 'ppn' => float, 'total' => float]
// Example: amount=1_000_000, rate=11 → dpp=1_000_000, ppn=110_000, total=1_110_000

// Hitung PPN Inclusive (PPN sudah termasuk dalam harga)
calculateInclusive(float $gross, float $ratePercent): array
// Returns: ['dpp' => float, 'ppn' => float, 'total' => float]
// Example: gross=1_110_000, rate=11 → dpp=1_000_000, ppn=110_000, total=1_110_000

// Hitung PPN untuk array items
calculateForItems(array $items, string $taxType, float $ratePercent): array
// Returns array of items with ppn calculated
```

---

### 2.2 InvoiceService

**File:** `app/Services/InvoiceService.php`

```php
// Buat invoice dari Delivery Order
createFromDeliveryOrder(DeliveryOrder $do): Invoice

// Buat invoice dari Purchase Receipt
createFromPurchaseReceipt(PurchaseReceipt $receipt): Invoice

// Generate nomor invoice
generateInvoiceNumber(bool $withPpn, int $cabangId): string
// Format: {prefix}-{YYYYMM}-{XXXX}

// Hitung total invoice dengan PPN dan other_fee
calculateTotal(array $items, float $ppnRate, array $otherFees): array
// Returns: ['subtotal', 'tax', 'other_fee_total', 'total', 'dpp', 'ppn']
```

---

### 2.3 SalesOrderService

**File:** `app/Services/SalesOrderService.php`

```php
// Approve sale order
approveSaleOrder(SaleOrder $order, User $approver): void
// Side effects: creates WarehouseConfirmation, reserves stock

// Request approve
requestApprove(SaleOrder $order, User $requester): void

// Cancel sale order
cancelSaleOrder(SaleOrder $order): void
// Side effects: releases stock reservations

// Close sale order
closeSaleOrder(SaleOrder $order, User $closer): void
```

---

### 2.4 PurchaseOrderService

**File:** `app/Services/PurchaseOrderService.php`

```php
// Approve PO
approvePo(PurchaseOrder $po, User $approver): void
// Changes status: draft → approved

// Create PO from Order Request
createFromOrderRequest(OrderRequest $request, array $supplierGroups): array
// Returns array of created PurchaseOrders

// Complete PO
completePurchaseOrder(PurchaseOrder $po): void
// Side effects: updates order request fulfilled quantities
```

---

### 2.5 StockReservationService

**File:** `app/Services/StockReservationService.php`

```php
// Reservasi stok untuk Sale Order
reserveForSaleOrder(SaleOrder $order): void
// Creates StockReservation records, updates InventoryStock.qty_reserved

// Release reservasi (SO dibatalkan atau diedit)
releaseReservation(SaleOrder $order): void
// Deletes StockReservation records, restores InventoryStock.qty_reserved

// Check ketersediaan stok
checkAvailability(Product $product, Warehouse $warehouse, float $qty): bool
// Returns true if qty_available >= qty
```

---

### 2.6 CustomerReturnService

**File:** `app/Services/CustomerReturnService.php`

```php
// Restore stok setelah return di-approve
restoreStock(CustomerReturn $return): void
// Creates StockMovement for each accepted item
// Updates InventoryStock.qty_available

// Complete customer return
completeReturn(CustomerReturn $return): void
// Calls restoreStock, posts journal entry, updates AR/invoice
```

---

### 2.7 DeliveryOrderService

**File:** `app/Services/DeliveryOrderService.php`

```php
// Tandai DO sebagai sent
markAsSent(DeliveryOrder $do): void
// Changes status: received → sent (or approved)

// Confirm dana diterima (approve)
confirmPaymentReceived(DeliveryOrder $do, User $user): void
// Changes status: received → approved, posts journal

// Tandai gagal kirim
markDeliveryFailed(DeliveryOrder $do, User $user, ?string $reason): void
// Changes status: sent → delivery_failed
```

---

### 2.8 BalanceSheetService

**File:** `app/Services/BalanceSheetService.php`

```php
// Generate balance sheet
generate(Carbon $asOf, int $cabangId): array
// Returns: ['assets' => [...], 'liabilities' => [...], 'equity' => [...], 'total_assets', 'total_liabilities_equity']

// Get balance for specific COA
getCoaBalance(int $coaId, Carbon $asOf): float
// Returns: opening_balance + sum(debit) - sum(credit) up to asOf date
```

---

### 2.9 QualityControlService

**File:** `app/Services/QualityControlService.php`

```php
// Process QC result
processQcResult(QualityControl $qc): void
// If passed_quantity > 0: update InventoryStock
// If rejected_quantity > 0: create PurchaseReturn

// Complete QC
completeQc(QualityControl $qc, int $passedQty, int $rejectedQty): void
```

---

## 3. OBSERVER REFERENCE

### 3.1 Observer Trigger Matrix

| Model Event | Observer | Side Effects |
|-------------|----------|-------------|
| StockMovement::created | StockMovementObserver | Update InventoryStock.qty_available |
| StockReservation::created | StockReservationObserver | Update InventoryStock.qty_reserved (-available, +reserved) |
| StockReservation::deleted | StockReservationObserver | Update InventoryStock.qty_reserved (+available, -reserved) |
| Invoice::created | InvoiceObserver | Create AccountReceivable atau AccountPayable |
| Invoice::updated | InvoiceObserver | Update AR/AP amounts |
| CustomerReceipt::created | CustomerReceiptObserver | Post journal entries, update AR |
| CustomerReceiptItem::created | CustomerReceiptItemObserver | Update AR.paid, AR.remaining |
| VendorPaymentDetail::created | VendorPaymentDetailObserver | Update AP.paid, AP.remaining |
| VendorPayment::created | VendorPaymentObserver | Post AP payment journal |
| SaleOrder::updated (status→approved) | SaleOrderObserver | Create WarehouseConfirmation |
| WarehouseConfirmation::updated (status→confirmed) | WarehouseConfirmation model | Create DeliveryOrder |
| QualityControl::updated (status→completed) | QualityControlObserver | Route to stock or purchase return |
| MaterialIssue::updated (status→approved) | MaterialIssueObserver | Post WIP journal |
| Production::updated (status→finished) | ProductionObserver | Post Finished Goods journal |
| PurchaseReceipt::updated (status→completed) | PurchaseReceiptObserver | Cascade to QC, update PO |
| Asset::created | AssetObserver | Calculate depreciation schedule |
| Deposit::created | DepositObserver | Post deposit journal |
| DepositLog::created | DepositLogObserver | Update Deposit.used_amount, remaining |
| CashBankTransfer::created | CashBankTransferObserver | Post debit/credit journal entries |

---

## 4. PERMISSION REFERENCE

### 4.1 Pattern Nama Permission

```
{verb} {module}
```

**Verbs:** `create`, `view`, `edit`, `delete`, `approve`, `request-approve`, `response`, `manage`

### 4.2 Permission List per Modul

#### Sales Module
```
create quotation
view quotation
edit quotation
delete quotation
request-approve quotation
approve quotation
reject quotation

create sale order
view sale order
edit sale order
delete sale order
request-approve sale order
approve sale order
cancel sale order

create delivery order
view delivery order
edit delivery order
delete delivery order
approve delivery order

create surat jalan
view surat jalan
terbit surat jalan
```

#### Procurement Module
```
create order request
approve order request
create purchase order
approve purchase order
response purchase order
create purchase receipt
view purchase receipt
create quality control purchase
view quality control purchase
create purchase return
view purchase return
create payment request
approve payment request
create vendor payment
view vendor payment
```

#### Finance Module
```
view invoice
create invoice
edit invoice
delete invoice
create customer receipt
view customer receipt
create journal entry
view journal entry
view account receivable
view account payable
create cash bank transaction
view cash bank transaction
create voucher request
approve voucher request
view balance sheet
view profit and loss
view cash flow
```

#### Manufacturing Module
```
create bill of material
create production plan
create manufacturing order
create material issue
approve material issue
create production
```

#### Inventory Module
```
view inventory stock
create stock transfer
approve stock transfer
create stock adjustment
create stock opname
approve stock opname
```

#### Asset Module
```
create asset
view asset
create asset disposal
create asset transfer
```

#### Master Data Module
```
create customer
edit customer
delete customer
create supplier
edit supplier
create product
edit product
create warehouse
create cabang
manage users
manage roles
manage permissions
```

---

## 5. KONFIGURASI FILAMENT PANEL

### 5.1 Admin Panel Provider

**File:** `app/Providers/Filament/AdminPanelProvider.php`

| Konfigurasi | Nilai |
|-------------|-------|
| Panel ID | `admin` |
| Path | `/admin` |
| Login page | Filament default |
| Dashboard | `FinanceDashboard` (custom) |
| Colors | Default Filament + custom brand |
| Dark mode | Enabled |
| Notifications | Database + Livewire |

---

## 6. EVENT & LISTENER REFERENCE

### 6.1 Event Bindings

**File:** `app/Providers/EventServiceProvider.php`

| Event | Listener | Trigger |
|-------|----------|---------|
| (Standard Laravel events) | (Standard listeners) | Auth events, email verification |

---

## 7. HELPER FUNCTIONS REFERENCE

**File:** `app/Helpers/helpers.php`

```php
// Format angka ke format Indonesia (titik ribuan, koma desimal)
formatAmount(float|int $amount, int $decimals = 2): string
// Example: formatAmount(1500000) → "1.500.000,00"

// Format tanpa desimal
formatAmountNoDecimal(float|int $amount): string
// Example: formatAmountNoDecimal(1500000) → "1.500.000"

// Format dengan prefix Rp
formatCurrency(float|int $amount): string
// Example: formatCurrency(1500000) → "Rp 1.500.000"
```

**File:** `app/Helpers/MoneyHelper.php`

```php
// Format Rupiah
MoneyHelper::rupiah(float|int $amount): string
// Example: MoneyHelper::rupiah(1500000) → "Rp 1.500.000"

// Format dengan desimal
MoneyHelper::rupiahDecimal(float|int $amount): string
// Example: MoneyHelper::rupiahDecimal(1500000.50) → "Rp 1.500.000,50"
```

---

*Dokumen API & Service Reference ini dibuat pada 14 Maret 2026.*
