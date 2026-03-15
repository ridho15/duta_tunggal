# DUTA TUNGGAL ERP — Module Reference Document
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## 1. MODUL MASTER DATA

### 1.1 Cabang (Branch)

**File:** `app/Filament/Resources/CabangResource.php`  
**Model:** `app/Models/Cabang.php`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `kode` | string | Kode cabang (unik) |
| `nama` | string | Nama cabang |
| `alamat` | text | Alamat |
| `telepon` | string | Telepon |
| `kenaikan_harga` | decimal | Persentase kenaikan harga jual default |
| `status` | enum | active/inactive |
| `tipe_penjualan` | string | Tipe penjualan cabang |
| `kode_invoice_pajak` | string | Prefix nomor invoice ber-PPN |
| `kode_invoice_non_pajak` | string | Prefix nomor invoice non-PPN |
| `nama_kwitansi` | string | Nama yang tertera di kwitansi |
| `lihat_stok_cabang_lain` | boolean | Apakah bisa lihat stok cabang lain |

**Relasi:** `hasMany` Warehouse, Product, Customer, Supplier

---

### 1.2 Gudang (Warehouse)

**File:** `app/Filament/Resources/WarehouseResource.php`  
**Model:** `app/Models/Warehouse.php`

| Field | Tipe | Keterangan |
|-------|------|-----------|
| `kode` | string | Kode gudang |
| `name` | string | Nama gudang |
| `cabang_id` | FK | Branch |
| `location` | string | Lokasi fisik |
| `telepon` | string | Telepon gudang |
| `tipe` | enum | Jenis gudang |
| `status` | enum | active/inactive |
| `warna_background` | string | Warna UI identifikasi |

**Relasi:** `hasMany` Rak, StockMovement, InventoryStock; `belongsTo` Cabang

---

### 1.3 Produk (Product)

**File:** `app/Filament/Resources/ProductResource.php`  
**Model:** `app/Models/Product.php`

Field utama: `name`, `sku`, `product_category_id`, `cabang_id`, `cost_price`, `sell_price`, `biaya`, `harga_batas`, `tipe_pajak`, `pajak`, `uom_id`, `is_manufacture`, `is_raw_material`, `is_active`

COA Mapping fields:
- `inventory_coa_id` — COA persediaan
- `sales_coa_id` — COA pendapatan penjualan
- `sales_return_coa_id` — COA retur penjualan
- `cogs_coa_id` — COA HPP
- `purchase_return_coa_id` — COA retur pembelian
- `unbilled_purchase_coa_id` — COA pembelian belum ditagih
- `temporary_procurement_coa_id` — COA pengadaan sementara

**Relasi:** `belongsToMany` Supplier; `hasMany` ProductUnitConversion; `belongsTo` ProductCategory, UnitOfMeasure

---

### 1.4 Customer

**File:** `app/Filament/Resources/CustomerResource.php`  
**Model:** `app/Models/Customer.php`

| Field | Keterangan |
|-------|-----------|
| `name` | Nama kontak |
| `code` | Kode customer (unik) |
| `address` | Alamat |
| `telephone/phone` | Telepon |
| `perusahaan` | Nama perusahaan |
| `tipe` | PKP (Pengusaha Kena Pajak) / PRI (Pribadi) |
| `nik_npwp` | NIK atau NPWP |
| `tempo_kredit` | Hari tenor kredit |
| `kredit_limit` | Limit kredit |
| `tipe_pembayaran` | Metode pembayaran default |
| `isSpecial` | Flag customer khusus |

---

### 1.5 Supplier

**File:** `app/Filament/Resources/SupplierResource.php`  
**Model:** `app/Models/Supplier.php`

| Field | Keterangan |
|-------|-----------|
| `code` | Kode supplier (unik) |
| `perusahaan` | Nama perusahaan |
| `address` | Alamat |
| `phone/handphone` | Telepon |
| `npwp` | NPWP supplier |
| `tempo_hutang` | Hari tenor hutang |
| `kontak_person` | Nama kontak |

**Relasi:** `belongsToMany` Product (via product_supplier pivot); `morphOne` Deposit

---

## 2. MODUL PENJUALAN (SALES)

