# DUTA TUNGGAL ERP — Database Schema Document
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## 1. GAMBARAN DATABASE

| Properti | Nilai |
|----------|-------|
| **DBMS** | MySQL (production) / SQLite (testing) |
| **Database** | u1605090_duta_tunggal |
| **Charset** | utf8mb4 |
| **Collation** | utf8mb4_unicode_ci |
| **Schema Base** | database/schema/mysql-schema.sql (squashed) |
| **Total Migrasi** | 45 file incremental |

---

## 2. DIAGRAM RELASI ENTITAS (ERD OVERVIEW)

### 2.1 Entitas Inti & Hubungan Utama

```
┌─────────────┐         ┌─────────────────────┐         ┌─────────────┐
│   customers  │────────►│    sale_orders       │────────►│  quotations │
└─────────────┘  1:N    └─────────────────────┘  N:1    └─────────────┘
       │                          │
       │                          │ N:M (delivery_sales_orders)
       │                          ▼
       │                 ┌─────────────────────┐
       │                 │   delivery_orders    │
       │                 └─────────────────────┘
       │                          │
       │                          ▼
       │                 ┌─────────────────────┐
       │                 │    surat_jalans      │(N:M via surat_jalan_delivery_order)
       │                 └─────────────────────┘
       │
       └─────►┌─────────────────────┐
              │      invoices        │
              └─────────────────────┘
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
    ┌──────────────────┐  ┌───────────────────┐
    │account_receivables│  │  customer_receipts│
    └──────────────────┘  └───────────────────┘
```

```
┌─────────────┐         ┌─────────────────────┐         ┌──────────────────┐
│  suppliers   │────────►│   purchase_orders    │◄────────│  order_requests  │
└─────────────┘  1:N    └─────────────────────┘  N:1    └──────────────────┘
       │                          │
       │                          ▼
       │                 ┌─────────────────────┐
       │                 │  purchase_receipts   │
       │                 └─────────────────────┘
       │                          │
       │                          ▼
       │                 ┌─────────────────────┐
       │                 │   quality_controls   │
       │                 └─────────────────────┘
       │                   passed │   rejected
       │                         │         ▼
       │                         ▼    ┌───────────────┐
       │                  ┌──────────┐│purchase_returns│
       │                  │inventory │└───────────────┘
       │                  │_stocks   │
       │                  └──────────┘
       │
       └─────►┌─────────────────────┐
              │      invoices        │(PurchaseInvoice)
              └─────────────────────┘
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
    ┌──────────────────┐  ┌───────────────────┐
    │ account_payables  │  │  vendor_payments  │
    └──────────────────┘  └───────────────────┘
```

---

## 3. TABEL DATABASE — DETAIL

### 3.1 Tabel Master Data

#### `cabangs`
```sql
id, kode, nama, alamat, telepon, kenaikan_harga, status, tipe_penjualan,
kode_invoice_pajak, kode_invoice_non_pajak, nama_kwitansi,
logo_invoice_non_pajak, lihat_stok_cabang_lain, created_at, updated_at
```

#### `warehouses`
```sql
id, kode, name, cabang_id (FK→cabangs), location, telepon,
tipe, status, warna_background, created_at, updated_at
```

#### `raks`
```sql
id, warehouse_id (FK→warehouses), [nama_rak, kode, etc.], created_at, updated_at
```

#### `unit_of_measures`
```sql
id, name, code, created_at, updated_at
```

#### `product_categories`
```sql
id, name, code, description, created_at, updated_at
```

#### `products`
```sql
id, name, sku, product_category_id (FK→product_categories),
cabang_id (FK→cabangs), cost_price, sell_price, biaya, harga_batas,
tipe_pajak, pajak, uom_id (FK→unit_of_measures),
is_manufacture, is_raw_material, is_active,
inventory_coa_id, sales_coa_id, sales_return_coa_id, cogs_coa_id,
purchase_return_coa_id, unbilled_purchase_coa_id,
temporary_procurement_coa_id,
created_at, updated_at
```

