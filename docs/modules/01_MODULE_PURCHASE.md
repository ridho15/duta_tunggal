# Dokumentasi Modul Purchase (Pembelian)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Purchase (Pembelian)** mengelola seluruh siklus pengadaan barang — mulai dari permintaan pembelian internal (Order Request), pembukaan Purchase Order ke supplier, proses Quality Control, penerimaan barang di gudang, penerbitan invoice pembelian, hingga pembayaran kepada vendor.

### Alur Bisnis Utama

```
Order Request (OR)
    ↓ [Submit → Approve]
Purchase Order (PO)
    ↓ [Approve PO]
Quality Control Purchase (QC)
    ↓ [Process QC → Pass]
Purchase Receipt (otomatis dibuat sistem)
    ↓
Purchase Invoice (PINV)
    ↓
Payment Request (PR)
    ↓ [Approve PR]
Vendor Payment (VP)
```

---

## 2. Sub-Modul & Fitur

### 2.1 Order Request (OR) — Permintaan Pembelian

**File:** `app/Filament/Resources/OrderRequestResource.php`  
**Navigasi:** Grup `Pembelian (Purchase Order)`, Sort 1  
**Nomor Dokumen:** Format `OR-YYYYMMDD-XXXX`

#### Tujuan
Dokumen internal yang dibuat oleh staf untuk mengajukan permintaan pengadaan barang sebelum Purchase Order dibuat.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `request_number` | Nomor OR unik, auto-generate |
| `cabang_id` | Cabang — dibatasi berdasarkan role user |
| `warehouse_id` | Gudang tujuan, difilter per cabang |
| `request_date` | Tanggal pengajuan |
| `note` | Catatan bebas |
| `tax_type` | Opsi PPN: `None`, `PPN Excluded`, `PPN Included` |
| **Repeater: Item** | Baris produk (min 1) |
| ↳ `product_id` | Pilihan produk; auto-isi harga, UOM, supplier rekomendasi |
| ↳ `unit` | Satuan (read-only, auto-isi) |
| ↳ `supplier_id` | Override supplier per item |
| ↳ `quantity` | Jumlah yang diminta (min 0.01) |
| ↳ `unit_price` | Harga satuan (dapat diubah) |
| ↳ `discount` | Diskon (%) |
| ↳ `tax` | Tarif pajak (%) |
| ↳ `tax_nominal` | Nominal PPN (read-only, auto-hitung) |
| ↳ `subtotal` | Subtotal per baris (read-only) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Submit Persetujuan** | Status `draft` | Status → `request_approve`; kirim notifikasi |
| **Setujui** | Status `request_approve` + permission | Status → `approved`; catat `approved_by` |
| **Tolak** | Status `request_approve` | Status → `rejected` + alasan penolakan |
| **Buat PO** | Status `approved` | Membuka modal untuk membuat PO terkait |
| **Export PDF** | Kapan saja | Unduh PDF via DomPDF |
| **Tutup** | Status `approved/partial/complete` | Status → `closed` |

#### Status Workflow

```
draft → request_approve → approved → partial → complete → closed
                       ↘ rejected
```

- `partial`: sebagian item sudah terpenuhi oleh PO
- `complete`: semua item sudah terpenuhi

---

### 2.2 Purchase Order (PO)

**File:** `app/Filament/Resources/PurchaseOrderResource.php`  
**Navigasi:** Grup `Pembelian (Purchase Order)`, Sort 2  
**Nomor Dokumen:** Format `PO-YYYYMMDD-XXXX`

#### Tujuan
Dokumen resmi pembelian barang ke supplier. Dapat mereferensikan Order Request atau Sales Order (drop-ship). Mendukung multi-mata uang.

#### Field Formulir — Header