### 2.1 Penawaran (Quotation)

**File:** `app/Filament/Resources/QuotationResource.php`  
**Model:** `app/Models/Quotation.php`  
**Service:** `app/Services/QuotationService.php`

**Status Flow:**
```
draft ──request_approve──► request_approve ──approve──► approve
                                           └──reject──► reject
```

**Field Utama:**
- `quotation_number` — Auto-generated
- `customer_id` — FK Customer
- `date` — Tanggal quotation
- `valid_until` — Berlaku hingga
- `tempo_pembayaran` — Tenor pembayaran
- `total_amount` — Total nilai
- `po_file_path` — Upload PO customer

**Actions:** Request Approve, Approve, Reject, Convert to SO

---

### 2.2 Sales Order (SO)

**File:** `app/Filament/Resources/SaleOrderResource.php`  
**Model:** `app/Models/SaleOrder.php`  
**Service:** `app/Services/SalesOrderService.php`  
**Observer:** `app/Observers/SaleOrderObserver.php`

**Status Flow:**
```
draft ──► request_approve ──► approved ──► closed/completed/canceled
```

**Field Utama:**
- `so_number` — Auto-generated
- `customer_id` — FK Customer
- `quotation_id` — FK Quotation (nullable)
- `order_date` — Tanggal SO
- `delivery_date` — Tanggal pengiriman yang diinginkan
- `tipe_pengiriman` — `Ambil Sendiri` / `Kirim Langsung`
- `tempo_pembayaran` — Tenor pembayaran
- `total_amount` — Total nilai

**Observer Actions (SaleOrderObserver):**
- Saat `approved`: Buat `WarehouseConfirmation` otomatis
- Reservasi stok via `StockReservationService`

**Catatan Penting:**
- PPN di item SO di-lock untuk role `Sales` (hanya admin/finance yang bisa edit)
- SO `Ambil Sendiri` tetap menghasilkan DO sebagai bukti keluar gudang

---

### 2.3 Delivery Order (DO)

**File:** `app/Filament/Resources/DeliveryOrderResource.php`  
**Model:** `app/Models/DeliveryOrder.php`  
**Service:** `app/Services/DeliveryOrderService.php`  
**Observer:** `app/Observers/DeliveryOrderObserver.php`

**Status Flow:**
```
draft ──► sent ──► received ──► approved/closed
         └──► delivery_failed
              └──► reject
```

**Field Utama:**
- `do_number` — Auto-generated
- `delivery_date` — Tanggal pengiriman
- `driver_id` — Pengemudi (nullable)
- `vehicle_id` — Kendaraan (nullable)
- `warehouse_id` — Warehouse asal
- `additional_cost` — Biaya tambahan pengiriman
- `cabang_id` — Cabang

**Relasi:** `belongsToMany` SaleOrder via `delivery_sales_orders`

**Setting:** `AppSetting::doApprovalRequired()` — kontrol apakah DO perlu approval

**Kolom Tabel Utama:** `do_number` → `customer_names` → `delivery_date` → `status`

---

### 2.4 Surat Jalan (SJ)

**File:** `app/Filament/Resources/SuratJalanResource.php`  
**Model:** `app/Models/SuratJalan.php`  
**Service:** `app/Services/SuratJalanService.php`

**Field Utama:**
- `sj_number` — Auto-generated
- `issued_at` — Tanggal terbit
- `sender_name` — Nama pengirim
- `shipping_method` — Metode pengiriman
- `status` — draft/issued/signed

**Actions:**
1. `terbit` — Setujui SJ (status → 1) — TIDAK auto-mark DO sebagai sent
2. `mark_as_sent` — Tandai DO terkait sebagai 'sent' (terpisah dari terbit)
3. `tandai_gagal_kirim` — Pilih DO yang gagal kirim → status `delivery_failed`
4. **Cetak Rekap Driver** — PDF per driver per tanggal

---

### 2.5 Invoice Penjualan (Sales Invoice)

**File:** `app/Filament/Resources/SalesInvoiceResource.php`  
**Model:** `app/Models/Invoice.php`  
**Service:** `app/Services/InvoiceService.php`  
**Observer:** `app/Observers/InvoiceObserver.php`

