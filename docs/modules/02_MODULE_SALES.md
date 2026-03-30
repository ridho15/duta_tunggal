# Dokumentasi Modul Sales (Penjualan)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Sales (Penjualan)** mengelola seluruh siklus penjualan — mulai dari penawaran (Quotation), Sales Order, Pengiriman (Delivery Order), Invoice Penjualan, hingga penerimaan pembayaran pelanggan dan retur penjualan.

### Alur Bisnis Utama

```
Quotation (QO)
    ↓ [Approve → Create SO]
Sale Order (SO)
    ↓ [Approve → Warehouse Confirmation]
Warehouse Confirmation (WC)
    ↓ [Confirmed → Auto-buat DO]
Delivery Order (DO)
    ↓ [Sent / Received]
Surat Jalan
    ↓ 
Invoice Penjualan (INV)
    ↓
Customer Receipt (Penerimaan Pembayaran)
```

---

## 2. Sub-Modul & Fitur

### 2.1 Quotation (Penawaran)

**File:** `app/Filament/Resources/QuotationResource.php`  
**Navigasi:** Grup `Penjualan (Sales Order)`, Sort 1  
**Nomor Dokumen:** Format `QO-YYYYMMDD-XXXX`

#### Tujuan
Mengelola penawaran harga kepada pelanggan sebelum SO dibuat. Mendukung approval workflow dan konversi ke Sales Order.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `quotation_number` | Nomor penawaran unik, auto-generate |
| `customer_id` | Pelanggan (dapat buat baru inline) |
| `date` | Tanggal penawaran |
| `valid_until` | Tanggal kadaluarsa penawaran |
| `tempo_pembayaran` | Jangka waktu pembayaran (hari); auto-isi dari `customer.tempo_kredit` |
| `cabang_id` | Cabang |
| `total_amount` | Total (read-only, auto-hitung) |
| `po_file_path` | Upload PO dari customer (PDF/Word/image, max 5MB) |
| `notes` | Catatan |
| **Repeater: Item Penawaran** | Baris produk |
| ↳ `product_id` | Produk; auto-isi `unit`, `unit_price` |
| ↳ `unit` | Satuan (read-only) |
| ↳ `unit_price` | Dari `product.sell_price` |
| ↳ `quantity` | Jumlah |
| ↳ `discount` | Diskon (%) |
| ↳ `tax_type` | `None` / `Exclusive` / `Inclusive` |
| ↳ `tax` | Tarif pajak (%) |
| ↳ `subtotal` | Subtotal per baris (read-only, auto-hitung) |
| ↳ `notes` | Catatan per baris |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Request Approve** | Status `draft` + permission | `QuotationService::requestApprove()` → status `request_approve` |
| **Approve** | Status `request_approve` + permission | `QuotationService::approve()` → status `approve` |
| **Reject** | Status `request_approve` | `QuotationService::reject()` → status `reject` |
| **Download PDF** | Status `approve` | Unduh PDF quotation |
| **Download File** | Ada `po_file_path` | Unduh attachment PO pelanggan |
| **Buat Sale Order** | Status `approve` + permission | Slideout form; buat `SaleOrder` (status=approved) dari quotation |
| **Sync Total** | Kapan saja | Hitung ulang `total_amount` |

#### Status Workflow

```
draft → request_approve → approve
                       ↘ reject
```

---

### 2.2 Sale Order (SO)

**File:** `app/Filament/Resources/SaleOrderResource.php`  
**Navigasi:** Grup `Penjualan (Sales Order)`, Sort 2  
**Nomor Dokumen:** Format `SO-XXXXX`

#### Tujuan
Dokumen inti pesanan penjualan. Mendukung referensi dari Quotation atau SO lain, alokasi multi-gudang, dan validasi limit kredit pelanggan.

#### Field Formulir — Header

| Field | Keterangan |
|---|---|
| `so_number` | Nomor SO unik, auto-generate |
| `customer_id` | Pelanggan; helper text tampilkan limit kredit, penggunaan, saldo |
| `cabang_id` | Cabang |
| `order_date` | Tanggal pesanan |
| `delivery_date` | Tanggal rencana pengiriman |
| `shipped_to` | Alamat pengiriman (auto-isi dari `customer.address`) |
| `total_amount` | Total (read-only); warning kredit jika melebihi limit |
| `tipe_pengiriman` | `Ambil Sendiri` / `Kirim Langsung` |
| `tempo_pembayaran` | Jangka kredit (hari) |
| `options_form` | Populate dari: None / Refer SO / Refer Quotation |

#### Field Formulir — Item Repeater