#### `product_supplier` (pivot)
```sql
id, product_id (FK→products), supplier_id (FK→suppliers),
unit_price, created_at, updated_at
```

#### `product_unit_conversions`
```sql
id, product_id, from_uom_id, to_uom_id, conversion_factor, created_at, updated_at
```

#### `customers`
```sql
id, name, code, address, telephone, phone, email, perusahaan,
tipe (PKP/PRI), fax, isSpecial, tempo_kredit, kredit_limit,
tipe_pembayaran, nik_npwp, cabang_id (FK→cabangs), created_at, updated_at
```

#### `suppliers`
```sql
id, code, perusahaan, address, phone, email, handphone, fax, npwp,
tempo_hutang, kontak_person, cabang_id (FK→cabangs), created_at, updated_at
```

#### `drivers`
```sql
id, name, phone, license_number, cabang_id, created_at, updated_at
```

#### `vehicles`
```sql
id, name, plate_number, type, cabang_id, created_at, updated_at
```

#### `currencies`
```sql
id, code, name, exchange_rate, created_at, updated_at
```

#### `tax_settings`
```sql
id, name, rate, type, created_at, updated_at
```

#### `app_settings`
```sql
id, key, value, created_at, updated_at
```

---

### 3.2 Tabel Penjualan

#### `quotations`
```sql
id, quotation_number, customer_id (FK→customers),
date, valid_until, tempo_pembayaran, total_amount,
status (draft/request_approve/approve/reject),
po_file_path, notes,
created_by (FK→users), request_approve_by (FK→users),
approve_by (FK→users), reject_by (FK→users),
cabang_id (FK→cabangs), created_at, updated_at
```

#### `quotation_items`
```sql
id, quotation_id (FK→quotations), product_id (FK→products),
quantity, unit_price, discount,
tax_type (default: 'PPN Excluded'), tax,
created_at, updated_at
```

#### `sale_orders`
```sql
id, so_number, customer_id (FK→customers),
quotation_id (FK→quotations, nullable),
order_date, delivery_date, status, tipe_pengiriman,
tempo_pembayaran, total_amount,
created_by (FK→users), request_approve_by, approve_by,
reject_by, closed_by,
cabang_id (FK→cabangs), created_at, updated_at
```

#### `sale_order_items`
```sql
id, sale_order_id (FK→sale_orders), product_id (FK→products),
quantity, unit_price, discount,
tipe_pajak (enum modified Mar 2026),
created_at, updated_at
```

#### `delivery_orders`
```sql
id, do_number, delivery_date,
driver_id (FK→drivers, nullable),
vehicle_id (FK→vehicles, nullable),
warehouse_id (FK→warehouses),
status (draft/sent/received/approved/closed/reject/delivery_failed),
notes, additional_cost,
created_by (FK→users), cabang_id (FK→cabangs),
created_at, updated_at
```

#### `delivery_order_items`
```sql
id, delivery_order_id (FK→delivery_orders),
sale_order_item_id (FK→sale_order_items),
product_id (FK→products), quantity,
created_at, updated_at
```

#### `delivery_sales_orders` (pivot N:M)
```sql
id, delivery_order_id (FK→delivery_orders),
sale_order_id (FK→sale_orders), created_at, updated_at
```

#### `delivery_order_logs`
```sql
id, delivery_order_id, action, description, user_id, created_at, updated_at
```

#### `delivery_order_approval_logs`
```sql
id, delivery_order_id, status, user_id, notes, created_at, updated_at
```

#### `surat_jalans`
```sql
id, sj_number, issued_at, signed_by (FK→users),
status, created_by (FK→users), document_path,
sender_name, shipping_method,
created_at, updated_at
```

#### `surat_jalan_delivery_order` (pivot N:M)
```sql
id, surat_jalan_id, delivery_order_id, created_at, updated_at
```