**Field Utama:**
- `invoice_number` — Auto-generated
- `from_model_type/id` — Polymorphic (SaleOrder atau DeliveryOrder)
- `invoice_date` — Tanggal invoice
- `due_date` — Jatuh tempo
- `ppn_rate` — Rate PPN (%)
- `dpp` — Dasar Pengenaan Pajak
- `subtotal`, `tax`, `other_fee` (JSON array), `total`
- `status` — draft/sent/paid/partially_paid/overdue

**Observer Actions (InvoiceObserver):**
- Saat created: Buat `AccountReceivable` record
- Update AR paid/remaining

**Catatan:** COA fields (`ar_coa_id`, `ppn_keluaran_coa_id`) **disembunyikan** di form (Hidden)

---

### 2.6 Penerimaan Pembayaran (Customer Receipt)

**File:** `app/Filament/Resources/CustomerReceiptResource.php`  
**Model:** `app/Models/CustomerReceipt.php`  
**Observer:** `app/Observers/CustomerReceiptObserver.php`

**Field Utama:**
- `selected_invoices` — JSON array invoice yang dilunasi
- `invoice_receipts` — JSON detail pembayaran per invoice
- `payment_date` — Tanggal pembayaran
- `total_payment` — Total dibayar
- `payment_method` — Metode pembayaran
- `diskon` — Diskon pembayaran
- `payment_adjustment` — Penyesuaian pembayaran
- `status` — Draft/Partial/Paid

**NTPN:** Field disembunyikan (tidak diperlukan untuk pelunasan piutang customer)

---

### 2.7 Retur Customer

**File:** `app/Filament/Resources/CustomerReturnResource.php`  
**Model:** `app/Models/CustomerReturn.php`  
**Service:** `app/Services/CustomerReturnService.php`

**Status Flow:**
```
pending ──► received ──► qc_inspection ──► approved ──► completed
                                       └──► rejected
```

**Field Utama:**
- `return_number` — Format: `CR-{YEAR}-XXXX`
- `invoice_id` — Invoice yang diretur
- `customer_id` — Customer
- `warehouse_id` — Gudang tujuan retur
- `return_date` — Tanggal retur
- `reason` — Alasan retur

**Item Decision:** `accepted` / `rejected` / `replace`  
**Stok:** Dikembalikan via `CustomerReturnService` saat status `completed`

---

## 3. MODUL PENGADAAN (PROCUREMENT)

### 3.1 Order Request (Permintaan Pembelian)

**File:** `app/Filament/Resources/OrderRequestResource.php`  
**Model:** `app/Models/OrderRequest.php`  
**Service:** `app/Services/OrderRequestService.php`

**Status Flow:**
```
draft ──approve──► approved ──► closed
      └──reject──► rejected
```

**Field Utama:**
- `request_number` — Auto-generated
- `warehouse_id` — Gudang yang meminta
- `supplier_id` — Supplier (default, bisa diubah per item)
- `tax_type` — PPN Excluded / PPN Included
- `note` — Catatan

**Features:**
- Field `unit_price` per item **bisa diubah** (override dari harga master)
- `original_price` dari data master supplier — read-only sebagai referensi
- **Multi-supplier:** Toggle → setiap item bisa di-assign ke supplier berbeda
- **Generate PO:** Otomatis membuat 1 PO per supplier group

---

### 3.2 Purchase Order (PO)

**File:** `app/Filament/Resources/PurchaseOrderResource.php`  
**Model:** `app/Models/PurchaseOrder.php`  
**Service:** `app/Services/PurchaseOrderService.php`  
**Observer:** `app/Observers/PurchaseOrderObserver.php`

**Status Flow:**
```
draft ──approve_po──► approved ──► partially_received ──► completed ──► closed
                                                       └──► request_close
```

**Field Utama:**
- `po_number` — Auto-generated
- `supplier_id` — Supplier
- `order_date` — Tanggal PO
- `expected_date` — Tanggal pengiriman yang diharapkan
- `is_asset` — Apakah pembelian aset
- `ppn_option` — Opsi PPN
- `tempo_hutang` — Tenor hutang
- `refer_model_type/id` — Sumber (OrderRequest atau lainnya)