| Field | Keterangan |
|---|---|
| `refer_model_type` | Radio: `SaleOrder` atau `OrderRequest` (opsional) |
| `refer_model_id` | Selector untuk model referensi |
| `supplier_id` | Supplier (dapat buat baru inline) |
| `cabang_id` | Cabang |
| `po_number` | Nomor PO unik, auto-generate |
| `order_date` | Tanggal pesanan |
| `expected_date` | Tanggal ekspektasi kedatangan |
| `warehouse_id` | Gudang tujuan |
| `is_import` | Toggle: tandai sebagai pembelian impor |
| `ppn_option` | Radio: `standard` / `non_ppn` |
| `tempo_hutang` | Jangka kredit (hari) — auto-isi dari supplier |
| `is_asset` | Toggle: tandai sebagai pembelian aset |

#### Field Formulir — Item Repeater

| Field | Keterangan |
|---|---|
| `product_id` | Produk (difilter per supplier) |
| `currency_id` | Mata uang per baris |
| `quantity` | Jumlah (divalidasi terhadap sisa qty OR jika terkait) |
| `unit_price` | Harga satuan (auto-isi dari harga supplier) |
| `discount` | Diskon (%) |
| `tipe_pajak` | `Inklusif` / `Eksklusif` / `Non Pajak` |
| `tax` | Pajak (%) |
| `subtotal` | Subtotal (auto-hitung) |

#### Field Formulir — Biaya Lain (Repeater)
`nama_biaya`, `currency_id`, `coa_id`, `total`, `tipe`, `masuk_invoice`

#### Field Formulir — Kurs Mata Uang (Repeater)
`currency_id`, `nominal` (kurs ke IDR)

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `draft` + permission | `PurchaseOrderService::approvePo()` → status `approved`; update qty OR |
| **Generate Invoice** | Status `completed` | Buka form untuk buat purchase invoice |
| **Download PDF** | Kapan saja | Unduh PDF PO |
| **Close** | Status `completed` | Status → `closed` |

#### Status Workflow

```
draft → approved → partially_received → completed → closed
```

#### Integrasi Akuntansi (PO Aset)
Saat PO Aset disetujui (`is_asset = true`):
- Auto-buat record `Asset` per unit
- **Debit** Aset Tetap COA (`1210.01` / `1500`)
- **Credit** Utang Dagang COA (`2110`)

---

### 2.3 Quality Control Purchase (QC Pembelian)

**File:** `app/Filament/Resources/QualityControlPurchaseResource.php`  
**Navigasi:** Grup `Pembelian (Purchase Order)`, Sort 3  
**Nomor Dokumen:** Format `QC-P-YYYYMMDD-XXXX`

#### Tujuan
Proses inspeksi kualitas antara persetujuan PO dan penerimaan fisik barang. QC yang lulus otomatis membuat Purchase Receipt; yang gagal membuat Purchase Return.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `from_model_id` | Selector `PurchaseOrderItem` (hanya PO approved dengan sisa qty) |
| `qc_number` | Nomor QC unik, auto-generate |
| `passed_quantity` | Qty lolos inspeksi |
| `rejected_quantity` | Qty ditolak |
| `total_inspected` | Total diperiksa (read-only) |
| `inspected_by` | User yang melakukan inspeksi |
| `date_send_stock` | Tanggal pengiriman ke stok |
| `notes` | Catatan umum |
| `reason_reject` | Alasan penolakan |

#### Aksi

| Aksi | Syarat | Efek |
|---|---|---|
| **Batch Create QC** | Header action | Modal: pilih PO → centang item → set gudang/rak/tanggal → buat QC massal |

#### Status Workflow
`0` (Belum diproses) → `1` (Sudah diproses)

#### Integrasi
- QC lulus → `PurchaseReceiptService::postItemInventoryAfterQC()` → buat `PurchaseReceipt`
- QC gagal → `PurchaseReturnService::createFromQualityControl()` → buat `PurchaseReturn`

---

### 2.4 Purchase Receipt (Penerimaan Barang)

**File:** `app/Filament/Resources/PurchaseReceiptResource.php`  
**Navigasi:** Grup `Pembelian (Purchase Order)`, Sort 4  
**Nomor Dokumen:** Format `RN-YYYYMMDD-XXXX`

#### Tujuan
Mencatat penerimaan fisik barang dari supplier. **Tidak dapat dibuat manual** — dibuat otomatis setelah QC lulus.