#### `return_products`
```sql
id, from_model_type, from_model_id,
reason, status, processed_by, processed_at,
created_at, updated_at
```

#### `return_product_items`
```sql
id, return_product_id, product_id, quantity, decision,
created_at, updated_at
```

#### `customer_returns` (baru Mar 2026)
```sql
id, return_number, invoice_id (FK→invoices),
customer_id (FK→customers), cabang_id (FK→cabangs),
warehouse_id (FK→warehouses, nullable),
return_date, reason,
status (pending/received/qc_inspection/approved/rejected/completed),
received_by (FK→users), qc_inspected_by (FK→users),
approved_by (FK→users), stock_restored_at,
completed_at (added Mar 2026),
created_at, updated_at
```

#### `customer_return_items`
```sql
id, customer_return_id (FK→customer_returns),
product_id (FK→products), quantity_returned,
quantity_accepted, quantity_rejected,
decision (accepted/rejected/replace),
reason, created_at, updated_at
```

---

### 3.3 Tabel Pengadaan

#### `order_requests`
```sql
id, request_number, warehouse_id (FK→warehouses),
supplier_id (FK→suppliers, nullable),
cabang_id (FK→cabangs),
request_date, status (draft/approved/rejected/closed),
tax_type (PPN Excluded/PPN Included),
note, created_by (FK→users),
created_at, updated_at
```

#### `order_request_items`
```sql
id, order_request_id (FK→order_requests),
product_id (FK→products),
quantity, unit_price, original_price,
tipe_pajak, tax,
fulfilled_quantity (added Feb 2026),
created_at, updated_at
```

#### `purchase_orders`
```sql
id, po_number, supplier_id (FK→suppliers),
order_date, expected_date,
status (draft/approved/partially_received/completed/closed/request_close),
total_amount, is_asset, warehouse_id (FK→warehouses),
tempo_hutang, note, ppn_option,
cabang_id (FK→cabangs),
refer_model_type, refer_model_id (polymorphic→order_requests),
created_by, approved_by, rejected_by, closed_by (FK→users),
created_at, updated_at
```

#### `purchase_order_items`
```sql
id, purchase_order_id (FK→purchase_orders),
product_id (FK→products),
quantity, unit_price, received_quantity,
created_at, updated_at
```

#### `purchase_order_biayas`
```sql
id, purchase_order_id, description, amount, coa_id, created_at, updated_at
```

#### `purchase_order_currencies`
```sql
id, purchase_order_id, currency_id, exchange_rate,
original_amount, idr_amount, created_at, updated_at
```

#### `purchase_receipts`
```sql
id, receipt_number,
purchase_order_id (FK→purchase_orders, nullable — since Feb 2026),
receipt_date, received_by (FK→users),
notes, currency_id, other_cost,
status (draft/partial/completed),
cabang_id (FK→cabangs),
created_at, updated_at
```

#### `purchase_receipt_items`
```sql
id, purchase_receipt_id (FK→purchase_receipts),
product_id (FK→products),
quantity, status (pendding/qc/received — added Feb 2026),
rak_id (FK→raks), created_at, updated_at
```

#### `purchase_receipt_biayas`
```sql
id, purchase_receipt_id, description, amount, coa_id, created_at, updated_at
```

#### `purchase_receipt_photos`
```sql
id, purchase_receipt_id, photo_path, created_at, updated_at
```

#### `quality_controls`
```sql
id, qc_number, inspected_by (FK→users),
passed_quantity, rejected_quantity, notes, status,
warehouse_id (FK→warehouses), product_id (FK→products), rak_id (FK→raks),
from_model_type, from_model_id (polymorphic),
purchase_return_processed, qc_resolution (added Feb 2026),
created_at, updated_at
```

#### `purchase_returns`
```sql
id, return_number, purchase_receipt_id,
quality_control_id, notes, status,
qc_resolution (added Feb 2026),
created_at, updated_at
```