**Catatan:** PO dibuat dalam status `draft`, perlu action `approve_po` untuk disetujui

---

### 3.3 Penerimaan Barang (Purchase Receipt / GRN)

**File:** `app/Filament/Resources/PurchaseReceiptResource.php`  
**Model:** `app/Models/PurchaseReceipt.php`  
**Service:** `app/Services/PurchaseReceiptService.php`  
**Observer:** `app/Observers/PurchaseReceiptObserver.php`

**Field Utama:**
- `receipt_number` — Auto-generated
- `purchase_order_id` — FK PO (nullable — bisa GRN tanpa PO)
- `receipt_date` — Tanggal terima
- `received_by` — User penerima
- `currency_id` — Mata uang
- `other_cost` — Biaya tambahan (cukai, dll)
- `status` — draft/partial/completed

**Observer Actions:**
- Saat `completed`: Cascade ke QC
- Auto-create invoice jika tidak ada PO

---

### 3.4 Quality Control Pembelian

**File:** `app/Filament/Resources/QualityControlPurchaseResource.php`  
**Model:** `app/Models/QualityControl.php`  
**Service:** `app/Services/QualityControlService.php`  
**Observer:** `app/Observers/QualityControlObserver.php`

**Field Utama:**
- `qc_number` — Auto-generated
- `passed_quantity` — Jumlah lolos QC
- `rejected_quantity` — Jumlah ditolak
- `status` — pending/inspecting/passed/failed/partial
- `product_id` — Produk
- `warehouse_id` — Tujuan stok yang lolos
- `rak_id` — Rak tujuan

**Tabel:** Menampilkan kolom `supplier_name` (searchable) dan filter dropdown supplier

**Observer Actions (QualityControlObserver):**
- Jumlah `passed` → masuk ke `InventoryStock`
- Jumlah `rejected` → otomatis buat `PurchaseReturn`

---

### 3.5 Payment Request & Vendor Payment

**File:** `app/Filament/Resources/PaymentRequestResource.php`, `app/Filament/Resources/VendorPaymentResource.php`  
**Models:** `app/Models/PaymentRequest.php`, `app/Models/VendorPayment.php`

**Payment Request Status Flow:**
```
draft ──► pending_approval ──► approved ──► paid
                           └──► rejected
```

**Vendor Payment Form:**
- CheckboxList dengan label: `"Invoice {number} ({date}) - Total: Rp X - Sisa: Rp Y - Due: Z"`
- Repeater `payment_details` per invoice: invoice_number, invoice_date, due_date, total_invoice, remaining_amount, payment_amount
- Support: import payment (PPn import, PPh22, bea masuk)

---

### 3.6 Retur Pembelian (Purchase Return)

**File:** `app/Filament/Resources/PurchaseReturnResource.php`  
**Model:** `app/Models/PurchaseReturn.php`  
**Service:** `app/Services/PurchaseReturnService.php`

**Pembuatan:** Otomatis oleh `PurchaseReturnAutomationService` ketika QC menolak barang  
**Format Nomor:** `RN-YYYYMMDD-XXXX` (contoh: `RN-20260313-0001`)

---

## 4. MODUL MANUFAKTUR

### 4.1 Bill of Material (BOM)

**File:** `app/Filament/Resources/BillOfMaterialResource.php`  
**Model:** `app/Models/BillOfMaterial.php`  
**Service:** `app/Services/BillOfMaterialService.php`

**Field Utama:**
- `code`, `nama_bom` — Identifikasi BOM
- `product_id` — Produk yang dihasilkan
- `quantity` — Jumlah yang dihasilkan per BOM
- `uom_id` — Satuan
- `labor_cost`, `overhead_cost`, `total_cost` — Komponen biaya
- `finished_goods_coa_id` — COA barang jadi
- `work_in_progress_coa_id` — COA WIP

**Item BOM:** product_id, quantity, uom_id per komponen bahan baku

---

### 4.2 Production Plan

**File:** `app/Filament/Resources/ProductionPlanResource.php`  
**Model:** `app/Models/ProductionPlan.php`  
**Service:** `app/Services/ProductionPlanService.php`