| Field | Keterangan |
|---|---|
| `product_id` | Produk; helper text: stok per gudang |
| `unit` | Satuan (read-only) |
| `unit_price` | Dari `product.sell_price` |
| `discount` | Diskon (%) |
| `tipe_pajak` | None / Exclusive / Inclusive |
| `tax` | Pajak (%) — disabled untuk role Sales |
| **Nested Repeater: Alokasi Gudang** | Multi-gudang opsional |
| ↳ `warehouse_id`, `rak_id`, `quantity` | Validasi stok level rak |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Request Approve** | Status `draft` + permission | `SalesOrderService::requestApprove()` |
| **Approve** | Status `request_approve` + permission | `SalesOrderService::approve()` (cek `CreditValidationService`) |
| **Reject** | Status `request_approve` | `SalesOrderService::reject()` |
| **Request Close** | Status `approved/confirmed/completed` | Form `reason_close`; `requestClose()` |
| **Close** | Status `request_close` | `SalesOrderService::close()` |
| **Completed** | Status `approved/confirmed` | `SalesOrderService::completed()` |
| **Download PDF** | Status `approved/completed/confirmed/received` | Unduh PDF SO |
| **Titip Saldo** | Status `approved/confirmed/completed` + permission | Form titipan saldo pelanggan → buat/update `Deposit` |
| **Buat PO** | Ada item tanpa PO + permission | Buat Purchase Order dari SO (untuk drop-ship) |
| **Sync Total** | permission `update sales order` | Hitung ulang total |

#### Status Workflow

```
draft → request_approve → approved → confirmed → received → completed
                       ↘ reject
        approved/confirmed/completed → request_close → closed
```

- **`approved`**: Warehouse Confirmation otomatis dibuat
- **`confirmed`**: Stok reserved, DO otomatis dibuat (jika WC confirmed)
- **`completed`**: Invoice otomatis dibuat

#### Events yang Dipicu oleh Observer (`SaleOrderObserver`)

| Transisi Status | Aksi |
|---|---|
| → `approved` | Buat `WarehouseConfirmation` (+ auto-buat DO jika stok cukup) |
| → `completed` | Buat `Invoice` otomatis + kurangi stok jika `Ambil Sendiri` |
| `total_amount` berubah | Sync jurnal entri |
| → `canceled` | Hapus semua `StockReservation` |

---

### 2.3 Sales Invoice (Invoice Penjualan)

**File:** `app/Filament/Resources/SalesInvoiceResource.php`  
**Navigasi:** Grup `Finance - Penjualan`, Sort 1  
**Nomor Dokumen:** Format `INV-YYYYMMDD-XXXX`

#### Tujuan
Invoice penjualan yang di-scope ke `from_model_type = SaleOrder`. Mendukung seleksi Delivery Order, edit quantity invoice, dan kalkulasi PPN.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `selected_customer` | Filter pelanggan |
| `selected_sale_order` | Hanya SO berstatus `completed` |
| `invoice_number` | Nomor invoice, auto-generate |
| `invoice_date` | Tanggal invoice |
| `due_date` | Jatuh tempo (auto-hitung dari `tempo_kredit`) |
| `selected_delivery_orders` | CheckboxList DO completed dari SO (disembunyikan untuk Ambil Sendiri) |
| `confirm_self_pickup_invoice` | Checkbox konfirmasi untuk alur Ambil Sendiri |
| **Repeater: Item DO** | Per-DO baris item (edit-able quantity) |
| ↳ `do_number`, `product_name`, `original_quantity` | Dari DO (read-only) |
| ↳ `invoice_quantity` | Max = original_quantity |
| ↳ `unit_price` | Harga SO setelah diskon |
| **Repeater: Biaya Lain** | Biaya tambahan (nama + amount) |
| `dpp` | DPP (Dasar Pengenaan Pajak) |
| `tipe_pajak` | None / Inklusif / Eksklusif |
| `ppn_rate` | Tarif PPN (%) |
| `total` | Grand total (bold, read-only) |

#### Status Workflow
`draft` → `sent` → `paid` / `partially_paid` / `overdue`

#### Integrasi Akuntansi (via `InvoiceObserver::postSalesInvoice`)
```
Debit  Piutang Dagang (1120)           [total invoice]
  Credit  Revenue per produk (4000)    [subtotal per item]
  Credit  PPn Keluaran (2120.06)       [jumlah PPN]
  Credit  Biaya Pengiriman (6100.02)   [biaya lain]

Debit  HPP / COGS (5100.10)                           [qty × cost_price]
  Credit  Barang Dalam Pengiriman / Persediaan (1140.20) [per item]
```

---

### 2.4 Customer Receipt (Penerimaan Pembayaran)

**File:** `app/Filament/Resources/CustomerReceiptResource.php`  
**Navigasi:** Grup `Finance - Pembayaran`, Sort 3