#### `purchase_return_items`
```sql
id, purchase_return_id, product_id, quantity,
reason, created_at, updated_at
```

#### `payment_requests` (baru Feb 2026)
```sql
id, request_number, supplier_id (FK→suppliers),
cabang_id (FK→cabangs),
requested_by (FK→users), approved_by (FK→users),
request_date, payment_date, total_amount,
selected_invoices (JSON), notes,
status (draft/pending_approval/approved/rejected/paid),
created_at, updated_at
```

#### `vendor_payments`
```sql
id, payment_request_id (FK→payment_requests, added Feb 2026),
supplier_id (FK→suppliers),
selected_invoices (JSON array), invoice_receipts (JSON),
payment_date, ntpn, total_payment (default 0),
coa_id (FK→chart_of_accounts),
payment_method, notes, diskon, status (Draft/Partial/Paid),
is_import_payment, ppn_import_amount, pph22_amount, bea_masuk_amount,
cabang_id (FK→cabangs), created_at, updated_at
```

#### `vendor_payment_details`
```sql
id, vendor_payment_id, invoice_id,
amount, due_date, created_at, updated_at
```

---

### 3.4 Tabel Inventori

#### `inventory_stocks`
```sql
id, product_id (FK→products), warehouse_id (FK→warehouses),
qty_available, qty_reserved, qty_min, rak_id (FK→raks),
created_at, updated_at
```

#### `stock_movements`
```sql
id, product_id (FK→products), warehouse_id (FK→warehouses),
quantity, value, type, reference_id, date, notes,
meta (JSON), rak_id (FK→raks),
from_model_type, from_model_id (polymorphic),
created_at, updated_at
```

#### `stock_reservations`
```sql
id, product_id, warehouse_id, reserved_qty,
source_type, source_id (polymorphic→sale_orders),
created_at, updated_at
```

#### `stock_transfers`
```sql
id, transfer_number,
from_warehouse_id (FK→warehouses), to_warehouse_id (FK→warehouses),
transfer_date, status (Pending/Draft/Approved/Request/Reject/completed/cancelled),
notes, created_by, approved_by (FK→users),
created_at, updated_at
```

#### `stock_transfer_items`
```sql
id, stock_transfer_id, product_id,
quantity, from_rak_id, to_rak_id,
created_at, updated_at
```

#### `stock_adjustments`
```sql
id, adjustment_number, warehouse_id, adjustment_date,
reason, status, created_by, approved_by,
created_at, updated_at
```

#### `stock_adjustment_items`
```sql
id, stock_adjustment_id, product_id, quantity,
type (addition/reduction), notes, created_at, updated_at
```

#### `stock_opnames`
```sql
id, opname_number, opname_date, warehouse_id (FK→warehouses),
status (draft/in_progress/approved), notes,
created_by (FK→users), approved_by (FK→users),
created_at, updated_at
```

#### `stock_opname_items`
```sql
id, stock_opname_id, product_id, system_qty, actual_qty,
difference (computed), created_at, updated_at
```

#### `warehouse_confirmations`
```sql
id, sale_order_id, status (pending/request/confirmed),
notes, created_at, updated_at
```

#### `warehouse_confirmation_items`
```sql
id, warehouse_confirmation_id, sale_order_item_id,
product_id, quantity, created_at, updated_at
```

---

### 3.5 Tabel Keuangan & Akuntansi

#### `chart_of_accounts`
```sql
id, code, name, type (Asset/Liability/Equity/Revenue/Expense/Contra Asset),
parent_id (FK→chart_of_accounts, self-referential),
is_active, is_current, description,
opening_balance, debit, credit, ending_balance,
created_at, updated_at
```

#### `journal_entries`
```sql
id, coa_id (FK→chart_of_accounts), date, reference, description,
debit, credit, journal_type, cabang_id (FK→cabangs),
source_type, source_id (polymorphic),
transaction_id, bank_recon_id (FK→bank_reconciliations),
is_reversal (added Mar 2026),
reversal_of_transaction_id (added Mar 2026),
created_at, updated_at
```