**Field Utama:**
- `plan_number` — Auto-generated
- `source_type` — Sumber perencanaan (dari SO atau mandiri)
- `sale_order_id` — FK SO (nullable)
- `bill_of_material_id` — BOM yang digunakan
- `quantity` — Jumlah yang direncanakan
- `start_date`, `end_date` — Jadwal produksi
- `status` — planning/in_progress/completed

---

### 4.3 Manufacturing Order (MO)

**File:** `app/Filament/Resources/ManufacturingOrderResource.php`  
**Model:** `app/Models/ManufacturingOrder.php`  
**Service:** `app/Services/ManufacturingService.php`

**Status Flow:**
```
draft ──► in_progress ──► completed
```

**Field Utama:**
- `mo_number` — Auto-generated
- `production_plan_id` — FK Production Plan
- `quantity` — Jumlah diproduksi
- `start_date`, `end_date` — Jadwal aktual
- `items` — JSON array item komponennya

---

### 4.4 Material Issue

**File:** `app/Filament/Resources/MaterialIssueResource.php`  
**Model:** `app/Models/MaterialIssue.php`  
**Observer:** `app/Observers/MaterialIssueObserver.php`

**Status Flow:**
```
draft ──► pending_approval ──► approved ──► completed
```

**Field Utama:**
- `issue_number` — Auto-generated
- `warehouse_id` — Gudang sumber material
- `type` — Tipe pengeluaran
- `total_cost` — Total biaya material

**Observer Actions:**
- Saat `approved`: Post WIP journal entry
- Update biaya material issue

---

### 4.5 Production & QC Manufaktur

**File:** `app/Filament/Resources/ProductionResource.php`, `app/Filament/Resources/QualityControlManufactureResource.php`  
**Models:** `app/Models/Production.php`  
**Service:** `app/Services/ProductionService.php`

**Production Status:** `draft` → `finished`

**Observer Actions (ProductionObserver):**
- Saat `finished`: Post finished goods journal entry
- Update inventory stock

---

## 5. MODUL INVENTORI

### 5.1 Stok Persediaan (Inventory Stock)

**File:** `app/Filament/Resources/InventoryStockResource.php`  
**Model:** `app/Models/InventoryStock.php`

**Field:**
- `product_id`, `warehouse_id`, `rak_id`
- `qty_available` — Stok tersedia
- `qty_reserved` — Direservasi SO
- `qty_min` — Stok minimum
- Computed: `qty_on_hand = qty_available + qty_reserved`

---

### 5.2 Pergerakan Stok (Stock Movement)

**File:** `app/Filament/Resources/StockMovementResource.php`  
**Model:** `app/Models/StockMovement.php`  
**Observer:** `app/Observers/StockMovementObserver.php`

**Field:**
- `type` — Jenis pergerakan (IN/OUT/TRANSFER)
- `from_model_type/id` — Polymorphic source
- `quantity`, `value` — Jumlah dan nilai
- `meta` — JSON metadata

**Observer:** Update `InventoryStock.qty_available` setiap ada movement baru

---

### 5.3 Transfer Stok (Stock Transfer)

**File:** `app/Filament/Resources/StockTransferResource.php`  
**Model:** `app/Models/StockTransfer.php`  
**Service:** `app/Services/StockTransferService.php`

**Status Flow:**
```
Draft ──► Pending ──► Request ──► Approved ──► completed
                              └──► Reject
```

---

### 5.4 Penyesuaian Stok (Stock Adjustment)

**File:** `app/Filament/Resources/StockAdjustmentResource.php`  
**Model:** `app/Models/StockAdjustment.php`

Digunakan untuk koreksi stok manual tanpa fisik count.

---

### 5.5 Stock Opname

**File:** `app/Filament/Resources/StockOpnameResource.php`  
**Model:** `app/Models/StockOpname.php`

**Status Flow:** `draft` → `in_progress` → `approved`

**Field per Item:**
- `system_qty` — Stok sesuai sistem
- `actual_qty` — Stok fisik
- `difference` — Selisih (auto-calculated)

Saat approved: StockMovement dan JournalEntry dibuat otomatis untuk selisih

---

## 6. MODUL KEUANGAN (FINANCE)

### 6.1 Chart of Accounts (COA)