#### Tujuan
Mencatat penerimaan pembayaran dari pelanggan terhadap satu atau lebih invoice penjualan. Mendukung Cash, Bank Transfer, Kredit, dan Deposit.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `customer_id` | Pelanggan |
| `payment_date` | Tanggal pembayaran |
| `cabang_id` | Cabang |
| `invoice_selection_table` | Komponen custom untuk pilih invoice (JS-driven) |
| `selected_invoices` | JSON list invoice ID yang dipilih |
| `invoice_receipts` | JSON per-invoice nominal pembayaran |
| `total_payment` | Total (auto-hitung oleh JS) |
| `coa_id` | COA akun penerimaan pembayaran |
| `payment_method` | Cash / Bank Transfer / Credit / Deposit |
| `notes` | Catatan |

#### Status
Draft → Partial → Paid

#### Integrasi Akuntansi

| Metode | Jurnal |
|---|---|
| Non-Deposit | Debit: COA Kas/Bank → Credit: Piutang Dagang (1120) |
| Deposit | Debit: Hutang Titipan Konsumen (2160.04) → Credit: Piutang Dagang (1120) |

---

### 2.5 Customer Return (Retur Pelanggan)

**File:** `app/Filament/Resources/CustomerReturnResource.php`  
**Navigasi:** Grup `Customer Return`, Sort 10

#### Tujuan
Mengelola pengembalian barang dari pelanggan dengan workflow QC lengkap.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `return_number` | Nomor retur (auto-generate) |
| `return_date` | Tanggal retur (default hari ini) |
| `customer_id` | Pelanggan |
| `cabang_id` | Cabang |
| `warehouse_id` | Gudang penerimaan retur |
| `invoice_id` | Invoice referensi (difilter per pelanggan) |
| `reason` | Alasan retur (wajib) |
| **Repeater: Item** | Produk yang diretur |
| ↳ `invoice_item_id` | Item invoice; auto-isi `product_id` |
| ↳ `quantity` | Jumlah (min 0.01) |
| ↳ `problem_description` | Deskripsi masalah |
| ↳ `qc_result` | `pass` / `fail` |
| ↳ `decision` | `repair` / `replace` / `reject` |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Mark Received** | Status `pending` | Set status=received |
| **Start QC** | Status `received` | Set status=qc_inspection |
| **Approve** | Status `qc_inspection` | Set status=approved |
| **Reject** | Status `qc_inspection` | Set status=rejected |
| **Complete** | Status `approved` + belum restore stok | `CustomerReturnService::processCompletion()` → restore stok + jurnal |

#### Status Workflow
```
pending → received → qc_inspection → approved → completed
                                  ↘ rejected
```

#### Integrasi Akuntansi (saat completed, decision=replace/repair)
```
Debit  Persediaan (1101.01)   [nilai barang yang dikembalikan]
  Credit  HPP (5100.10)       [reversal COGS]
```

---

### 2.6 Other Sale (Penjualan Lainnya)

**File:** `app/Filament/Resources/OtherSaleResource.php`  
**Navigasi:** Grup `Finance - Penjualan`, Sort 3

#### Tujuan
Mencatat pendapatan non-standar (sewa gedung, pendapatan lainnya) dengan posting jurnal langsung.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `reference_number` | Nomor referensi (default: OS-{Ymd}{rand3}) |
| `transaction_date` | Tanggal transaksi |
| `type` | `building_rental` / `other_income` |
| `coa_id` | COA Pendapatan (default: kode `7000.04`) |
| `amount` | Nominal |
| `cabang_id` | Cabang |
| `cash_bank_account_id` | Akun kas/bank (opsional) |
| `customer_id` | Pelanggan (opsional) |
| `description` | Deskripsi (wajib) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Post Journal** | Status `draft` | `OtherSaleService::postJournalEntries()` → post jurnal, status=posted |
| **Reverse Journal** | Status `posted` | `OtherSaleService::reverseJournalEntries()` → buat jurnal pembalik |

#### Status Workflow
`draft` → `posted` → `cancelled`

---

### 2.7 Return Product (Retur Barang — Gudang)

**File:** `app/Filament/Resources/ReturnProductResource.php`  
**Navigasi:** Grup `Gudang`, Sort 7  
**Nomor Dokumen:** Format `RN-YYYYMMDD-XXXX`

#### Tujuan
Pengelolaan retur barang dari Delivery Order atau Purchase Receipt kembali ke gudang.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `return_number` | Nomor retur, auto-generate |
| `from_model_type` | `DeliveryOrder` / `PurchaseReceipt` |
| `from_model_id` | DO atau PurchaseReceipt |
| `warehouse_id` | Gudang tujuan |
| `reason` | Alasan retur |
| `return_action` | `reduce_quantity_only` / `close_do_partial` / `close_so_complete` |
| **Repeater: Item** | Barang yang diretur |
| ↳ `from_item_model_id` | Item DO atau PurchaseReceiptItem; auto-isi produk |
| ↳ `quantity` | Jumlah (dibatasi max_quantity) |
| ↳ `rak_id` | Rak tujuan |
| ↳ `condition` | `good` / `damage` / `repair` |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Approve** | Status `draft` + permission | `ReturnProductService::updateQuantityFromModel()` → sesuaikan qty |