#### Field Formulir — Header

| Field | Keterangan |
|---|---|
| `receipt_number` | Nomor penerimaan (auto-generate) |
| `cabang_id` | Cabang |
| `receipt_date` | Tanggal dan jam penerimaan |
| `received_by` | User yang menerima barang |
| `currency_id` | Mata uang penerimaan |
| `notes` | Catatan |

#### Field Formulir — Item Repeater

| Field | Keterangan |
|---|---|
| `product_id` | Produk |
| `warehouse_id` | Gudang tujuan |
| `rak_id` | Rak dalam gudang (opsional) |
| `qty_received` | Total qty yang datang dari supplier |
| `qty_accepted` | Qty yang diterima (divalidasi ≤ qty_received) |
| `qty_rejected` | Qty yang ditolak |
| `reason_rejected` | Alasan penolakan |

#### Status Workflow
`draft` → `partial` → `completed`

#### Integrasi Akuntansi
- Setelah QC: `PurchaseReceiptService::postItemInventoryAfterQC()`
- **Debit** Persediaan COA (`1140.10`)
- **Credit** Hutang Pengadaan Sementara COA (`1180.01`)
- Buat `StockMovement` tipe `purchase_in`

---

### 2.5 Purchase Invoice (Invoice Pembelian)

**File:** `app/Filament/Resources/PurchaseInvoiceResource.php`  
**Navigasi:** Grup `Finance - Pembelian`, Sort 9  
**Nomor Dokumen:** Format `PINV-YYYYMMDD-XXXX`

#### Tujuan
Membuat invoice pembelian dari PO/receipt yang sudah selesai. Mendukung multi-PO dan multi-receipt, kalkulasi DPP dan PPN otomatis.

#### Field Formulir — Seleksi Sumber

| Field | Keterangan |
|---|---|
| `selected_supplier` | Supplier (reset semua seleksi jika berubah) |
| `cabang_id` | Cabang |
| `selected_order_request` | Filter OR (batasi list PO ke OR tersebut) |
| `selected_purchase_orders` | `CheckboxList` PO completed (difilter per OR/supplier) |
| `selected_purchase_receipts` | `CheckboxList` Receipt dari PO terpilih |

#### Field Formulir — Info Invoice

| Field | Keterangan |
|---|---|
| `invoice_number` | Nomor invoice unik, auto-generate |
| `invoice_date` | Tanggal invoice; auto-update `due_date` dari tempo PO |
| `due_date` | Jatuh tempo (auto-hitung tapi dapat diubah) |

#### Status Workflow
`draft` → `unpaid` → `partially_paid` → `paid` / `overdue`

#### Integrasi Akuntansi (via `LedgerPostingService::postInvoice`)
```
Debit  Hutang Pengadaan Sementara (atau Persediaan)    [subtotal]
Debit  PPN Masukan                                     [ppn_amount]
Debit  Biaya Lainnya                                   [other_fees]
  Credit  Utang Dagang (AP)                            [total]
```

---

### 2.6 Purchase Return (Retur Pembelian)

**File:** `app/Filament/Resources/PurchaseReturnResource.php`  
**Navigasi:** Grup `Pembelian (Purchase Order)`, Sort 5  
**Nomor Dokumen:** Format `NR-YYYYMMDD-XXXX`

#### Tujuan
Mengelola pengembalian barang yang dibeli ke supplier. Dapat dibuat dari Purchase Receipt atau otomatis dari QC yang ditolak.

#### Field Formulir — Header

| Field | Keterangan |
|---|---|
| `nota_retur` | Nomor nota retur, auto-generate |
| `purchase_receipt_id` | Receipt sumber (hanya dari PO completed/closed) |
| `cabang_id` | Cabang (auto-isi dari receipt) |
| `return_date` | Tanggal dan jam retur |
| `status` | Status (hanya Super Admin yang dapat edit langsung) |
| `notes` | Catatan |

#### Field Formulir — Item Repeater