**File:** `app/Filament/Resources/ChartOfAccountResource.php`  
**Model:** `app/Models/ChartOfAccount.php`

**Tipe Akun:**
- `Asset` — Aktiva
- `Liability` — Kewajiban
- `Equity` — Ekuitas
- `Revenue` — Pendapatan
- `Expense` — Biaya
- `Contra Asset` — Contra aktiva (akumulasi penyusutan)

**Field:**
- `code` — Kode akun
- `name` — Nama akun
- `parent_id` — Akun induk (hierarki)
- `opening_balance` — Saldo awal
- `debit`, `credit`, `ending_balance` — Saldo berjalan

---

### 6.2 Jurnal (Journal Entry)

**File:** `app/Filament/Resources/JournalEntryResource.php`  
**Model:** `app/Models/JournalEntry.php`  
**Observer:** `app/Observers/JournalEntryObserver.php`

**Field:**
- `coa_id` — FK COA
- `date` — Tanggal jurnal
- `reference` — Nomor referensi
- `debit`, `credit` — Nilai
- `journal_type` — Tipe jurnal
- `source_type/id` — Polymorphic sumber
- `is_reversal` — Flag reversal
- `reversal_of_transaction_id` — FK ke transaksi yang di-reverse

**Validasi:** `JournalValidationTrait` memastikan debit = kredit (toleransi 0.01)

---

### 6.3 Kas & Bank

**Files:**
- `CashBankAccountResource.php` — Master rekening
- `CashBankTransactionResource.php` — Transaksi
- `CashBankTransferResource.php` — Transfer antar rekening

**CashBankTransaction Field:**
- `number` — Nomor voucher
- `date` — Tanggal
- `type` — Jenis transaksi
- `account_coa_id` — COA rekening utama
- `offset_coa_id` — COA lawan
- `amount` — Nilai
- `voucher_request_id` — Link ke Voucher Request

---

### 6.4 Rekonsiliasi Bank

**File:** `app/Filament/Resources/BankReconciliationResource.php`  
**Model:** `app/Models/BankReconciliation.php`

**Field:**
- `coa_id` — Rekening yang direkonsiliasi
- `period_start`, `period_end` — Periode
- `statement_ending_balance` — Saldo rekening koran
- `book_balance` — Saldo buku
- `difference` — Selisih
- `status` — draft/completed

---

### 6.5 AR/AP Management

**Files:** `AccountReceivableResource.php`, `AccountPayableResource.php`  
**Models:** `AccountReceivable.php`, `AccountPayable.php`

| Field | AR | AP |
|-------|----|----|
| `invoice_id` | FK Inv. Penjualan | FK Inv. Pembelian |
| `customer_id/supplier_id` | customer | supplier |
| `total` | Total tagihan | Total hutang |
| `paid` | Sudah dibayar | Sudah dibayar |
| `remaining` | Sisa | Sisa |
| `status` | Lunas/Belum Lunas | Lunas/Belum Lunas |

---

### 6.6 Deposit

**File:** `app/Filament/Resources/DepositResource.php`  
**Model:** `app/Models/Deposit.php`  
**Observers:** `DepositObserver.php`, `DepositLogObserver.php`

**Field:**
- `deposit_number` — Auto-generated
- `from_model_type/id` — Customer atau Supplier (morphable)
- `amount` — Total deposit
- `used_amount` — Yang sudah digunakan
- `remaining_amount` — Sisa deposit
- `status` — active/closed

---

### 6.7 Voucher Request

**File:** `app/Filament/Resources/VoucherRequestResource.php`  
**Model:** `app/Models/VoucherRequest.php`  
**Service:** `app/Services/VoucherRequestService.php`

**Status Flow:**
```
draft ──► pending_approval ──► approved ──► (cash_bank_transaction dibuat)
                           └──► rejected
```

---

## 7. MODUL ASET

### 7.1 Aset Tetap (Fixed Asset)

**File:** `app/Filament/Resources/AssetResource.php`  
**Model:** `app/Models/Asset.php`  
**Service:** `app/Services/AssetService.php`