#### Status Workflow
`draft` → `approved`

#### Mode Return Action

| Mode | Aksi |
|---|---|
| `reduce_quantity_only` | Hanya kurangi qty, tidak ada perubahan lain |
| `close_do_partial` | Tutup DO secara parsial |
| `close_so_complete` | Tutup DO + set SO ke `request_close` |

---

## 3. Services

| Service | Method | Fungsi |
|---|---|---|
| `SalesOrderService` | `updateTotalAmount()` | Hitung ulang total SO |
| `SalesOrderService` | `requestApprove()` | Submit SO untuk persetujuan |
| `SalesOrderService` | `approve()` | Setujui SO (cek credit limit) |
| `SalesOrderService` | `confirm()` | Konfirmasi SO: validasi stok + buat reservasi |
| `SalesOrderService` | `cancel()` | Batalkan SO + hapus reservasi |
| `SalesOrderService` | `completed()` | Selesaikan SO |
| `SalesOrderService` | `close()` | Tutup SO |
| `SalesOrderService` | `titipSaldo()` | Buat/update Deposit untuk pelanggan |
| `SalesOrderService` | `createPurchaseOrder()` | Buat PO dari SO (drop-ship) |
| `SalesOrderService` | `generateSoNumber()` | Generate nomor SO unik |
| `QuotationService` | `generateCode()` | Generate nomor quotation |
| `QuotationService` | `requestApprove()` | Submit quotation |
| `QuotationService` | `approve()` | Setujui quotation |
| `InvoiceService` | `generateInvoiceNumber()` | Generate INV-YYYYMMDD-XXXX |
| `CustomerReturnService` | `processCompletion()` | Restore stok + buat jurnal retur |

---

## 4. Observers & Events

| Observer | Event | Aksi yang Dipicu |
|---|---|---|
| `SaleOrderObserver` | `updated` → `approved` | Buat WarehouseConfirmation (+ auto DO jika stok ready) |
| `SaleOrderObserver` | `updated` → `completed` | Buat Invoice otomatis; kurangi stok jika Ambil Sendiri |
| `SaleOrderObserver` | `updated` → `canceled` | Hapus semua StockReservation |
| `SaleOrderObserver` | `total_amount` berubah | Sync journal entries |
| `InvoiceObserver` | `created` (SO) | Buat AccountReceivable; buat ageing schedule |
| `InvoiceObserver` | `updated` (SO, field keuangan berubah) | Hapus + re-post journal entries |
| `InvoiceObserver` | `deleted` | Hapus AccountReceivable + jurnal |
| `CustomerReceiptObserver` | `created` | Post jurnal ke GL |
| `CustomerReceiptObserver` | `updated` | Re-post jurnal jika total berubah; update AR |
| `CustomerReceiptItemObserver` | `created` | Update status receipt; proses deposit FIFO; buat jurnal |
| `OtherSaleObserver` | `updated` (field keuangan berubah, sudah posted) | Reverse + re-post jurnal |

---

## 5. Pola Jurnal Akuntansi

| Transaksi | Debit | Kredit |
|---|---|---|
| Invoice Penjualan (AR) | Piutang Dagang (1120) | Revenue (4000 atau COA produk) |
| Invoice Penjualan (PPN) | — | PPn Keluaran (2120.06) |
| Invoice Penjualan (COGS) | HPP (5100.10) | Persediaan/Barang Dalam Pengiriman (1140.20) |
| Penerimaan Pembayaran (Cash/Bank) | Kas/Bank | Piutang Dagang (1120) |
| Penerimaan Pembayaran (Deposit) | Hutang Titipan Konsumen (2160.04) | Piutang Dagang (1120) |
| Retur Pelanggan (replace) | Persediaan (1101.01) | HPP Reversal (5100.10) |
| Retur Pelanggan (repair) | WIP In-Repair (1101.02) | HPP Reversal (5100.10) |

---

## 6. Permissions

| Permission | Fungsi |
|---|---|
| `request-approve quotation` | Submit quotation untuk approval |
| `approve quotation` | Setujui quotation |
| `reject quotation` | Tolak quotation |
| `create sales order` | Buat SO |
| `request sales order` | Submit SO untuk approval |
| `response sales order` | Approve/Reject SO |
| `update sales order` | Update SO |
| `update deposit` | Titip saldo pelanggan |
| `approve return product` | Setujui retur barang gudang |
| `create purchase invoice` | Buat invoice dari SO |