#### `invoices`
```sql
id, invoice_number, from_model_type, from_model_id (polymorphic),
invoice_date, subtotal, tax, other_fee (JSON), total, due_date,
status (draft/sent/paid/partially_paid/overdue),
ppn_rate, dpp, delivery_orders (JSON array), purchase_receipts (JSON array),
purchase_order_ids (JSON array, added Feb 2026),
ar_coa_id, ppn_keluaran_coa_id (FK→chart_of_accounts),
cabang_id (FK→cabangs), created_at, updated_at
```

#### `invoice_items`
```sql
id, invoice_id (FK→invoices), product_id (FK→products),
quantity, unit_price, discount, tax,
coa_id (FK→chart_of_accounts, added Dec 2025),
created_at, updated_at
```

#### `account_receivables`
```sql
id, invoice_id (FK→invoices), customer_id (FK→customers),
total, paid, remaining, status (Lunas/Belum Lunas),
created_by (FK→users), cabang_id (FK→cabangs),
created_at, updated_at
-- Index unique: (invoice_id) added Mar 2026
-- Index: (created_at) for sorting performance
```

#### `account_payables`
```sql
id, invoice_id (FK→invoices), supplier_id (FK→suppliers),
total, paid, remaining, status (Lunas/Belum Lunas),
cabang_id (FK→cabangs, added Mar 2026),
created_at, updated_at
```

#### `customer_receipts`
```sql
id, invoice_id (FK→invoices, nullable),
customer_id (FK→customers),
selected_invoices (JSON), invoice_receipts (JSON),
payment_date, ntpn (nullable),
total_payment, notes, diskon, payment_adjustment,
payment_method, coa_id (FK→chart_of_accounts),
status (Draft/Partial/Paid),
created_by (FK→users), cabang_id (FK→cabangs),
created_at, updated_at
```

#### `customer_receipt_items`
```sql
id, customer_receipt_id, invoice_id, amount, created_at, updated_at
```

#### `cash_bank_accounts`
```sql
id, name, bank_name, account_number, coa_id, notes, created_at, updated_at
```

#### `cash_bank_transactions`
```sql
id, number, date, type,
account_coa_id (FK→chart_of_accounts),
offset_coa_id (FK→chart_of_accounts),
amount, counterparty, description, attachment_path,
cabang_id, cash_bank_account_id,
voucher_request_id (FK→voucher_requests),
voucher_number, voucher_usage_type, voucher_amount_used,
created_at, updated_at
```

#### `cash_bank_transaction_details`
```sql
id, cash_bank_transaction_id, coa_id, amount, description, created_at, updated_at
```

#### `cash_bank_transfers`
```sql
id, from_account_id, to_account_id, amount, date, notes, created_at, updated_at
```

#### `bank_reconciliations`
```sql
id, coa_id (FK→chart_of_accounts),
period_start, period_end,
statement_ending_balance, book_balance, difference,
reference, notes, status, created_at, updated_at
```

#### `deposits`
```sql
id, deposit_number, from_model_type, from_model_id (polymorphic),
amount, used_amount, remaining_amount,
coa_id, payment_coa_id (FK→chart_of_accounts),
note, status (active/closed), created_at, updated_at
```

#### `deposit_logs`
```sql
id, deposit_id (FK→deposits), amount, type, reference, created_at, updated_at
```

#### `voucher_requests`
```sql
id, voucher_number, voucher_date, amount, related_party,
description, status (draft/pending_approval/approved/rejected),
created_by (FK→users), approved_by (FK→users), approved_at,
requested_to_owner_at, approval_notes,
cash_bank_transaction_id (FK→cash_bank_transactions),
cabang_id (FK→cabangs), created_at, updated_at
```