**Field Utama:**
- `code`, `name` — Identifikasi aset
- `purchase_date`, `usage_date` — Tanggal
- `purchase_cost` — Harga perolehan
- `salvage_value` — Nilai sisa
- `useful_life_years` — Masa manfaat (tahun)
- `depreciation_method` — Metode depresiasi (straight-line)
- `annual_depreciation`, `monthly_depreciation` — Nilai depresiasi
- `accumulated_depreciation`, `book_value` — Nilai buku
- `status` — active/disposed/transferred

**COA Mapping:**
- `asset_coa_id` — COA aset
- `accumulated_depreciation_coa_id` — COA akumulasi penyusutan
- `depreciation_expense_coa_id` — COA biaya penyusutan

---

### 7.2 Depresiasi Aset

**File:** Computed otomatis dari `AssetObserver`  
**Service:** `app/Services/AssetDepreciationService.php`

Depresiasi dihitung saat aset di-create berdasarkan metode straight-line:
```
Annual Depreciation = (Purchase Cost - Salvage Value) / Useful Life Years
Monthly Depreciation = Annual Depreciation / 12
```

---

### 7.3 Disposal & Transfer Aset

**Files:** `AssetDisposalResource.php`, `AssetTransferResource.php`  
**Services:** `AssetDisposalService.php`, `AssetTransferService.php`

- **Disposal:** Menghapus aset dari register, menghitung gain/loss
- **Transfer:** Memindahkan aset antar cabang

---

## 8. MODUL LAPORAN

### 8.1 Balance Sheet (Neraca)

**File:** `app/Filament/Resources/Reports/BalanceSheetResource.php`  
**Service:** `app/Services/BalanceSheetService.php`

Format: Aktiva (Asset) vs Pasiva (Liabilities + Equity)  
Export: Excel + PDF

---

### 8.2 Laba Rugi (Income Statement / P&L)

**File:** `app/Filament/Resources/Reports/ProfitAndLossResource.php`  
**Service:** `app/Services/IncomeStatementService.php`

Format: Pendapatan - HPP - Biaya Operasional = Laba Bersih  
Export: Excel + PDF

---

### 8.3 Cash Flow

**File:** `app/Filament/Resources/Reports/CashFlowResource.php`  
**Service:** `app/Services/Reports/CashFlowReportService.php`  
**Config:** `config/cashflow.php`

Bagian: Aktivitas Operasi / Aktivitas Investasi / Aktivitas Pendanaan

---

### 8.4 HPP / COGS Report

**File:** `app/Filament/Resources/Reports/HppResource.php`  
**Service:** `app/Services/Reports/HppReportService.php`  
**Config:** `config/hpp.php`

Kalkulasi Harga Pokok Penjualan dengan breakdown overhead

---

### 8.5 Ageing Schedule

**File:** `app/Filament/Resources/Reports/AgeingReportResource.php`  
**Model:** `app/Models/AgeingSchedule.php`

Laporan umur piutang/hutang dalam bucket: 0-30, 31-60, 61-90, >90 hari

---

### 8.6 Kartu Persediaan (Inventory Card)

**File:** `app/Http/Controllers/InventoryCardController.php`

Menampilkan riwayat lengkap pergerakan per produk per gudang  
Format: HTML print view / Excel download / PDF download

---

### 8.7 Laporan Stok

**File:** `app/Http/Controllers/Reports/StockReportController.php`

Preview stok ringkasan per produk / per gudang

---

## 9. KONFIGURASI SISTEM

### 9.1 App Settings

**File:** `app/Filament/Pages/AppSettingsPage.php`  
**Model:** `app/Models/AppSetting.php`

| Setting | Keterangan |
|---------|-----------|
| `do_approval_required` | Apakah DO memerlukan approval |
| (settings lainnya di-define melalui model) | |

---

## 10. RBAC (ROLE-BASED ACCESS CONTROL)

### 10.1 Manajemen Role & Permission

**Files:** `RoleResource.php`, `PermissionResource.php`  
**Package:** Spatie Laravel Permission

**Permission Pattern:** `{action} {module}` contoh:
- `create sale order`
- `approve delivery order`
- `view financial reports`
- `request-approve quotation`

---

*Dokumen ini merupakan referensi modul lengkap untuk Duta Tunggal ERP per 14 Maret 2026.*
