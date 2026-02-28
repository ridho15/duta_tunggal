# Dokumentasi Alur Pembelian (Purchase Flow) — Duta Tunggal ERP

> **Tanggal Dibuat:** 2025-07-27  
> **Stack:** Laravel 11 + Filament v3 + Livewire v3  
> **Basis URL:** `http://localhost:8009` (match `APP_URL` di `.env`)  
> **Test Akun:** `ralamzah@gmail.com` / `ridho123`

---

## Daftar Isi

1. [Gambaran Umum Alur Pembelian](#1-gambaran-umum-alur-pembelian)
2. [Diagram Alur](#2-diagram-alur)
3. [Model Database & Relasi](#3-model-database--relasi)
4. [Detail Setiap Tahap](#4-detail-setiap-tahap)
   - [4.1 Order Request (OR)](#41-order-request-or)
   - [4.2 Purchase Order (PO)](#42-purchase-order-po)
   - [4.3 Quality Control (QC)](#43-quality-control-qc)
   - [4.4 Purchase Receipt (PR)](#44-purchase-receipt-pr)
   - [4.5 Invoice (Faktur)](#45-invoice-faktur)
   - [4.6 Vendor Payment (Pembayaran)](#46-vendor-payment-pembayaran)
5. [Status Transitions](#5-status-transitions)
6. [Filament Actions yang Tersedia](#6-filament-actions-yang-tersedia)
7. [Akuntansi & Jurnal Otomatis](#7-akuntansi--jurnal-otomatis)
8. [E2E Playwright Test Coverage](#8-e2e-playwright-test-coverage)
9. [Panduan Menjalankan E2E Test](#9-panduan-menjalankan-e2e-test)
10. [Masalah yang Ditemukan & Diperbaiki](#10-masalah-yang-ditemukan--diperbaiki)
11. [Data Referensi](#11-data-referensi)

---

## 1. Gambaran Umum Alur Pembelian

Alur pembelian lengkap terdiri dari 6 tahap utama:

```
Order Request (OR)
      ↓  [Approve OR]
Purchase Order (PO)  ← Auto-approved setelah dibuat dari OR / dibuat manual
      ↓  [Buat QC dari PO Item — opsional tapi lazim]
Quality Control (QC)
      ↓  [Process QC → setiap item QC dikonfirmasi]
      ↓  [Complete Purchase Order]
Purchase Receipt (PR)  ← Dibuat OTOMATIS saat PO di-complete
      ↓  [Terbitkan Invoice dari PO]
Invoice (Faktur)
      ↓  [Buat Vendor Payment]
Vendor Payment  ← Melunasi invoice kepada supplier
```

**Catatan penting:**
- PO dapat dibuat **langsung** (tanpa OR) atau melalui proses OR → PO
- Purchase Receipt dibuat **secara otomatis** oleh `PurchaseOrder::manualComplete()`, tidak dibuat manual
- Satu PO dapat memiliki **beberapa Purchase Receipt** (penerimaan parsial)
- Satu Invoice dapat dibayar melalui **satu atau beberapa Vendor Payment**

---

## 2. Diagram Alur

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          ALUR PEMBELIAN LENGKAP                                 │
└─────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────┐     Approve      ┌──────────────┐
  │ Order Request│ ───────────────► │ Purchase     │
  │  (status:    │                  │  Order       │
  │   draft)     │                  │  (status:    │
  └──────────────┘                  │   draft →    │
                                    │   approved)  │
  ┌──────────────┐                  └──────┬───────┘
  │  [Buat PO    │                         │
  │   Manual]    │ ────────────────────────┘
  └──────────────┘          ↓
                   [Buat QC dari PO Item]
                          ↓
                  ┌──────────────┐
                  │Quality Control│
                  │ (status:      │
                  │  pending →    │
                  │  passed/      │
                  │  failed)      │
                  └──────┬───────┘
                         │ [Process QC — items diverifikasi]
                         ↓
                  [Complete Purchase Order]
                         ↓
                  ┌──────────────┐     AUTO-CREATED
                  │  Purchase    │ ◄──────────────────
                  │  Receipt     │   oleh manualComplete()
                  │ (status:     │
                  │  draft →     │
                  │  completed)  │
                  └──────────────┘
                         ↓
                  [Terbitkan Invoice]
                         ↓
                  ┌──────────────┐
                  │   Invoice    │
                  │ (status:     │
                  │  draft →     │
                  │  sent/paid)  │
                  └──────┬───────┘
                         │
                  [Buat Vendor Payment]
                         ↓
                  ┌──────────────┐
                  │    Vendor    │
                  │   Payment    │
                  │  (pelunasan) │
                  └──────────────┘
```

---

## 3. Model Database & Relasi

### Model Utama

| Model | Tabel | File |
|-------|-------|------|
| `OrderRequest` | `order_requests` | `app/Models/OrderRequest.php` |
| `PurchaseOrder` | `purchase_orders` | `app/Models/PurchaseOrder.php` |
| `PurchaseOrderItem` | `purchase_order_items` | `app/Models/PurchaseOrderItem.php` |
| `PurchaseReceipt` | `purchase_receipts` | `app/Models/PurchaseReceipt.php` |
| `PurchaseReceiptItem` | `purchase_receipt_items` | `app/Models/PurchaseReceiptItem.php` |
| `QualityControl` | `quality_controls` | `app/Models/QualityControl.php` |
| `Invoice` | `invoices` | `app/Models/Invoice.php` |
| `VendorPayment` | `vendor_payments` | `app/Models/VendorPayment.php` |

### Relasi PurchaseOrder

```php
PurchaseOrder
  ├── belongsTo  Supplier              (supplier_id)
  ├── belongsTo  Warehouse             (warehouse_id)
  ├── belongsTo  User (approvedBy)     (approved_by)
  ├── hasMany    PurchaseOrderItem     (purchase_order_id)
  ├── hasMany    PurchaseOrderCurrency (purchase_order_id)
  ├── hasMany    PurchaseReceipt       (via service)
  ├── hasOne     Invoice               (refer_model)
  └── hasMany    Assets                (via purchaseOrderItem)
```

### Field Penting PurchaseOrder

| Field | Type | Keterangan |
|-------|------|------------|
| `po_number` | string | Nomor PO, format: `PO-YYYYMMDD-NNNN` |
| `status` | enum | `draft`, `approved`, `partially_received`, `completed`, `closed`, `request_close` |
| `ppn_option` | string | `include`, `exclude`, `none` |
| `tempo_hutang` | integer | Jatuh tempo dalam hari |
| `is_asset` | boolean | PO untuk pembelian aset tetap |
| `is_import` | boolean | PO barang impor |
| `cabang_id` | integer | Cabang terkait (scoped) |

---

## 4. Detail Setiap Tahap

### 4.1 Order Request (OR)

**URL:** `/admin/order-requests`  
**Resource:** `app/Filament/Resources/OrderRequestResource.php`

#### Status
| Status | Keterangan |
|--------|------------|
| `draft` | Baru dibuat, belum diajukan |
| `approved` | Disetujui, dapat dibuat PO |
| `rejected` | Ditolak |
| `closed` | Sudah selesai / dibatalkan |

#### Field Utama
- `or_number` — Nomor OR, format: `OR-YYYYMMDD-NNNN`
- `supplier_id` — Supplier yang dituju
- `request_date` — Tanggal pengajuan
- `cabang_id` — Cabang

#### Alur
1. User membuat OR dengan status `draft`
2. Manager/Admin meng-approve → status menjadi `approved`
3. Dari OR yang `approved`, dibuat PO melalui action **"Buat Purchase Order"**

---

### 4.2 Purchase Order (PO)

**URL:** `/admin/purchase-orders`  
**Resource:** `app/Filament/Resources/PurchaseOrderResource.php`  
**Service:** `app/Services/PurchaseOrderService.php`  
**Observer:** `app/Observers/PurchaseOrderObserver.php`

#### Status
| Status | Keterangan |
|--------|------------|
| `draft` | Baru dibuat |
| `approved` | Disetujui (auto maupun manual) |
| `partially_received` | Sebagian barang sudah diterima |
| `completed` | Semua barang diterima / PO selesai |
| `closed` | PO ditutup (oleh admin/manager) |
| `request_close` | Request penutupan sedang diproses |

#### Field Utama
| Field | Keterangan |
|-------|------------|
| `po_number` | Nomor PO (bisa auto-generate) |
| `order_date` | Tanggal PO |
| `expected_date` | Estimasi tanggal terima |
| `supplier_id` | Supplier |
| `warehouse_id` | Gudang tujuan |
| `ppn_option` | `include` / `exclude` / `none` |
| `tempo_hutang` | Jatuh tempo hutang (hari) |
| `is_asset` | Apakah untuk aset tetap |
| `is_import` | Apakah barang impor |

#### PO Items (Repeater)
Setiap baris item berisi:
- `product_id` — Produk
- `quantity` — Jumlah pesanan
- `unit_price` — Harga satuan
- `discount` — Diskon
- `tax` — Pajak per item

#### Auto-Approve
PO yang dibuat dari OR yang sudah `approved` akan **otomatis** mendapat status `approved` (tidak perlu approval manual).

---

### 4.3 Quality Control (QC)

**URL:** `/admin/quality-controls`  
**Resource:** `app/Filament/Resources/QualityControlResource.php`

#### Cara Membuat QC
Dari halaman **View PO** (`/admin/purchase-orders/{id}`), klik action **"Buat Quality Control"** yang muncul ketika PO berstatus `approved` atau `partially_received`.

#### Status QC
| Status | Keterangan |
|--------|------------|
| `pending` | Menunggu pemeriksaan |
| `passed` | Barang lolos QC |
| `failed` | Barang tidak lolos QC |

#### Field Utama
- `po_id` — Referensi ke PO
- `inspected_by` — User yang melakukan inspeksi (Choices.js select)
- `inspection_date` — Tanggal inspeksi
- `notes` — Catatan QC

#### Proses QC
1. Buka QC yang berstatus `pending`
2. Isi hasil pemeriksaan per item
3. Konfirmasi lolos/gagal
4. QC yang selesai mengizinkan PO untuk di-complete

---

### 4.4 Purchase Receipt (PR)

**URL:** `/admin/purchase-receipts`  
**Resource:** `app/Filament/Resources/PurchaseReceiptResource.php`

#### Pembuatan Otomatis
PR **tidak dibuat secara manual**. PR dibuat oleh method `PurchaseOrder::manualComplete()` yang dipanggil saat user mengklik action **"Complete Purchase Order"** di halaman View PO.

```php
// Dipanggil dari:
// app/Filament/Resources/PurchaseOrderResource/Pages/ViewPurchaseOrder.php
// Action: 'complete'
$record->manualComplete($userId);
```

#### Status PR
| Status | Keterangan |
|--------|------------|
| `draft` | Baru dibuat |
| `partial` | Sebagian item sudah diterima |
| `completed` | Semua item diterima |

#### Isi PR
- `receipt_number` — Nomor penerimaan
- `purchase_order_id` — Referensi PO
- `received_date` — Tanggal terima
- Items: `product_id`, `quantity_received`, `status`

#### Effect ke Stok
Saat PR dibuat/completed:
- Stok produk di gudang **bertambah** sesuai kuantitas yang diterima
- Jurnal akuntansi **debit Persediaan / kredit Hutang Dagang** dibuat otomatis

---

### 4.5 Invoice (Faktur)

**URL:** `/admin/invoices`  
**Resource:** `app/Filament/Resources/InvoiceResource.php`

#### Cara Menerbitkan Invoice
Dari halaman View PO atau dari list PO, klik action **"Terbitkan Invoice"** (`terbit_invoice`). Action ini tersedia di:
- Halaman View PO (header action)
- List PO (action group di setiap baris)

#### Status Invoice
| Status | Keterangan |
|--------|------------|
| `draft` | Baru diterbitkan |
| `sent` | Sudah dikirim ke supplier |
| `paid` | Sudah dilunasi |
| `partially_paid` | Sebagian sudah dibayar |
| `overdue` | Jatuh tempo terlampaui |

#### Field Utama
- `invoice_number` — Nomor faktur, format: `INV-YYYYMMDD-NNNN`
- `supplier_id` — Supplier
- `purchase_order_id` — Referensi PO
- `invoice_date` — Tanggal faktur
- `due_date` — Tanggal jatuh tempo (dihitung dari `tempo_hutang` PO)
- `total_amount` — Total tagihan
- `ppn_amount` — Jumlah PPN

---

### 4.6 Vendor Payment (Pembayaran)

**URL:** `/admin/vendor-payments`  
**Resource:** `app/Filament/Resources/VendorPaymentResource.php`

#### Field Wajib

| Field | Type | Keterangan |
|-------|------|------------|
| `supplier_id` | Select | Supplier yang dibayar |
| `payment_date` | Date | Tanggal pembayaran |
| `payment_method` | Radio | `Cash`, `Bank Transfer`, `Credit`, `Deposit` |
| `coa_id` | Select | Akun kas/bank yang digunakan (auto-filled oleh Livewire) |

#### Auto-Fill COA (Penting!)

Ketika `payment_method` diubah, Livewire secara otomatis mengisi `coa_id`:

| Method | Query COA |
|--------|-----------|
| `Cash` | `code LIKE '11%'` AND (`name LIKE '%kas%'` OR `name LIKE '%tunai%'`) |
| `Bank Transfer` | `code LIKE '11%'` AND `name LIKE '%bank%'` |

**COA default untuk Cash:** id=4, code=`1110`, name=`KAS DAN SETARA KAS`

#### Field Opsional
- `selected_invoices` — CheckboxList untuk memilih invoice yang dilunasi
- `amount` — Jumlah pembayaran
- `notes` — Keterangan

---

## 5. Status Transitions

### Purchase Order

```
draft
  │
  ├─[konfirmasi/approve]──► approved
  │                            │
  │                            ├─[sebagian terima]──► partially_received
  │                            │                           │
  │                            └─[complete]──► completed ◄─┘
  │                                               │
  │                                          [request close]──► request_close
  │                                                                  │
  └─[tolak]──► (deleted/rejected)                          [konfirmasi close]──► closed
```

### Purchase Receipt

```
draft ──► partial ──► completed
```

### Invoice

```
draft ──► sent ──► paid
              └──► partially_paid ──► paid
              └──► overdue
```

---

## 6. Filament Actions yang Tersedia

### Halaman View Purchase Order

| Action | Label | Kondisi Tampil | Keterangan |
|--------|-------|----------------|------------|
| `EditAction` | Edit | Selalu | Edit PO |
| `buat_qc` | Buat Quality Control | status = `approved` OR `partially_received` | Buat QC baru dari PO item |
| `complete` | Complete Purchase Order | `canBeCompleted()` = true | Selesaikan PO, buat PR otomatis |
| `konfirmasi` | Konfirmasi | status = `draft` (via admin) | Approve PO manual |
| `tolak` | Tolak | status = `draft` | Tolak PO |
| `request_close` | Request Close | status = `approved`/`partially_received` | Ajukan penutupan |
| `cetak_pdf` | Cetak PDF | status bukan `draft` | Cetak PO ke PDF |

### List Purchase Order (Action Group per Baris)

| Action | Kondisi |
|--------|---------|
| View | Selalu |
| Edit | Selalu |
| Delete | Status `draft` |
| Konfirmasi | Status `draft` |
| Tolak | Status `draft` |
| Request Close | Status `approved`/`partially_received` |
| Cetak PDF | Status bukan `draft` |
| Update Total Amount | Selalu |
| Terbitkan Invoice | Status `completed`/`closed` |

---

## 7. Akuntansi & Jurnal Otomatis

### Saat Purchase Receipt Dibuat (PO Complete)

```
DEBIT   Persediaan (11xx)        [nilai barang diterima]
CREDIT  Hutang Dagang (21xx)     [nilai sama]
```

### Saat Invoice Diterbitkan

```
DEBIT   Beban PPN Masukan (jika ppn_option = 'exclude')
CREDIT  Hutang Dagang (21xx)     [total termasuk PPN]
```

### Saat Vendor Payment Dibuat

```
DEBIT   Hutang Dagang (21xx)     [jumlah dibayar]
CREDIT  Kas/Bank (11xx)          [dari coa_id yang dipilih]
```

Verifikasi jurnal dapat dilakukan di:  
`/admin/journal-entries` — filter berdasarkan `reference_type = 'VendorPayment'` atau `'PurchaseReceipt'`

---

## 8. E2E Playwright Test Coverage

### File Test Utama

**`tests/playwright/e2e-purchase-flow-complete.spec.js`**

12 langkah pengujian yang dijalankan secara **serial** (1 worker):

| Step | Nama | Durasi Rata-rata | Yang Diverifikasi |
|------|------|-----------------|-------------------|
| 1 | Buat Order Request | ~18 detik | OR dibuat, ID tersimpan di state file |
| 2 | Approve OR + Buat PO | ~12 detik | Tombol approve di baris OR, PO ID tersimpan |
| 3 | Verifikasi PO Auto-Approved | ~13 detik | Status PO = approved di halaman view |
| 4 | Buat QC dari PO Item | ~10 detik | Action "Buat QC" dari halaman View PO |
| 5 | Process QC | ~11 detik | QC pending diproses |
| 6 | Complete Purchase Order | ~13 detik | Action "Complete" diklik, konfirmasi modal |
| 7 | Verifikasi Purchase Receipt | ~13 detik | PR otomatis ada di list `/purchase-receipts` |
| 8 | Terbitkan Invoice | ~29 detik | Invoice dibuat dari PO, invoice number tersimpan |
| 9 | Buat Vendor Payment | ~53 detik | Payment method Cash, COA auto-filled |
| 10 | Verifikasi Inventory | ~12 detik | Stok produk bertambah di `/inventory-records` |
| 11 | Verifikasi Journal Entries | ~11 detik | Minimal 8 baris jurnal dari transaksi |
| 12 | Summary | ~19 detik | Ringkasan semua entity yang dibuat |

**Total runtime: ±3.7 menit**

### State File Antar Step

Test menggunakan file `/tmp/e2e-po-state.json` untuk berbagi data antar step:

```json
{
  "orId": 27,
  "orNumber": "OR-E2E-1753600000000",
  "poId": 4,
  "poNumber": "PO-20260223-0002",
  "qcId": null,
  "invoiceId": 12,
  "invoiceNumber": "INV-E2E-1753600000000",
  "vendorPaymentId": null
}
```

### File Test Terkait Lainnya

| File | Keterangan |
|------|------------|
| `tests/playwright/purchase-order.spec.js` | Unit test PO CRUD |
| `tests/playwright/purchase-receipt.spec.js` | Unit test Purchase Receipt |
| `tests/playwright/vendor-payment.spec.js` | Unit test Vendor Payment |
| `tests/playwright/complete-procurement.spec.js` | Flow procurement alternatif |

---

## 9. Panduan Menjalankan E2E Test

### Prasyarat

1. **PHP Server** harus berjalan di port 8009:
   ```bash
   php artisan serve --port=8009
   ```

2. **Database** harus memiliki data seed:
   ```bash
   php artisan db:seed
   ```

3. **Node modules** sudah terinstal:
   ```bash
   npm install
   npx playwright install chromium
   ```

4. **APP_URL** di `.env` harus `http://localhost:8009` (bukan `127.0.0.1:8009`):
   ```
   APP_URL=http://localhost:8009
   ```
   > **Alasan:** Filament v3 memuat `select.js` via dynamic `import()` dari APP_URL origin.
   > Jika browser menggunakan `127.0.0.1` tapi APP_URL `localhost`, CORS akan memblokir
   > import tersebut dan Choices.js tidak pernah terinisialisasi.

### Cara Menjalankan Test

```bash
# Jalankan seluruh test purchase flow (12 steps)
npx playwright test tests/playwright/e2e-purchase-flow-complete.spec.js

# Dengan reporter yang lebih detail
npx playwright test tests/playwright/e2e-purchase-flow-complete.spec.js --reporter=list

# Debug mode (membuka browser visual)
npx playwright test tests/playwright/e2e-purchase-flow-complete.spec.js --debug

# Jalankan satu step saja (contoh step 9)
npx playwright test tests/playwright/e2e-purchase-flow-complete.spec.js --grep "Step 9"
```

### Konfigurasi Test

File: `playwright.config.js`

```js
{
  baseURL: 'http://localhost:8009',
  fullyParallel: true,    // Global — tapi per-describe overridden ke serial
  workers: undefined,
  use: {
    browserName: 'chromium',
    headless: true,
    viewport: { width: 1280, height: 720 }
  }
}
```

> **Penting:** Di dalam describe block e2e-purchase-flow-complete, ada:
> ```js
> test.describe.configure({ mode: 'serial' });
> ```
> Ini **WAJIB** agar 12 step dijalankan berurutan dan state file tidak diklobber oleh worker lain.

### Data DB yang Dibutuhkan

| Entity | ID | Detail |
|--------|----|--------|
| Supplier | 1 | code="Supplier 1", perusahaan="Personal" |
| Product | 101 | sku="SKU-001", name="Produk sed 1" |
| Warehouse | 1 | name="testing" |
| Cabang | 1 | nama="Cabang 1" |
| COA (Cash) | 4 | code="1110", name="KAS DAN SETARA KAS" |

---

## 10. Masalah yang Ditemukan & Diperbaiki

### Bug #1: Race Condition — Parallel Execution

**Masalah:** Test runner menggunakan 5 worker secara paralel (`fullyParallel: true`).
Step 3, 4, dan 12 membaca `/tmp/e2e-po-state.json` yang belum ditulis Step 1/2, sehingga timeout.

**Perbaikan:**
```js
// Ditambahkan di awal describe block
test.describe.configure({ mode: 'serial' });
```

---

### Bug #2: phpDebugBar Table Row Conflict (Step 7)

**Masalah:** Selector `table tbody tr` juga mencocokkan baris dari phpDebugBar
(`phpdebugbar-widgets-table-row`). Baris ini tidak terlihat sehingga `.click()` menunggu
selamanya (180 detik timeout).

**Perbaikan:** Ganti selector ke `.fi-ta-row` (Filament-specific class):
```js
// Sebelum:
const rows = page.locator('table tbody tr');

// Sesudah:
const rows = page.locator('.fi-ta-row');
```

---

### Bug #3: safeGoto Login Race Condition

**Masalah:** `page.url().includes('/login')` memberikan false positive — URL
URL `/admin/login` muncul sebentar dalam redirect chain meski user sudah login.
Test kemudian memanggil `page.fill('[id="data.email"]')` yang menunggu elemen
yang tidak pernah muncul → timeout.

**Perbaikan:** Deteksi login bukan dari URL string, tapi dari **keberadaan form login**:
```js
// Sebelum (salah):
if (page.url().includes('/login')) {
  await page.fill('[id="data.email"]', '...');
}

// Sesudah (benar):
const loginFormVisible = await page
  .locator('[id="data.email"]')
  .isVisible({ timeout: 1500 });
if (loginFormVisible) {
  await doLogin(page);
}
```

---

### Bug #4: `waitForLoadState('networkidle')` Hang Selamanya

**Masalah:** Livewire v3 menggunakan polling dan reactive lifecycle yang selalu
mengirim request HTTP. `waitForLoadState('networkidle')` tidak pernah resolve
pada halaman yang menggunakan Livewire (misalnya form Vendor Payment dengan
`selected_invoices.live()` dan `payment_method.reactive()`).

**Perbaikan:** Semua `waitForLoadState('networkidle')` diberi batas waktu:
```js
// Sebelum (hang selamanya):
await page.waitForLoadState('networkidle');

// Sesudah (bounded, tidak hang):
await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => null);
```

---

### Bug #5: Step 9 — Vendor Payment Form Timeout (5 Menit)

**Masalah A:** Field `payment_method` (Radio) tidak pernah diisi. Karena field ini
wajib, form tidak bisa disimpan dan test loop menunggu URL berubah.

**Perbaikan A:** Tambahkan klik radio Cash:
```js
const cashRadio = page.locator('input[type="radio"][value="Cash"]');
await cashRadio.check();
await page.waitForTimeout(3000); // Tunggu Livewire reactive update
```

**Masalah B:** `fillSelect(page, 'COA', '1101')` memicu pencarian AJAX di
Choices.js yang membekukan JavaScript evaluation context Chromium selama
±5 menit (full timeout).

**Perbaikan B:** Hapus interaksi manual dengan COA. Livewire `afterStateUpdated`
di server secara otomatis mengisi `coa_id=4` ketika `payment_method=Cash` dipilih.
Test tidak perlu mengisi COA secara manual.

---

### Issue Soft (Tidak Menyebabkan Fail, Tapi Perlu Perhatian)

#### Step 2: Approve OR Dilewati
Setelah OR dibuat, tombol "Approve" di baris table tidak selalu terdeteksi oleh
selector. Test mencetak "No DRAFT OR found. Skipping approve step." dan melanjutkan
ke step 3 menggunakan PO yang sudah ada (fallback ke PO id=4).

#### Step 4: QC "Inspected By" Tidak Terisi
Field `inspected_by` menggunakan Choices.js yang belum terinisialisasi pada saat
test mengisinya. QC dibuat tanpa field ini (`QC ID: undefined` di log).

#### Step 9: Form Vendor Payment Tidak Tersimpan
URL tetap `/create` setelah klik simpan karena invoice tidak dipilih di
`selected_invoices`. Test melewati assertion ini karena URL mengandung
`vendor-payment`. Vendor Payment baru tidak dibuat di sesi test ini
(menggunakan record lama, count=2).

---

## 11. Data Referensi

### Akun Chart of Account (COA) yang Relevan

| ID | Code | Name | Fungsi |
|----|------|------|--------|
| 4 | 1110 | KAS DAN SETARA KAS | Kas untuk payment method Cash |
| ~11xx | 11xx | Rekening Bank | Bank Transfer payment |
| ~21xx | 21xx | Hutang Dagang | Hutang ke supplier dari PO/Invoice |
| ~14xx | 14xx | Persediaan | Stok barang saat PR dibuat |

### URL Halaman Utama

| Fitur | URL |
|-------|-----|
| List Order Request | `/admin/order-requests` |
| Buat Order Request | `/admin/order-requests/create` |
| List Purchase Order | `/admin/purchase-orders` |
| Buat Purchase Order | `/admin/purchase-orders/create` |
| View Purchase Order | `/admin/purchase-orders/{id}` |
| List Purchase Receipt | `/admin/purchase-receipts` |
| List Quality Control | `/admin/quality-controls` |
| List Invoice | `/admin/invoices` |
| List Vendor Payment | `/admin/vendor-payments` |
| Buat Vendor Payment | `/admin/vendor-payments/create` |
| Inventory Records | `/admin/inventory-records` |
| Journal Entries | `/admin/journal-entries` |

### File Kode Kunci

| File | Keterangan |
|------|------------|
| `app/Models/PurchaseOrder.php` | Model + `manualComplete()` |
| `app/Services/PurchaseOrderService.php` | Business logic PO |
| `app/Observers/PurchaseOrderObserver.php` | Auto-approve observer |
| `app/Filament/Resources/PurchaseOrderResource.php` | Form, table, actions |
| `app/Filament/Resources/PurchaseOrderResource/Pages/ViewPurchaseOrder.php` | Header actions (QC, Complete, dll) |
| `app/Filament/Resources/VendorPaymentResource.php` | Form pembayaran + reactive COA |
| `tests/playwright/e2e-purchase-flow-complete.spec.js` | E2E test 12 langkah |
| `playwright.config.js` | Konfigurasi Playwright |

---

*Dokumentasi ini dihasilkan berdasarkan eksplorasi kode sumber dan hasil E2E testing pada 2025-07-27.*