#### `other_sales`
```sql
id, reference_number, transaction_date, type, description, amount,
coa_id (FK→chart_of_accounts),
cash_bank_account_id (FK→cash_bank_accounts),
customer_id (FK→customers), cabang_id (FK→cabangs),
status, notes, created_at, updated_at
```

#### `ageing_schedules`
```sql
id, from_model_type, from_model_id (polymorphic→AR/AP),
due_date, amount, created_at, updated_at
```

---

### 3.6 Tabel Manufaktur

#### `bill_of_materials`
```sql
id, cabang_id, product_id, quantity, code, nama_bom, note,
is_active, uom_id, labor_cost, overhead_cost, total_cost,
finished_goods_coa_id, work_in_progress_coa_id,
created_at, updated_at
```

#### `bill_of_material_items`
```sql
id, bill_of_material_id, product_id, quantity, uom_id, created_at, updated_at
```

#### `production_plans`
```sql
id, plan_number, name, source_type, sale_order_id (nullable),
bill_of_material_id, product_id, quantity, uom_id, warehouse_id,
start_date, end_date, status, notes, created_by,
created_at, updated_at
```

#### `manufacturing_orders`
```sql
id, mo_number, production_plan_id, quantity,
status (draft/in_progress/completed),
start_date, end_date, items (JSON), cabang_id,
created_at, updated_at
```

#### `productions`
```sql
id, production_number, manufacturing_order_id,
quantity_produced, production_date, status (draft/finished),
created_at, updated_at
```

#### `material_issues`
```sql
id, issue_number, production_plan_id, manufacturing_order_id,
warehouse_id, issue_date, type,
status (draft/pending_approval/approved/completed),
total_cost, notes, created_by, approved_by,
created_at, updated_at
```

#### `material_issue_items`
```sql
id, material_issue_id, product_id, quantity, unit_cost, created_at, updated_at
```

---

### 3.7 Tabel Aset

#### `assets`
```sql
id, code, name, purchase_date, usage_date,
purchase_cost, salvage_value, useful_life_years,
depreciation_method, asset_coa_id,
accumulated_depreciation_coa_id, depreciation_expense_coa_id,
annual_depreciation, monthly_depreciation,
accumulated_depreciation, book_value,
status (active/disposed/transferred),
product_id (FK→products, nullable),
purchase_order_id (FK→purchase_orders, nullable),
cabang_id (FK→cabangs), created_at, updated_at
```

#### `asset_depreciations`
```sql
id, asset_id (FK→assets), depreciation_date, amount,
journal_entry_id (FK→journal_entries), created_at, updated_at
```

#### `asset_disposals`
```sql
id, asset_id (FK→assets), disposal_date, proceeds, gain_loss,
notes, created_at, updated_at
```

#### `asset_transfers`
```sql
id, asset_id (FK→assets), transfer_date,
from_cabang_id (FK→cabangs), to_cabang_id (FK→cabangs),
notes, created_at, updated_at
```

---

### 3.8 Tabel RBAC

#### `roles`
```sql
id, name, guard_name, description (added Mar 2026), created_at, updated_at
```

#### `permissions`
```sql
id, name, guard_name, description (added Mar 2026), created_at, updated_at
```

#### `model_has_roles` (pivot)
```sql
role_id, model_type, model_id
```

#### `model_has_permissions` (pivot)
```sql
permission_id, model_type, model_id
```

#### `role_has_permissions` (pivot)
```sql
permission_id, role_id
```

---

### 3.9 Tabel Laporan Konfigurasi

#### `cash_flow_sections`
```sql
id, name, code, type (operating/investing/financing), sort_order, created_at, updated_at
```

#### `cash_flow_items`
```sql
id, section_id, name, coa_ids (JSON), sort_order, created_at, updated_at
```

#### `cash_flow_item_prefixes`, `cash_flow_item_sources`
```sql
id, item_id, prefix/source, created_at, updated_at
```

