# DOKUMENTASI LENGKAP: PURCHASE ORDER FLOW & PAYMENT

**Sistem ERP Duta Tunggal**  
**Tanggal Pembuatan**: 9 Desember 2025  
**Versi**: 1.0

---

## DAFTAR ISI

1. [Overview Purchase Order Flow](#1-overview-purchase-order-flow)
2. [Struktur Database](#2-struktur-database)
3. [Model Relationships](#3-model-relationships)
4. [Flow Detail Purchase Order](#4-flow-detail-purchase-order)
5. [Flow Pembayaran (Vendor Payment)](#5-flow-pembayaran-vendor-payment)
6. [Journal Entries Creation](#6-journal-entries-creation)
7. [Status Lifecycle](#7-status-lifecycle)
8. [Observers & Business Logic](#8-observers--business-logic)
9. [Impact Analysis](#9-impact-analysis)

---

## 1. OVERVIEW PURCHASE ORDER FLOW

### 1.1 Alur Umum
```
Order Request (Optional) 
    ↓
Purchase Order (PO) 
    ↓
PO Approval 
    ↓
Purchase Receipt (PR) 
    ↓
Quality Control (QC) 
    ↓
Stock Update 
    ↓
Purchase Invoice 
    ↓
Account Payable (AP) 
    ↓
Vendor Payment 
    ↓
Journal Entries
```

### 1.2 Actors/Peran
- **Requester**: Membuat Order Request
- **Purchasing**: Membuat & mengelola Purchase Order
- **Approver**: Menyetujui Purchase Order
- **Warehouse**: Menerima barang, melakukan QC
- **Finance**: Membuat Invoice, Vendor Payment
- **System**: Otomatis membuat Journal Entries

---

## 2. STRUKTUR DATABASE

### 2.1 Table: `purchase_orders`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `supplier_id` | int | NO | - | FK ke suppliers |
| `po_number` | varchar(255) | NO | - | Nomor PO (unique) |
| `order_date` | datetime | NO | - | Tanggal pembuatan PO |
| `status` | enum | NO | - | Status PO (lihat detail) |
| `expected_date` | datetime | YES | NULL | Tanggal estimasi pengiriman |
| `total_amount` | decimal(18,2) | NO | 0.00 | Total nilai PO |
| `is_asset` | tinyint(1) | NO | 0 | Flag aset tetap |
| `is_import` | tinyint(1) | NO | 0 | Flag impor |
| `ppn_option` | enum | NO | 'standard' | standard / non_ppn |
| `close_reason` | text | YES | NULL | Alasan close PO |
| `date_approved` | datetime | YES | NULL | Tanggal approval |
| `approved_by` | int | YES | NULL | FK ke users |
| `approval_signature` | text | YES | NULL | Tanda tangan digital |
| `approval_signed_at` | datetime | YES | NULL | Waktu tanda tangan |
| `note` | text | YES | NULL | Catatan PO |
| `close_requested_by` | int | YES | NULL | User request close |
| `close_requested_at` | datetime | YES | NULL | Waktu request close |
| `closed_by` | int | YES | NULL | User yang close |
| `closed_at` | datetime | YES | NULL | Waktu close |
| `completed_at` | datetime | YES | NULL | Waktu completed |
| `completed_by` | int | YES | NULL | User completed |
| `created_by` | int | YES | NULL | User pembuat |
| `refer_model_type` | varchar(255) | YES | NULL | Polymorphic type |
| `refer_model_id` | bigint unsigned | YES | NULL | Polymorphic id |
| `warehouse_id` | int | NO | - | FK ke warehouses |
| `tempo_hutang` | int | NO | - | Tempo pembayaran (hari) |
| `cabang_id` | int | YES | NULL | FK ke cabang |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

**Status Enum Values**:
- `draft` - PO baru dibuat, belum approved
- `approved` - PO sudah diapprove, siap receipt
- `partially_received` - Sebagian barang sudah diterima
- `completed` - Semua barang sudah diterima & QC
- `invoiced` - Invoice sudah dibuat
- `paid` - Sudah dibayar lunas
- `closed` - PO ditutup (cancel/batal)
- `request_close` - Meminta persetujuan close
- `request_approval` - Meminta persetujuan

---

### 2.2 Table: `purchase_order_items`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `purchase_order_id` | bigint unsigned | NO | FK ke purchase_orders |
| `product_id` | int | NO | FK ke products |
| `quantity` | int | NO | Jumlah order |
| `unit_price` | decimal(18,2) | NO | Harga per unit |
| `discount` | decimal(18,2) | NO | Diskon |
| `tax` | decimal(18,2) | NO | Pajak |
| `tipe_pajak` | varchar(255) | NO | Non Pajak/Inklusif/Eklusif |
| `refer_item_model_id` | bigint unsigned | YES | Polymorphic (SaleOrderItem, OrderRequestItem) |
| `refer_item_model_type` | varchar(255) | YES | Polymorphic type |
| `currency_id` | int | NO | FK ke currencies |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

**Calculated Fields**:
- `subtotal` = `quantity` × `unit_price` - `discount`
- `total` = `subtotal` + `tax`

---

### 2.3 Table: `purchase_order_biayas`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `purchase_order_id` | bigint unsigned | NO | FK ke purchase_orders |
| `currency_id` | int | NO | FK ke currencies |
| `coa_id` | bigint unsigned | YES | FK ke chart_of_accounts |
| `nama_biaya` | varchar(255) | NO | Nama biaya |
| `total` | decimal(18,2) | NO | Nilai biaya |
| `untuk_pembelian` | varchar(255) | NO | Non Pajak / Pajak |
| `masuk_invoice` | tinyint(1) | NO | Apakah masuk invoice |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.4 Table: `purchase_order_currencies`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `purchase_order_id` | bigint unsigned | NO | FK ke purchase_orders |
| `currency_id` | int | NO | FK ke currencies |
| `nominal` | decimal(18,2) | NO | Nominal dalam mata uang |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.5 Table: `purchase_receipts`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `receipt_number` | varchar(255) | NO | Nomor penerimaan |
| `purchase_order_id` | bigint unsigned | NO | FK ke purchase_orders |
| `receipt_date` | date | NO | Tanggal penerimaan |
| `received_by` | int | NO | FK ke users |
| `notes` | text | YES | Catatan |
| `currency_id` | int | NO | FK ke currencies |
| `other_cost` | decimal(18,2) | YES | Biaya lain-lain |
| `status` | enum | NO | draft/partial/completed |
| `cabang_id` | int | YES | FK ke cabang |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.6 Table: `purchase_receipt_items`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `purchase_receipt_id` | bigint unsigned | NO | FK ke purchase_receipts |
| `purchase_order_item_id` | bigint unsigned | YES | FK ke purchase_order_items |
| `product_id` | int | NO | FK ke products |
| `qty_received` | int | NO | Qty diterima |
| `qty_accepted` | int | NO | Qty diterima setelah QC |
| `qty_rejected` | int | YES | Qty reject |
| `reason_rejected` | text | YES | Alasan reject |
| `warehouse_id` | int | NO | FK ke warehouses |
| `is_sent` | tinyint(1) | NO | Sudah dikirim ke QC |
| `rak_id` | bigint unsigned | YES | FK ke raks |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.7 Table: `invoices` (Purchase Invoice)

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `invoice_number` | varchar(255) | NO | Nomor invoice |
| `from_model_type` | varchar(255) | NO | Polymorphic: PurchaseOrder / SaleOrder |
| `from_model_id` | bigint unsigned | NO | Polymorphic id |
| `customer_name` | varchar(255) | YES | Nama customer/supplier |
| `customer_phone` | varchar(255) | YES | No telp |
| `invoice_date` | date | NO | Tanggal invoice |
| `due_date` | date | YES | Tanggal jatuh tempo |
| `subtotal` | decimal(18,2) | NO | Subtotal |
| `tax` | decimal(18,2) | NO | Pajak |
| `ppn_rate` | decimal(5,2) | YES | Persentase PPN |
| `total` | decimal(18,2) | NO | Total invoice |
| `notes` | text | YES | Catatan |
| `status` | enum | NO | draft/sent/paid/cancelled |
| `cabang_id` | int | YES | FK ke cabang |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

**Catatan**: Purchase Invoice menggunakan `from_model_type = 'App\Models\PurchaseOrder'`

---

### 2.8 Table: `account_payables`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `invoice_id` | bigint unsigned | NO | FK ke invoices |
| `supplier_id` | int | NO | FK ke suppliers |
| `total` | decimal(18,2) | NO | Total hutang |
| `paid` | decimal(18,2) | NO | Sudah dibayar |
| `remaining` | decimal(18,2) | NO | Sisa hutang |
| `status` | enum | NO | Belum Lunas / Lunas |
| `cabang_id` | int | YES | FK ke cabang |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.9 Table: `vendor_payments`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `supplier_id` | int | NO | - | FK ke suppliers |
| `selected_invoices` | json | YES | NULL | Array invoice IDs yang dipilih |
| `invoice_receipts` | json | YES | NULL | Detail pembayaran per invoice |
| `ntpn` | varchar(255) | YES | NULL | Nomor Transaksi Penerimaan Negara |
| `payment_date` | date | YES | NULL | Tanggal pembayaran |
| `total_payment` | decimal(18,2) | NO | - | Total pembayaran |
| `coa_id` | bigint unsigned | YES | NULL | FK ke chart_of_accounts (Cash/Bank) |
| `payment_method` | varchar(255) | YES | NULL | Cash/Bank Transfer/Check/Giro/Other |
| `is_import_payment` | tinyint(1) | NO | 0 | Flag pembayaran impor |
| `ppn_import_amount` | decimal(18,2) | NO | 0.00 | PPN impor |
| `pph22_amount` | decimal(18,2) | NO | 0.00 | PPh 22 |
| `bea_masuk_amount` | decimal(18,2) | NO | 0.00 | Bea masuk |
| `notes` | text | YES | NULL | Catatan pembayaran |
| `status` | enum | NO | 'Draft' | Draft/Partial/Paid |
| `diskon` | bigint | NO | 0 | Diskon dalam Rupiah |
| `payment_adjustment` | decimal(18,2) | NO | 0.00 | Adjustment pembayaran |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

---

### 2.10 Table: `vendor_payment_details`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `vendor_payment_id` | bigint unsigned | NO | FK ke vendor_payments |
| `invoice_id` | bigint unsigned | NO | FK ke invoices |
| `method` | varchar(255) | YES | Metode pembayaran |
| `amount` | decimal(18,2) | NO | Jumlah dibayar |
| `adjustment_amount` | decimal(18,2) | YES | Adjustment |
| `balance_amount` | decimal(18,2) | YES | Saldo |
| `coa_id` | bigint unsigned | YES | FK ke chart_of_accounts |
| `payment_date` | date | YES | Tanggal bayar |
| `notes` | text | YES | Catatan |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.11 Table: `journal_entries`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `coa_id` | bigint unsigned | NO | FK ke chart_of_accounts |
| `date` | date | NO | Tanggal jurnal |
| `reference` | varchar(255) | YES | Referensi (PO-xxx, VP-xxx) |
| `description` | text | YES | Deskripsi |
| `debit` | decimal(18,2) | NO | Nilai debit |
| `credit` | decimal(18,2) | NO | Nilai kredit |
| `journal_type` | varchar(255) | YES | purchase/payment/etc |
| `source_type` | varchar(255) | YES | Polymorphic type |
| `source_id` | bigint unsigned | YES | Polymorphic id |
| `cabang_id` | int | YES | FK ke cabang |
| `department_id` | int | YES | FK ke departments |
| `project_id` | int | YES | FK ke projects |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

## 3. MODEL RELATIONSHIPS

### 3.1 PurchaseOrder Model

```php
// Located: app/Models/PurchaseOrder.php

// Relationships
- supplier() : belongsTo(Supplier)
- warehouse() : belongsTo(Warehouse)
- purchaseOrderItem() : hasMany(PurchaseOrderItem)
- purchaseOrderBiaya() : hasMany(PurchaseOrderBiaya)
- purchaseOrderCurrency() : hasMany(PurchaseOrderCurrency)
- purchaseReceipt() : hasMany(PurchaseReceipt)
- invoice() : morphMany(Invoice, 'from_model')
- journalEntries() : morphMany(JournalEntry, 'source')
- assets() : hasMany(Asset)
- approvedBy() : belongsTo(User, 'approved_by')
- createdBy() : belongsTo(User, 'created_by')
- closeRequestedBy() : belongsTo(User)
- closedBy() : belongsTo(User)
- completedBy() : belongsTo(User)
- referModel() : morphTo('refer_model') // OrderRequest atau SaleOrder
```

### 3.2 PurchaseOrderItem Model

```php
// Located: app/Models/PurchaseOrderItem.php

// Relationships
- purchaseOrder() : belongsTo(PurchaseOrder)
- product() : belongsTo(Product)
- currency() : belongsTo(Currency)
- referItemModel() : morphTo() // SaleOrderItem, OrderRequestItem
- purchaseReceiptItem() : hasMany(PurchaseReceiptItem)
```

### 3.3 VendorPayment Model

```php
// Located: app/Models/VendorPayment.php

// Relationships
- supplier() : belongsTo(Supplier)
- coa() : belongsTo(ChartOfAccount) // Cash/Bank account
- vendorPaymentDetail() : hasMany(VendorPaymentDetail)
- journalEntries() : morphMany(JournalEntry, 'source')
- deposits() : hasMany(Deposit)

// Computed Attributes
- getCalculatedTotalAttribute() : sum dari vendorPaymentDetail
- getReferenceAttribute() : ntpn atau 'VP-{id}'
```

### 3.4 VendorPaymentDetail Model

```php
// Located: app/Models/VendorPaymentDetail.php

// Relationships
- vendorPayment() : belongsTo(VendorPayment)
- invoice() : belongsTo(Invoice)
- coa() : belongsTo(ChartOfAccount)
- depositLog() : morphMany(DepositLog, 'reference')
```

---

## 4. FLOW DETAIL PURCHASE ORDER

### 4.1 Step 1: Membuat Purchase Order (Draft)

**Trigger**: User membuat PO baru dari Filament Resource

**Action**:
1. User mengisi form PO:
   - Supplier
   - Warehouse tujuan
   - Tanggal order & expected date
   - Tempo hutang
   - Note
   - Items (product, quantity, unit_price, tax, discount)

2. System generates PO number
   - Format: `PO-{YYYYMMDD}-{sequence}`
   - Example: `PO-20251209-0001`

3. PO disimpan dengan status `draft`

**Database Changes**:
```sql
INSERT INTO purchase_orders (
    supplier_id, po_number, order_date, status, 
    expected_date, warehouse_id, tempo_hutang, created_by
) VALUES (...);

INSERT INTO purchase_order_items (
    purchase_order_id, product_id, quantity, 
    unit_price, discount, tax, currency_id
) VALUES (...);
```

**Impact**:
- ✅ Record PO dibuat
- ✅ Record PO Items dibuat
- ❌ Journal entry BELUM dibuat (masih draft)
- ❌ Stock BELUM berubah
- ❌ Account Payable BELUM dibuat

**Status**: `draft`

---

### 4.2 Step 2: Approval Purchase Order

**Trigger**: User dengan permission meng-approve PO

**Action**:
1. User mereview PO
2. User meng-approve dengan/tanpa digital signature
3. System update status menjadi `approved`
4. System mencatat:
   - `date_approved`
   - `approved_by`
   - `approval_signature` (jika ada)
   - `approval_signed_at`

**Observer Trigger**: `PurchaseOrderObserver@updated()`

```php
// app/Observers/PurchaseOrderObserver.php

if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === 'approved') {
    // Jika is_asset = true, auto-create assets
    if ($purchaseOrder->is_asset) {
        $this->handleAssetPurchaseApproval($purchaseOrder);
    }
}
```

**Special Case - Asset Purchase**:
Jika `is_asset = 1`, system otomatis:
1. Set status PO ke `completed`
2. Auto-create Asset records untuk setiap item
3. Set asset purchase_date = PO order_date
4. Calculate depreciation schedule

**Database Changes**:
```sql
UPDATE purchase_orders SET
    status = 'approved',
    date_approved = NOW(),
    approved_by = {user_id}
WHERE id = {po_id};

-- Jika is_asset = 1:
INSERT INTO assets (
    name, code, purchase_date, purchase_cost,
    purchase_order_id, product_id, ...
) VALUES (...);
```

**Impact**:
- ✅ PO status = `approved`
- ✅ PO siap untuk receipt
- ✅ (Asset) Auto-create asset records
- ❌ Journal entry BELUM dibuat
- ❌ Stock BELUM berubah

**Status**: `approved` atau `completed` (jika asset)

---

### 4.3 Step 3: Purchase Receipt (Penerimaan Barang)

**Trigger**: Warehouse menerima barang

**Action**:
1. User membuat Purchase Receipt dari PO
2. Mengisi:
   - Receipt date
   - Items yang diterima (qty_received, qty_accepted, qty_rejected)
   - Warehouse & Rak tujuan
   - Foto penerimaan (optional)
   - Notes

3. Status receipt bisa:
   - `draft` - Belum final
   - `partial` - Sebagian barang diterima
   - `completed` - Semua barang diterima

**Database Changes**:
```sql
INSERT INTO purchase_receipts (
    receipt_number, purchase_order_id, receipt_date,
    received_by, status, currency_id
) VALUES (...);

INSERT INTO purchase_receipt_items (
    purchase_receipt_id, purchase_order_item_id,
    product_id, qty_received, qty_accepted, qty_rejected,
    warehouse_id, rak_id, is_sent
) VALUES (...);
```

**Observer Trigger**: `PurchaseReceiptItemObserver@created()`

```php
// Ketika receipt item dibuat, system membuat temporary procurement journal

JournalEntry::create([
    'coa_id' => product.temporaryProcurementCoa,
    'debit' => qty_received * unit_price,
    'credit' => 0,
    'description' => 'Temporary Procurement - Receipt {receipt_number}',
    'source_type' => 'PurchaseReceiptItem',
    'source_id' => {receipt_item_id}
]);

JournalEntry::create([
    'coa_id' => unbilledPurchaseCoa, // COA 2116
    'debit' => 0,
    'credit' => qty_received * unit_price,
    ...
]);
```

**Impact**:
- ✅ Receipt record dibuat
- ✅ **Temporary Journal Entry dibuat** (Debit: Procurement In Transit, Credit: Unbilled Purchase)
- ✅ PO status update ke `partially_received` atau tetap `approved`
- ❌ Stock BELUM masuk warehouse (masih QC)
- ❌ Invoice BELUM dibuat

**Status PO**: `partially_received` (jika sebagian) atau tetap `approved`

---

### 4.4 Step 4: Quality Control (QC)

**Trigger**: Warehouse melakukan QC

**Action**:
1. User membuat Quality Control record untuk receipt item
2. Mengisi:
   - QC date
   - Inspector
   - Criteria (Visual, Functional, Performance, etc)
   - Status: Pass/Fail
   - Notes

3. Saat QC completed:
   - Stock masuk warehouse
   - Inventory stock bertambah
   - Journal entries dibuat

**Observer Trigger**: `QualityControlObserver@updated()`

```php
if ($qualityControl->wasChanged('status') && $qualityControl->status === 'pass') {
    // Add stock to inventory
    $receiptItem->qty_accepted = $qualityControl->qty_accepted;
    
    // Create/update inventory_stocks
    InventoryStock::updateOrCreate(
        [
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'rak_id' => $rak_id
        ],
        [
            'quantity' => DB::raw('quantity + ' . $qty_accepted)
        ]
    );
    
    // Post inventory journal entries
    $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);
}
```

**Journal Entries Created**:

```
SAAT QC PASS:

1. Reverse Temporary Procurement:
   Dr. Unbilled Purchase (2116)        xxx
   Cr. Procurement In Transit (1xxx)      xxx

2. Capitalize to Inventory:
   Dr. Inventory (1101.01)             xxx
   Cr. Unbilled Purchase (2116)           xxx
```

**Database Changes**:
```sql
INSERT INTO quality_controls (
    purchase_receipt_item_id, qc_date, inspector_id,
    qty_inspected, qty_accepted, qty_rejected,
    status, notes
) VALUES (...);

-- Saat QC pass:
UPDATE inventory_stocks SET
    quantity = quantity + {qty_accepted}
WHERE product_id = {id} AND warehouse_id = {id};

INSERT INTO journal_entries (...); -- Multiple entries
```

**Impact**:
- ✅ QC record dibuat
- ✅ Stock masuk warehouse (inventory_stocks bertambah)
- ✅ **Journal Entry dibuat** (Inventory & Unbilled Purchase)
- ✅ PO status bisa update ke `completed` (jika semua item QC pass)
- ❌ Invoice BELUM dibuat

**Status PO**: `completed` (jika semua barang sudah QC pass)

---

### 4.5 Step 5: Purchase Invoice Creation

**Trigger**: Finance membuat invoice dari PO

**Action**:
1. System generate invoice dari PO yang sudah `completed`
2. Invoice number auto-generated
   - Format: `PINV-{YYYYMMDD}-{sequence}`
   - Example: `PINV-20251209-0001`

3. Invoice details:
   - from_model_type = 'App\Models\PurchaseOrder'
   - from_model_id = {po_id}
   - Items dari PO items
   - Subtotal, Tax (PPN), Total
   - Due date = invoice_date + tempo_hutang

**Observer Trigger**: `InvoiceObserver@created()`

```php
// Saat invoice created dengan status 'sent' atau 'paid':

if ($invoice->status === 'sent' || $invoice->status === 'paid') {
    // Create Account Payable
    AccountPayable::create([
        'invoice_id' => $invoice->id,
        'supplier_id' => $supplier_id,
        'total' => $invoice->total,
        'paid' => 0,
        'remaining' => $invoice->total,
        'status' => 'Belum Lunas'
    ]);
    
    // Post invoice journal entries
    $ledgerService->postInvoice($invoice);
}
```

**Journal Entries Created**:

```
SAAT INVOICE POSTED (status = sent/paid):

1. Reverse Unbilled Purchase & Record Expense/Inventory:
   Dr. Expense/Inventory (tergantung tipe)  xxx
   Cr. Unbilled Purchase (2116)                 xxx

2. Record PPN Masukan (jika ada):
   Dr. PPN Masukan (1104.04)               xxx
   Cr. Account Payable (2110)                  xxx

3. Record Account Payable:
   Dr. Account Payable (contra)            xxx
   Cr. Account Payable (2110)                  xxx
```

**Database Changes**:
```sql
INSERT INTO invoices (
    invoice_number, from_model_type, from_model_id,
    invoice_date, due_date, subtotal, tax, total, status
) VALUES (...);

INSERT INTO invoice_items (
    invoice_id, product_id, quantity, price, total
) VALUES (...);

INSERT INTO account_payables (
    invoice_id, supplier_id, total, paid, remaining, status
) VALUES (...);

INSERT INTO journal_entries (...); -- Multiple entries
```

**Impact**:
- ✅ Invoice record dibuat
- ✅ Account Payable dibuat (status: Belum Lunas)
- ✅ **Journal Entry dibuat** (AP, PPN Masukan, Inventory/Expense)
- ✅ PO status update ke `invoiced`
- ❌ Payment BELUM dilakukan

**Status PO**: `invoiced`

---

## 5. FLOW PEMBAYARAN (VENDOR PAYMENT)

### 5.1 Step 6: Vendor Payment Creation

**Trigger**: Finance membayar invoice

**Action**:
1. User membuat Vendor Payment
2. Memilih supplier
3. System menampilkan unpaid/partially paid invoices
4. User memilih invoice(s) yang akan dibayar
5. Mengisi:
   - Payment date
   - Payment method (Cash/Bank Transfer/Check/Giro/Other)
   - Cash/Bank account (coa_id)
   - Amount per invoice
   - Adjustment (jika ada)
   - NTPN (Nomor Transaksi)
   - Notes

6. Status payment:
   - `Draft` - Belum diposting
   - `Partial` - Pembayaran sebagian
   - `Paid` - Lunas

**Observer Trigger**: `VendorPaymentObserver@created()`

```php
// 1. Create VendorPaymentDetail from selected_invoices
if ($payment->selected_invoices) {
    foreach ($payment->selected_invoices as $invoice_id) {
        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'invoice_id' => $invoice_id,
            'amount' => ...,
            'payment_date' => $payment->payment_date
        ]);
    }
}

// 2. Post journal entries (jika status = Partial atau Paid)
if ($payment->status === 'Partial' || $payment->status === 'Paid') {
    $ledgerService->postVendorPayment($payment);
}

// 3. Update Account Payable & Invoice status
$this->updateAccountPayableAndInvoiceStatus($payment);
```

**Database Changes**:
```sql
INSERT INTO vendor_payments (
    supplier_id, payment_date, ntpn, total_payment,
    coa_id, payment_method, status, selected_invoices,
    invoice_receipts, notes
) VALUES (...);

INSERT INTO vendor_payment_details (
    vendor_payment_id, invoice_id, amount,
    payment_date, method, notes
) VALUES (...);
```

**Impact**:
- ✅ Vendor Payment record dibuat
- ✅ Vendor Payment Detail records dibuat
- ✅ **Journal Entry dibuat** (jika status Partial/Paid)
- ✅ Account Payable updated (paid += amount, remaining -= amount)
- ✅ Invoice status updated (paid/partially_paid)
- ✅ PO status update ke `paid` (jika lunas)

**Status PO**: `paid` (jika semua invoice lunas)

---

### 5.2 VendorPaymentDetail Observer

**Trigger**: `VendorPaymentDetailObserver@created()`

**Action**:
```php
// Update Account Payable
$accountPayable = AccountPayable::where('invoice_id', $detail->invoice_id)->first();

$totalReduction = $detail->amount + ($detail->adjustment_amount ?? 0);

$accountPayable->paid += $totalReduction;
$accountPayable->remaining = max(0, $accountPayable->total - $accountPayable->paid);
$accountPayable->status = ($accountPayable->remaining <= 0.01) ? 'Lunas' : 'Belum Lunas';
$accountPayable->save();

// Sync invoice status
$invoice->status = ($accountPayable->remaining <= 0.01) ? 'paid' : 'partially_paid';
$invoice->save();
```

**Impact**:
- ✅ AP.paid bertambah
- ✅ AP.remaining berkurang
- ✅ AP.status update (Lunas jika remaining <= 0.01)
- ✅ Invoice.status update (paid/partially_paid)

---

### 5.3 Payment Methods & Special Cases

#### 5.3.1 Import Payment (is_import_payment = 1)

Jika pembayaran untuk barang impor, ada field tambahan:
- `ppn_import_amount` - PPN Impor
- `pph22_amount` - PPh 22
- `bea_masuk_amount` - Bea Masuk

**Journal Entries untuk Import Payment**:
```
Dr. Account Payable (2110)              xxx
Dr. PPN Masukan (1104.04)              xxx  (ppn_import_amount)
Dr. PPh 22 Prepaid (1104.xx)           xxx  (pph22_amount)
Dr. Bea Masuk Prepaid (1104.xx)        xxx  (bea_masuk_amount)
Cr. Cash/Bank (11xx)                       xxx (total)
```

#### 5.3.2 Payment Adjustment

Jika ada `adjustment_amount` atau `diskon`:
- Adjustment dicatat di VendorPaymentDetail
- Mengurangi total pembayaran
- Journal entry bisa menggunakan COA "Purchase Discount" atau "Other Income"

---

## 6. JOURNAL ENTRIES CREATION

### 6.1 Timing - Kapan Journal Entry Dibuat

| Event | Journal Entry Created | Status |
|-------|----------------------|--------|
| PO Created (Draft) | ❌ TIDAK | draft |
| PO Approved | ❌ TIDAK | approved |
| Purchase Receipt Created | ✅ YA - Temporary | partially_received |
| Quality Control Pass | ✅ YA - Inventory | completed |
| Invoice Created (sent/paid) | ✅ YA - AP & PPN | invoiced |
| Vendor Payment Posted | ✅ YA - Payment | paid |

---

### 6.2 Journal Entry Details

#### 6.2.1 Purchase Receipt - Temporary Procurement

**Trigger**: `PurchaseReceiptItemObserver@created()`  
**Method**: `PurchaseReceiptService::createTemporaryProcurementEntriesForReceiptItem()`

**When**: Saat barang diterima (receipt item created)

```
Dr. Procurement In Transit (1xxx)      xxx
Cr. Unbilled Purchase (2116)              xxx

Keterangan: Temporary Procurement - Receipt {receipt_number}
Source: PurchaseReceiptItem
```

**COA Used**:
- Debit: `product->temporaryProcurementCoa` (dari product setting)
- Credit: `2116` - Unbilled Purchase

---

#### 6.2.2 Quality Control Pass - Inventory Capitalization

**Trigger**: `QualityControlObserver@updated()` (status = pass)  
**Method**: `PurchaseReceiptService::postItemInventoryAfterQC()`

**When**: Saat QC pass, barang masuk gudang

**Step 1: Reverse Temporary**
```
Dr. Unbilled Purchase (2116)           xxx
Cr. Procurement In Transit (1xxx)         xxx

Keterangan: Reverse Temporary Procurement - QC Pass
```

**Step 2: Capitalize to Inventory**
```
Dr. Inventory (1101.01)                xxx
Cr. Unbilled Purchase (2116)              xxx

Keterangan: Inventory from QC - {product_name}
Source: PurchaseReceiptItem
```

**COA Used**:
- Inventory: `1101.01` atau product-specific inventory COA
- Unbilled Purchase: `2116`

---

#### 6.2.3 Purchase Invoice - Account Payable

**Trigger**: `InvoiceObserver@created()` atau `@updated()` (status = sent/paid)  
**Method**: `LedgerPostingService::postInvoice()`

**When**: Saat invoice diposting (status sent/paid)

**Scenario A: Non-Tax Invoice**
```
Dr. Expense/Inventory (tergantung)     xxx
Cr. Account Payable (2110)                xxx

Keterangan: Purchase invoice - {invoice_number}
Source: Invoice
```

**Scenario B: Tax Invoice (dengan PPN)**
```
1. Main Entry:
Dr. Unbilled Purchase (2116)           xxx (subtotal)
Cr. Account Payable (2110)                xxx (subtotal)

2. PPN Entry:
Dr. PPN Masukan (1104.04)             xxx (tax)
Cr. Account Payable (2110)                xxx (tax)

Keterangan: Purchase invoice - {invoice_number}
Source: Invoice
```

**COA Used**:
- Unbilled Purchase: `2116`
- PPN Masukan: `1104.04`
- Account Payable: `2110`

---

#### 6.2.4 Vendor Payment - Payment Entry

**Trigger**: `VendorPaymentObserver@created()` atau `@updated()` (status = Partial/Paid)  
**Method**: `LedgerPostingService::postVendorPayment()`

**When**: Saat payment diposting (status Partial/Paid)

**Scenario A: Regular Payment**
```
Dr. Account Payable (2110)             xxx
Cr. Cash/Bank (11xx)                      xxx

Keterangan: Vendor payment - {ntpn}
Source: VendorPayment
```

**Scenario B: Import Payment**
```
Dr. Account Payable (2110)             xxx
Dr. PPN Masukan (1104.04)             xxx
Dr. PPh 22 Prepaid (1104.xx)          xxx
Dr. Bea Masuk Prepaid (1104.xx)       xxx
Cr. Cash/Bank (11xx)                      xxx (total)

Keterangan: Import payment - {ntpn}
Source: VendorPayment
```

**Scenario C: Payment with Discount**
```
Dr. Account Payable (2110)             xxx
Cr. Cash/Bank (11xx)                      xxx
Cr. Purchase Discount (8xxx)              xxx

Keterangan: Vendor payment with discount - {ntpn}
Source: VendorPayment
```

**COA Used**:
- Account Payable: `2110`
- Cash/Bank: dari `vendor_payments.coa_id`
- PPN Masukan: `1104.04`
- Purchase Discount: `8xxx` (income account)

---

### 6.3 Journal Entry Sync Mechanism

System memiliki **automatic sync mechanism** untuk menjaga konsistensi journal entries dengan source model.

**Observer**: `PurchaseOrderObserver::syncJournalEntries()`

**Trigger**: Saat PO.total_amount berubah (karena PO item update/delete)

**Action**:
```php
// Update journal entries yang linked ke PO
JournalEntry::where('source_type', 'PurchaseOrder')
    ->where('source_id', $po->id)
    ->update([
        'reference' => 'PO-' . $po->po_number,
        'description' => 'Purchase Order: ' . $po->po_number,
        'date' => $po->order_date,
        'debit' => $po->total_amount // jika debit entry
    ]);
```

**Juga berlaku untuk**:
- `PurchaseOrderItemObserver` - sync saat PO item berubah
- Invoice update - sync journal entries
- Payment update - sync journal entries

---

## 7. STATUS LIFECYCLE

### 7.1 Purchase Order Status Flow

```
draft
  ↓ (User approves)
approved
  ↓ (Warehouse receives partial goods)
partially_received
  ↓ (All goods received & QC pass)
completed
  ↓ (Finance creates invoice)
invoiced
  ↓ (Finance pays invoice)
paid

Alternative flows:
draft → request_approval → approved
approved → request_close → closed
```

### 7.2 Status Transition Rules

| From Status | To Status | Condition | Action |
|-------------|-----------|-----------|--------|
| `draft` | `request_approval` | User requests approval | - |
| `draft` | `approved` | User with permission approves | Update approved_by, date_approved |
| `approved` | `partially_received` | First receipt created | - |
| `partially_received` | `completed` | All items received & QC pass | Update completed_by, completed_at |
| `completed` | `invoiced` | Invoice created | - |
| `invoiced` | `paid` | All invoices paid | - |
| Any | `request_close` | User requests close | Update close_requested_by, close_requested_at |
| `request_close` | `closed` | Manager approves close | Update closed_by, closed_at, close_reason |

---

### 7.3 Purchase Receipt Status

| Status | Description | Impact |
|--------|-------------|--------|
| `draft` | Penerimaan draft, bisa diedit | Tidak ada journal entry |
| `partial` | Sebagian barang diterima | Journal entry temporary |
| `completed` | Semua barang diterima | Journal entry final (setelah QC) |

---

### 7.4 Invoice Status

| Status | Description | Account Payable |
|--------|-------------|-----------------|
| `draft` | Invoice draft | AP belum dibuat |
| `sent` | Invoice sudah dikirim/diposting | AP dibuat, status: Belum Lunas |
| `partially_paid` | Sebagian sudah dibayar | AP.paid > 0, remaining > 0 |
| `paid` | Lunas | AP.status = Lunas, remaining = 0 |
| `cancelled` | Dibatalkan | AP dihapus/reversed |

---

### 7.5 Vendor Payment Status

| Status | Description | Journal Entry |
|--------|-------------|---------------|
| `Draft` | Payment draft | Tidak ada journal |
| `Partial` | Pembayaran sebagian | Journal entry dibuat |
| `Paid` | Pembayaran lunas | Journal entry dibuat |

---

## 8. OBSERVERS & BUSINESS LOGIC

### 8.1 PurchaseOrderObserver

**Location**: `app/Observers/PurchaseOrderObserver.php`

**Events**:

#### created()
- Update total_amount dari items
- **Tidak** membuat journal entry (masih draft)

#### updated()
- Update total_amount jika items berubah
- Handle asset purchase approval (jika is_asset = 1 dan status = approved)
  - Auto-create assets
  - Set status ke completed
  - Calculate depreciation
- Sync journal entries jika total_amount berubah

**Key Methods**:
```php
handleAssetPurchaseApproval($purchaseOrder)
  - Prevent duplicate asset creation
  - Set PO status to completed
  - Auto-create assets for each item
  - Calculate depreciation

syncJournalEntries($purchaseOrder)
  - Update journal reference & description
  - Update journal date
  - Update debit amount (jika simple debit entry)
```

---

### 8.2 PurchaseOrderItemObserver

**Location**: `app/Observers/PurchaseOrderItemObserver.php`

**Events**:

#### saved()
- Trigger sync journal entries di parent PO

#### deleted()
- Trigger sync journal entries di parent PO

**Purpose**: Menjaga konsistensi journal entries saat PO items berubah

---

### 8.3 PurchaseReceiptItemObserver

**Location**: `app/Observers/PurchaseReceiptItemObserver.php`

**Events**:

#### created()
- Create temporary procurement journal entries
- Update PO status menjadi `partially_received`

#### updated()
- Re-post journal entries jika qty berubah

---

### 8.4 QualityControlObserver

**Location**: `app/Observers/QualityControlObserver.php`

**Events**:

#### updated()
- Jika status berubah ke `pass`:
  - Add stock to inventory
  - Post inventory journal entries (reverse temporary + capitalize)
  - Update PO status ke `completed` (jika semua item sudah QC)

---

### 8.5 InvoiceObserver

**Location**: `app/Observers/InvoiceObserver.php`

**Events**:

#### created()
- Jika status = `sent` atau `paid`:
  - Create Account Payable
  - Post invoice journal entries

#### updated()
- Jika status berubah ke `sent` atau `paid`:
  - Create/update Account Payable
  - Post invoice journal entries

---

### 8.6 VendorPaymentObserver

**Location**: `app/Observers/VendorPaymentObserver.php`

**Events**:

#### created()
- Create VendorPaymentDetail dari selected_invoices
- Jika status = `Partial` atau `Paid`:
  - Post payment journal entries
  - Update Account Payable & Invoice status

#### updated()
- Jika status berubah ke `Partial` atau `Paid`:
  - Post payment journal entries
  - Update Account Payable & Invoice status

#### deleted()
- Reverse Account Payable updates
- Soft delete journal entries
- Soft delete payment details

**Key Methods**:
```php
updateAccountPayableAndInvoiceStatus($payment)
  - Recalculate AP.paid & AP.remaining
  - Update AP.status (Lunas/Belum Lunas)
  - Sync invoice status (paid/partially_paid)

reverseAccountPayableAndInvoiceStatus($payment)
  - Subtract payment amount from AP.paid
  - Add to AP.remaining
  - Revert invoice status
```

---

### 8.7 VendorPaymentDetailObserver

**Location**: `app/Observers/VendorPaymentDetailObserver.php`

**Events**:

#### created()
- Update Account Payable (paid += amount, remaining -= amount)
- Update Invoice status
- Create deposit log (jika ada deposit)

---

## 9. IMPACT ANALYSIS

### 9.1 Saat Membuat Purchase Order

**Database Impact**:
- ✅ purchase_orders +1 record
- ✅ purchase_order_items +N records
- ✅ purchase_order_currencies +N records (jika multi-currency)
- ✅ purchase_order_biayas +N records (jika ada biaya tambahan)

**System Impact**:
- ❌ Inventory stock: TIDAK berubah
- ❌ Journal entries: TIDAK dibuat
- ❌ Account payable: TIDAK dibuat
- ❌ Financial reports: TIDAK terpengaruh

**User Impact**:
- ✅ PO bisa diedit/dihapus
- ✅ PO bisa direview sebelum approval

---

### 9.2 Saat Approve Purchase Order

**Database Impact**:
- ✅ purchase_orders.status = `approved`
- ✅ purchase_orders.approved_by, date_approved updated
- ✅ (Jika asset) assets +N records
- ✅ (Jika asset) depreciation_schedules +N records

**System Impact**:
- ❌ Inventory stock: TIDAK berubah (belum receipt)
- ❌ Journal entries: TIDAK dibuat (belum ada transaksi)
- ✅ (Jika asset) Assets ready for tracking

**User Impact**:
- ⚠️ PO tidak bisa diedit lagi (harus request close)
- ✅ PO siap untuk receipt
- ✅ (Jika asset) Assets muncul di daftar asset

---

### 9.3 Saat Purchase Receipt

**Database Impact**:
- ✅ purchase_receipts +1 record
- ✅ purchase_receipt_items +N records
- ✅ journal_entries +2N records (temporary procurement)

**System Impact**:
- ❌ Inventory stock: BELUM berubah (masih QC)
- ✅ Journal entries: Temporary procurement entries dibuat
  - Dr. Procurement In Transit
  - Cr. Unbilled Purchase
- ✅ Balance Sheet: Temporary procurement muncul

**User Impact**:
- ✅ Barang tercatat diterima
- ✅ Menunggu QC untuk masuk gudang

---

### 9.4 Saat Quality Control Pass

**Database Impact**:
- ✅ quality_controls +1 record
- ✅ inventory_stocks: quantity += qty_accepted
- ✅ journal_entries +4N records (reverse temporary + inventory)
- ✅ purchase_orders.status = `completed` (jika semua item QC)

**System Impact**:
- ✅ Inventory stock: BERTAMBAH
- ✅ Journal entries:
  - Reverse temporary procurement
  - Dr. Inventory, Cr. Unbilled Purchase
- ✅ Balance Sheet: Inventory bertambah
- ✅ Inventory valuation: Bertambah

**User Impact**:
- ✅ Barang tersedia di gudang
- ✅ Bisa dijual/digunakan
- ✅ Muncul di laporan stok

---

### 9.5 Saat Purchase Invoice Created

**Database Impact**:
- ✅ invoices +1 record
- ✅ invoice_items +N records
- ✅ account_payables +1 record
- ✅ journal_entries +2-4 records (AP, PPN)
- ✅ purchase_orders.status = `invoiced`

**System Impact**:
- ✅ Journal entries:
  - Dr. Unbilled Purchase (reverse)
  - Dr. PPN Masukan (jika ada)
  - Cr. Account Payable
- ✅ Balance Sheet: Account Payable bertambah
- ✅ Income Statement: TIDAK terpengaruh (sudah dicatat di inventory)
- ✅ Aging Report: Hutang bertambah

**User Impact**:
- ✅ Invoice muncul di daftar payables
- ✅ Finance bisa memproses pembayaran
- ✅ Supplier bisa ditagih

---

### 9.6 Saat Vendor Payment

**Database Impact**:
- ✅ vendor_payments +1 record
- ✅ vendor_payment_details +N records
- ✅ journal_entries +2-6 records (payment entries)
- ✅ account_payables: paid += amount, remaining -= amount
- ✅ invoices.status = `paid` atau `partially_paid`
- ✅ purchase_orders.status = `paid` (jika lunas)

**System Impact**:
- ✅ Journal entries:
  - Dr. Account Payable
  - Cr. Cash/Bank
  - (Import) Dr. PPN Masukan, PPh 22, Bea Masuk
- ✅ Balance Sheet:
  - Account Payable berkurang
  - Cash/Bank berkurang
- ✅ Cash Flow Statement: Cash outflow
- ✅ Aging Report: Hutang berkurang

**User Impact**:
- ✅ Payment tercatat
- ✅ Account Payable berkurang
- ✅ Invoice status updated
- ✅ Muncul di payment history

---

### 9.7 Dampak ke Laporan Keuangan

#### Balance Sheet (Neraca)

| Account | Debit | Credit | Event |
|---------|-------|--------|-------|
| Procurement In Transit (1xxx) | +xxx | | Receipt created |
| Unbilled Purchase (2116) | | +xxx | Receipt created |
| Unbilled Purchase (2116) | +xxx | | QC pass (reverse) |
| Procurement In Transit (1xxx) | | +xxx | QC pass (reverse) |
| Inventory (1101.01) | +xxx | | QC pass |
| Unbilled Purchase (2116) | | +xxx | QC pass |
| Unbilled Purchase (2116) | +xxx | | Invoice created |
| PPN Masukan (1104.04) | +xxx | | Invoice created |
| Account Payable (2110) | | +xxx | Invoice created |
| Account Payable (2110) | +xxx | | Payment |
| Cash/Bank (11xx) | | +xxx | Payment |

**Net Effect**:
- ↑ Inventory (asset)
- ↑ Account Payable (liability) → kemudian ↓ saat bayar
- ↓ Cash/Bank (asset) saat bayar

#### Income Statement (Laba Rugi)

**Tidak ada dampak langsung** karena:
- Purchase dicatat sebagai Inventory (asset), bukan expense
- Expense baru diakui saat barang dijual (COGS)

**Kecuali**:
- Jika PO untuk expense items (bukan inventory), maka:
  - Dr. Expense, Cr. AP
  - Expense langsung masuk Income Statement

#### Cash Flow Statement

| Event | Impact | Category |
|-------|--------|----------|
| Purchase Receipt | Tidak ada | - |
| Invoice Created | Tidak ada | - |
| Vendor Payment | - Cash outflow | Operating Activities |

**Operating Activities**:
- Payment to suppliers: -xxx

---

### 9.8 Dampak ke Inventory Management

#### Inventory Stock Movement

```
1. Purchase Receipt Created:
   - Qty: NOT in warehouse (still QC)
   - Location: Procurement In Transit

2. QC Pass:
   - Qty: IN warehouse
   - Location: warehouse_id + rak_id
   - Status: Available

3. QC Fail:
   - Qty: Rejected
   - Return to supplier or dispose
```

#### Inventory Valuation

**Method**: Weighted Average atau FIFO (tergantung config)

**Saat QC Pass**:
```
New Avg Cost = (Old Stock × Old Cost + New Qty × New Cost) / (Old Stock + New Qty)
```

**Impact**:
- ✅ Inventory value bertambah
- ✅ Avg cost mungkin berubah
- ✅ Mempengaruhi COGS di sales

---

### 9.9 Dampak ke Tax Compliance

#### PPN (Pajak Pertambahan Nilai)

**Flow**:
1. Purchase dengan PPN → dicatat di PPN Masukan (asset)
2. Sales dengan PPN → dicatat di PPN Keluaran (liability)
3. Akhir periode: PPN Keluaran - PPN Masukan = PPN terutang

**Journal Entry**:
```
Saat Purchase Invoice:
Dr. PPN Masukan (1104.04)  xxx
Cr. Account Payable            xxx
```

#### PPh 22 (Import)

**Jika pembayaran impor**:
```
Dr. PPh 22 Prepaid (1104.xx)  xxx
Cr. Cash/Bank                    xxx
```

**PPh 22 bisa dikreditkan** di SPT Tahunan

---

### 9.10 Error Scenarios & Rollback

#### Scenario 1: Payment Deleted

**VendorPaymentObserver::deleted()**:
```php
// 1. Reverse Account Payable
AP.paid -= payment.amount
AP.remaining += payment.amount
AP.status = (remaining > 0) ? 'Belum Lunas' : 'Lunas'

// 2. Reverse Invoice status
invoice.status = (AP.remaining > 0) ? 'partially_paid' : 'sent'

// 3. Soft delete journal entries
JournalEntry::where('source_type', VendorPayment::class)
    ->where('source_id', $payment->id)
    ->delete();

// 4. Soft delete payment details
$payment->vendorPaymentDetail()->delete();
```

**Impact**:
- ✅ AP kembali ke status sebelumnya
- ✅ Invoice status reverted
- ✅ Journal entries di-soft delete
- ✅ Cash/Bank tidak ter-reverse (manual adjustment)

#### Scenario 2: PO Item Deleted

**PurchaseOrderItemObserver::deleted()**:
```php
// 1. Recalculate PO total_amount
$po->total_amount = $po->purchaseOrderItem()->sum('total');

// 2. Sync journal entries
syncJournalEntries($po);
```

**Impact**:
- ✅ PO total_amount updated
- ✅ Journal entries updated (jika ada)

#### Scenario 3: QC Rejected

**Manual Process**:
1. Create Purchase Return
2. Update inventory stock (qty -= qty_rejected)
3. Create return journal entries:
   ```
   Dr. Account Payable / Supplier Refund
   Cr. Inventory
   ```

---

## KESIMPULAN

### Key Takeaways:

1. **Purchase Order Flow** memiliki 6 tahap utama:
   - Draft → Approval → Receipt → QC → Invoice → Payment

2. **Journal Entries** dibuat di 4 titik:
   - Purchase Receipt: Temporary procurement
   - QC Pass: Inventory capitalization
   - Invoice: Account Payable & PPN
   - Payment: Cash/Bank & AP reduction

3. **Status Lifecycle** terstruktur dengan observer yang menjaga konsistensi data

4. **Impact Analysis** menunjukkan dampak ke:
   - Balance Sheet (Inventory, AP, Cash)
   - Cash Flow Statement (Operating activities)
   - Tax Compliance (PPN, PPh 22)

5. **Automatic Sync Mechanism** menjaga journal entries selalu konsisten dengan source model

---

**Dibuat oleh**: GitHub Copilot  
**Untuk**: Sistem ERP Duta Tunggal  
**Tanggal**: 9 Desember 2025  
**Versi**: 1.0

---

*Dokumentasi ini dapat diupdate seiring perubahan sistem.*