| Field | Keterangan |
|---|---|
| `purchase_receipt_item_id` | Item receipt sumber; auto-isi product_id dan harga |
| `qty_returned` | Jumlah yang dikembalikan (min 0.01) |
| `unit_price` | Harga dari PO |
| `reason` | Alasan retur |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Submit Persetujuan** | Status `draft` | Status → `pending_approval` |
| **Approve** | Status `pending_approval` | Buat jurnal + sesuaikan stok |
| **Reject** | Status `pending_approval` | Status → `rejected` |

#### Status Workflow
`draft` → `pending_approval` → `approved` / `rejected`

#### Integrasi Akuntansi (saat disetujui)
```
Debit  Utang Dagang (2101.01)    [nilai barang]
  Credit  Persediaan (1101.01)   [nilai barang]
```
Buat `StockMovement` tipe `purchase_return` (mengurangi `qty_available` dan `qty_on_hand`).

#### Mode Resolusi QC (untuk retur dari QC)

| Mode | Aksi |
|---|---|
| `reduce_stock` | Kurangi qty PO item |
| `wait_next_delivery` | Tandai `supplier_response = pending_resend`; PO tetap terbuka |
| `merge_next_order` | Buat `PurchaseOrderItem` baru di PO target |

---

### 2.7 Payment Request (Permintaan Pembayaran)

**File:** `app/Filament/Resources/PaymentRequestResource.php`  
**Navigasi:** Grup `Finance - Pembayaran`, Sort 1

#### Tujuan
Dokumen pengajuan pembayaran yang mengagregasi invoice-invoice yang belum dibayar untuk satu supplier dan meminta finance untuk mengotorisasi pembayaran.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `request_number` | Nomor request, auto-generate |
| `supplier_id` | Vendor/Supplier (filter invoice) |
| `request_date` | Tanggal pengajuan (default: hari ini) |
| `payment_date` | Tanggal pembayaran yang diminta |
| `cabang_id` | Cabang |
| `total_amount` | Auto-hitung dari invoice terpilih (read-only) |
| `selected_invoices` | `CheckboxList` invoice unpaid/overdue/draft per supplier |
| `notes` | Catatan |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Ajukan Persetujuan** | Status `draft` | Status → `pending_approval` |
| **Setujui** | Status `pending_approval` | Modal approval notes; status → `approved` |
| **Tolak** | Status `pending_approval` | Modal alasan penolakan; status → `rejected` |

#### Status Workflow
`draft` → `pending_approval` → `approved` / `rejected`

---

### 2.8 Vendor Payment (Pembayaran Vendor)

**File:** `app/Filament/Resources/VendorPaymentResource.php`  
**Navigasi:** Grup `Finance - Pembayaran`, Sort 2

#### Tujuan
Mencatat pembayaran aktual ke vendor. Wajib mereferensikan Payment Request yang sudah disetujui. Mendukung pembayaran multi-invoice dan berbagai metode pembayaran.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `payment_request_id` | PR yang sudah disetujui; auto-isi supplier dan invoices |
| `supplier_id` | Supplier (auto-isi dari PR) |
| `payment_date` | Tanggal pembayaran |
| `selected_invoices` | Daftar invoice dari PR yang dipilih |
| **Repeater: Detail Pembayaran** | Satu baris per invoice |
| ↳ `payment_amount` | Nominal per invoice (validasi ≤ sisa) |
| `total_payment` | Total (auto-hitung, read-only) |
| `coa_id` | COA akun pembayaran |
| `payment_method` | `Cash` / `Bank Transfer` / `Credit` / `Deposit` |
| `ntpn` | NTPN untuk pembayaran impor (opsional) |
| **Seksi Pajak Impor** | `ppn_import_amount`, `ppn_pph22_amount`, `bea_masuk_amount` |

#### Status Workflow
`partial` / `paid` — ditentukan otomatis berdasarkan sisa saldo AP setelah pembayaran

#### Integrasi Akuntansi (via `LedgerPostingService::postVendorPayment`)
```
Debit  Utang Dagang (AP)    [total_payment]
  Credit  Kas/Bank (COA)    [total_payment]
```
Update `AccountPayable.paid`, `AccountPayable.remaining`, dan `Invoice.status`.