#### `cash_flow_cash_accounts`
```sql
id, coa_id, created_at, updated_at
```

#### `hpp_overhead_items`, `hpp_overhead_item_prefixes`, `hpp_prefixes`
```sql
(HPP / COGS calculation configuration tables)
```

#### `income_statement_items`
```sql
id, section, name, coa_ids (JSON), sort_order, created_at, updated_at
```

---

## 4. INDEX & PERFORMANCE

### 4.1 Index Penting (added Mar 2026)

```sql
-- account_receivables
CREATE UNIQUE INDEX ON account_receivables(invoice_id);
CREATE INDEX ON account_receivables(created_at);

-- Performance indexes for sorting
CREATE INDEX ON [various tables](created_at);
```

### 4.2 Index Rekomendasi Tambahan

| Tabel | Kolom | Alasan |
|-------|-------|--------|
| sale_orders | customer_id, status | Filter utama di list view |
| delivery_orders | status, delivery_date | Status filter + date sort |
| journal_entries | source_type, source_id | Polymorphic lookup |
| journal_entries | coa_id, date | Buku besar query |
| stock_movements | product_id, warehouse_id | Inventory card query |
| invoices | customer_id, status, due_date | AR management |
| purchase_orders | supplier_id, status | AP management |

---

## 5. CATATAN SCHEMA KHUSUS

### 5.1 Kolom JSON

Beberapa kolom menyimpan data JSON array:
- `invoices.other_fee` — Array biaya tambahan
- `invoices.delivery_orders` — Array DO IDs
- `invoices.purchase_receipts` — Array receipt IDs
- `invoices.purchase_order_ids` — Array PO IDs
- `vendor_payments.selected_invoices` — Array invoice IDs
- `vendor_payments.invoice_receipts` — Detail pembayaran per invoice
- `manufacturing_orders.items` — Array item MO

**Catatan Bug Historis:** `other_fee` pernah tersimpan sebagai integer `0` bukan JSON array `[]` — sudah diperbaiki.

### 5.2 Polymorphic Relations

| Kolom | Relasi From | Relasi To |
|-------|-------------|-----------|
| `invoices.from_model_*` | Invoice | SaleOrder, DeliveryOrder, PurchaseReceipt |
| `journal_entries.source_*` | JournalEntry | Invoice, Receipt, Payment, Transfer, etc. |
| `stock_movements.from_model_*` | StockMovement | DeliveryOrder, PurchaseReceipt, etc. |
| `ageing_schedules.from_model_*` | AgeingSchedule | AccountReceivable, AccountPayable |
| `deposits.from_model_*` | Deposit | Customer, Supplier |
| `quality_controls.from_model_*` | QualityControl | PurchaseReceipt, Production |
| `return_products.from_model_*` | ReturnProduct | DeliveryOrder |

### 5.3 Squashed Schema

File `database/schema/mysql-schema.sql` berisi snapshot schema hingga ~Desember 2025. Migrasi inkremental mulai dari tanggal tersebut diterapkan secara terpisah.

**Catatan:** Saat install baru (`php artisan migrate --fresh`), schema base + 45 incremental migrations diterapkan secara urut.

---

## 6. HISTORI MIGRASI INKREMENTAL

| Tahap | Period | Jumlah Migrasi | Perubahan Kunci |
|-------|--------|----------------|-----------------|
| Phase 1 | Des 2025 | 5 | COA invoice, HPP tables |
| Phase 2 | Des 2025 | 4 | Product cabang_id refactor |
| Phase 3 | Feb 2026 | 10 | Order request enhancements, product_supplier, GRN fixes |
| Phase 4 | Feb 2026 | 4 | Purchase invoice multi-PO, payment requests |
| Phase 5 | Mar 2026 | 22 | Tax types, customer returns, app settings, journal reversal, performance indexes |

---

*Dokumen ini merupakan referensi schema database untuk Duta Tunggal ERP per 14 Maret 2026.*