#### Pembayaran dengan Deposit
Jika `payment_method = Deposit`:
- Validasi saldo deposit tersedia untuk supplier
- Deduct dari deposit tertua (FIFO)
- Buat `DepositLog` entries

---

## 3. Services

| Service | Method Utama | Fungsi |
|---|---|---|
| `PurchaseOrderService` | `approvePo()` | Setujui PO, catat approver, update OR qty |
| `PurchaseOrderService` | `updateTotalAmount()` | Hitung ulang total PO dari items |
| `PurchaseReceiptService` | `postItemInventoryAfterQC()` | Post persediaan setelah QC lulus |
| `PurchaseReturnService` | `submitForApproval()` | Submit retur untuk persetujuan |
| `PurchaseReturnService` | `approve()` | Setujui retur: buat jurnal + sesuaikan stok |
| `PurchaseReturnService` | `reject()` | Tolak permintaan retur |
| `PurchaseReturnService` | `createFromQualityControl()` | Buat retur otomatis dari QC gagal |
| `QualityControlService` | `createQCFromReceiptItem()` | Buat record QC dari item receipt |

---

## 4. Observers & Events

| Observer | Event | Aksi yang Dipicu |
|---|---|---|
| `PurchaseOrderObserver` | `saved` | Hitung ulang total PO |
| `PurchaseOrderObserver` | `updated` → status `approved` (asset) | Buat Asset records + jurnal akuisisi |
| `PurchaseOrderObserver` | `updated` → status `approved` | Update status Order Request (partial/complete) |
| `PurchaseOrderObserver` | `updated` → total_amount berubah | Sync journal entries |
| `PurchaseReceiptObserver` | `created` | Auto-buat PurchaseReturn untuk qty yang ditolak |
| `PurchaseReceiptObserver` | `updated` | Cek dan update status PO (completed/partially_received) |
| `PurchaseReceiptItemObserver` | `created` | Jika ada qty_rejected → buat/update PurchaseReturn |
| `PurchaseReturnObserver` | `updated` → status `approved` | Buat journal entry + adjustStock |
| `VendorPaymentObserver` | `created` | Buat detail dari invoices; post jurnal; update AP |
| `VendorPaymentObserver` | `updated` → total berubah | Reverse + re-post jurnal |
| `VendorPaymentObserver` | `deleted` | Reverse AP + hapus journal entries |
| `VendorPaymentDetailObserver` | `created` | Update AP paid/remaining; proses deposit FIFO |

---

## 5. Pola Jurnal Akuntansi

| Transaksi | Debit | Kredit |
|---|---|---|
| Penerimaan Barang | Persediaan (`1140.10`) | Hutang Pengadaan Sementara (`1180.01`) |
| Invoice Pembelian (dengan receipt) | Hutang Pengadaan Sementara | Utang Dagang (`2110`) |
| Invoice Pembelian (tanpa receipt) | Persediaan | Utang Dagang (`2110`) |
| PPN Masukan | PPN Masukan | Utang Dagang |
| Pembayaran Vendor | Utang Dagang | Kas/Bank |
| Retur Pembelian | Utang Dagang (`2101.01`) | Persediaan (`1101.01`) |
| Akuisisi Aset (PO Aset) | Aset Tetap (`1210.01`) | Utang Dagang (`2110`) |

---

## 6. Permissions

| Permission | Fungsi |
|---|---|
| `view any order request` | Lihat daftar OR |
| `create order request` | Buat OR |
| `request-approve order request` | Submit OR untuk persetujuan |
| `approve order request` | Setujui OR |
| `reject order request` | Tolak OR |
| `create purchase order` | Buat PO |
| `approve purchase order` | Setujui PO |
| `create purchase invoice` | Buat purchase invoice |
| `approve purchase invoice` | Setujui purchase invoice |
| `approve purchase return` | Setujui retur pembelian |
| `approve payment request` | Setujui payment request |
| `create vendor payment` | Buat vendor payment |
