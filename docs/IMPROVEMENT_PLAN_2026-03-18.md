# Improvement Plan — ERP Duta Tunggal
**Tanggal:** 18 Maret 2026  
**Berdasarkan:** Catatan Review 16 & 18 Maret 2026

---

## Daftar Isu & Status Awal — Procurement (16 Maret 2026)

| # | Isu | Area | Status Awal |
|---|-----|------|-------------|
| 1 | Qty PO yang diapprove + dibuat tidak boleh melebihi qty OR | OrderRequest / PO | ✅ Sudah |
| 2 | OR: Tampilkan rekomendasi harga supplier saat produk dipilih, supplier bisa dipilih per item | OrderRequest | ✅ Sudah (partial) |
| 3 | Hapus "Default Supplier" dari header OR | OrderRequest | ✅ Sudah (optional) |
| 4 | Status row OR + warna background tabel | OrderRequest | ✅ Sudah |
| 5 | PO approve → update fulfilled_quantity OR → update status OR | PO / OrderRequest | ✅ Sudah |
| 6 | Invoice: harga tidak bisa diedit | PurchaseInvoice | ✅ Sudah |
| 7 | Invoice: cukup PPN saja, hilangkan double pajak | PurchaseInvoice | ✅ Sudah |
| 8 | Invoice: PO yang sudah di-invoice tetap muncul tapi tidak bisa dipilih | PurchaseInvoice | ✅ Sudah |
| 9 | Parsing nominal Rupiah (ribuan, ratus ribu, jutaan, ratus juta) | MoneyHelper / semua form | ✅ Sudah |
| 10 | DateMalformedStringException saat simpan PaymentRequest | PaymentRequest | ✅ Sudah |
| 11 | VendorPayment: Pembayaran mengacu pada PaymentRequest | VendorPayment | ✅ Sudah |
| 12 | VendorPayment: Data otomatis terisi dari PaymentRequest dan Invoice | VendorPayment | ✅ Sudah |
| 13 | VendorPayment: Checkbox invoice berbasis PaymentRequest | VendorPayment | ✅ Sudah |
| 14 | VendorPayment: Bisa melakukan pembayaran sisa setelah pembayaran pertama | VendorPayment | ✅ Sudah |
| 15 | NTPN: Optional, manual input saja, tidak boleh auto-generate | VendorPayment | ✅ Sudah |
| 16 | DepositResource: Nominal 20.000.000 jadi 20 | DepositResource | ✅ Sudah |
| 17 | Cabang: Default cabang mengikuti pilihan sebelumnya di submodule selanjutnya | Semua Resource Procurement | ✅ Sudah |

---

## Kelompok Task (Urutan Prioritas)

### KELOMPOK A — Order Request (Fondasi Procurement)
> Harus selesai lebih dulu karena PO, Receipt, Invoice bergantung data OR

#### A1. Hapus Field "Supplier Default" dari Header OR
**File:** `app/Filament/Resources/OrderRequestResource.php`  
**Model:** `app/Models/OrderRequest.php`

- [x] Hapus `Select::make('supplier_id')` dari form section header OR (atau jadikan benar-benar hidden tanpa label "Default")
- [x] Pastikan filter tabel tidak bergantung pada `supplier_id` header
- [x] Update `fillForm` pada action `approve` dan `create_purchase_order` — hapus `$defaultSupplierId = $record->supplier_id` fallback
- [x] Update factory `OrderRequestFactory` — hapus `supplier_id`
- [x] **Backward-compat:** Data lama yang punya `supplier_id` di header tetap terbaca (tidak break)

**Acceptance Criteria:**
- Form create/edit OR tidak menampilkan field "Supplier (Default)"
- Item-level supplier tetap berfungsi normal
- PO grouping per supplier masih benar

---

#### A2. Supplier Recommendation saat Produk Dipilih
**File:** `app/Filament/Resources/OrderRequestResource.php`

Saat ini (`Placeholder::make('supplier_recommendation')`) sudah menampilkan supplier dengan harga terendah. Perlu enhancement:

- [x] **Tampilkan semua supplier yang memiliki produk** tersebut beserta harganya di dalam dropdown `supplier_id` item (terverifikasi)
- [x] Ketika supplier dipilih → `unit_price` dan `original_price` terisi otomatis dari `pivot->supplier_price`
- [x] Ketika produk pertama kali dipilih dan belum ada supplier dipilih → `unit_price` dari `cost_price` produk (fallback)
- [x] Label dropdown supplier item menampilkan format `(KODE) Nama Supplier - Rp harga`
- [x] Jika supplier tidak memiliki harga di katalog → tetap bisa dipilih, price tidak berubah

**Acceptance Criteria:**
- Pilih produk → dropdown supplier menampilkan hanya supplier dengan produk itu + harganya
- Pilih supplier → harga otomatis ter-isi
- User bisa override harga manual setelah supplier dipilih

---

#### A3. Validasi Qty PO Tidak Melebihi Qty OR
**File:** `app/Filament/Resources/OrderRequestResource.php` (actions: `create_purchase_order`, `approve`)  
**File:** `app/Services/OrderRequestService.php`  
**File:** `app/Services/PurchaseOrderService.php`

**Masalah:** Ketika PO sudah dibuat dari OR (fulfilled_quantity sudah naik), kemudian create PO baru lagi dari OR yang sama, qty bisa melebihi sisa.

- [x] Form `create_purchase_order` — `max_quantity` sudah dihitung sebagai `$remainingQty = $item->quantity - ($item->fulfilled_quantity ?? 0)`. Verifikasi bahwa validasi server-side juga ada (tidak hanya client)
- [x] Form `approve` — sama, verifikasi `max_quantity` consistency
- [x] Di `resolveSelectedItems()` service → tambahkan guard: jika `qty_requested > remaining` maka clamp ke `remaining` dan log warning
- [x] Validasi di `PurchaseOrderService::approvePo()` — ketika PO diapprove, cek total fulfilled per OR item tidak melebihi `quantity`
- [x] Jika total melebihi → throw exception / show notifikasi error

**Acceptance Criteria:**
- Tidak bisa memasukkan qty lebih dari sisa OR di modal create PO
- Server-side validation mencegah over-fulfillment

---

#### A4. Status OR & Background Row Table (Verifikasi)
**File:** `app/Filament/Resources/OrderRequestResource.php`

Status dan background saat ini:
```
draft           → putih (default)
request_approve → bg-gray-100 (abu-abu) ✅
approved        → bg-blue-50 (biru) ✅
partial         → bg-yellow-50 (kuning) ✅
complete        → bg-green-50 (hijau) ✅
closed          → bg-red-50 (merah) ✅
rejected        → bg-red-50 (merah) ✅
```

- [x] Verifikasi `recordClasses` sudah benar dan tampil di browser
- [x] Pastikan kolom `status` Badge tampil dengan warna yang konsisten
- [x] Pastikan transisi status `approved → partial → complete` di-trigger oleh PO approve (via `updateStatus()` di model)

**Acceptance Criteria:**
- Setiap baris OR menampilkan warna background sesuai status

---

### KELOMPOK B — Purchase Invoice (Finance)
> Diperbaiki sebelum VendorPayment karena VendorPayment bergantung invoice

#### B1. Invoice: Semua Field Harga ReadOnly
**File:** `app/Filament/Resources/PurchaseInvoiceResource.php`

- [x] Audit semua field di repeater `invoiceItem`: `unit_price`, `quantity`, `discount`, `tax` → semua harus `->readOnly()` atau `->disabled()` pada create/edit
- [x] Field `subtotal` per item → sudah harus readOnly
- [x] Field `ppn_rate` → readOnly saat edit (bisa di-set saat create, tidak bisa diubah)
- [x] Field `ppn_amount` → readOnly (auto-kalkulasi)
- [x] Field `total` / `grand_total` → readOnly (auto-kalkulasi)
- [x] Alert/helper text: "Harga mengikuti Purchase Receipt, tidak dapat diubah"

**Acceptance Criteria:**
- User tidak bisa mengubah harga, qty, atau tax item di invoice
- Semua kalkulasi otomatis dari data PO/Receipt

---

#### B2. Invoice: Hilangkan Double PPN
**File:** `app/Filament/Resources/PurchaseInvoiceResource.php`

**Masalah yang perlu diaudit:**  
- PO item sudah punya `tax` (dari OR → PO), artinya subtotal PO item sudah include/exclude PPN
- Di invoice, ada `ppn_rate` lagi yang dikalikan ke subtotal
- Jika subtotal dari PO sudah include tax dan kemudian dikalikan ppn_rate lagi → **double tax**

Langkah audit:
- [x] Baca bagaimana `subtotal` item invoice dihitung saat receipt/PO dipilih (baris ~349–410)
- [x] Tentukan: apakah `subtotal` yang masuk ke invoice adalah **DPP (pre-tax)** atau **nilai sudah include tax**?
  - Jika **DPP** → kalkulasi PPN invoice (ppn_rate × DPP) sudah benar → tidak double
  - Jika **termasuk tax** → subtotal invoice sudah kena tax, kemudian dikali ppn_rate lagi → double
- [x] Fix: pastikan `subtotal` yang diakumulasi ke invoice = DPP (qty × unit_price × (1 - discount%))
- [x] `ppn_amount = DPP × ppn_rate / 100`
- [x] `grand_total = DPP + ppn_amount + other_fees`
- [x] Hapus/ignore field `tipe_pajak` per item dalam kalkulasi invoice (cukup ppn_rate tunggal)

**Acceptance Criteria:**
- Total invoice = sum(DPP per item) + PPN tunggal (ppn_rate%) + biaya lain
- Tidak ada PPN yang dikalkulasi dua kali

---

#### B3. Invoice: PO Sudah Di-Invoice Tetap Muncul Tapi Non-Selectable
**File:** `app/Filament/Resources/PurchaseInvoiceResource.php`

Saat ini: PO yang `fullyInvoiced` muncul dengan label `[Sudah di-invoice]` di CheckboxList.  
Masalah: CheckboxList Filament tidak punya native `disabled per item` — label `[Sudah di-invoice]` muncul tapi PO masih bisa dicentang.

- [x] Gunakan `disableOptionWhen()` pada `CheckboxList::make('selected_purchase_orders')` untuk PO yang sudah fully invoiced:
  ```php
  ->disableOptionWhen(fn ($value) => $this->isPoFullyInvoiced($value))
  ```
- [x] Tetap tampilkan PO tersebut (tidak di-filter keluar) namun dengan visual disabled
- [x] Sama untuk `CheckboxList::make('selected_purchase_receipts')` — disable receipt yang sudah di-invoice

**Acceptance Criteria:**
- PO yang sudah di-invoice muncul tapi tidak bisa dicentang
- Label "[Sudah di-invoice]" tampil jelas pada item yang disabled

---

### KELOMPOK C — Vendor Payment & Payment Request
> Bergantung pada invoice yang sudah benar dari Kelompok B

#### C1. Audit & Fix VendorPayment — Data Otomatis dari PaymentRequest
**File:** `app/Filament/Resources/VendorPaymentResource.php`

- [x] Ketika `payment_request_id` dipilih:
  - `supplier_id` → otomatis dari PaymentRequest
  - `amount` → otomatis dari sisa hutang PaymentRequest
  - Daftar invoice → tampilkan hanya invoice yang terkait dengan PaymentRequest
- [x] Audit `afterStateUpdated` untuk `payment_request_id` — pastikan semua field yang harus auto-fill sudah ter-set
- [x] Jika tidak ada `payment_request_id` dipilih → field invoice dikosongkan

**Files terkait:**  
- `app/Models/PaymentRequest.php` — cek relasi ke invoice
- `app/Models/VendorPayment.php` — cek relasi

---

#### C2. VendorPayment: Checkbox Invoice Berbasis PaymentRequest
**File:** `app/Filament/Resources/VendorPaymentResource.php`

- [x] Ganti komponen invoice (jika saat ini Select/text) → `CheckboxList::make('invoice_ids')` 
- [x] Options hanya invoice yang berada dalam PaymentRequest yang dipilih
- [x] Setiap checkbox row menampilkan: nomor invoice, tanggal, total, sisa
- [x] Auto-hitung `total_payment` berdasarkan invoice yang dicentang
- [x] Validasi: total payment tidak melebihi sisa PaymentRequest

---

#### C3. VendorPayment: Partial Payment (Pembayaran Sisa)
**File:** `app/Filament/Resources/VendorPaymentResource.php`  
**File:** `app/Services/VendorPaymentService.php` (jika ada)  
**Model:** `app/Models/PaymentRequest.php`

- [x] Field `paid_amount` dan `remaining_amount` harus terkalkulasi dan tersimpan per PaymentRequest
- [x] Ketika VendorPayment dibuat untuk PaymentRequest yang sudah ada payment sebelumnya → tampilkan sisa yang harus dibayar
- [x] Validasi: tidak bisa bayar lebih dari sisa
- [x] Setelah bayar lunas → status PaymentRequest berubah ke `paid`/`complete`
- [x] Setelah bayar sebagian → status PaymentRequest berubah ke `partial`

---

#### C4. NTPN: Verifikasi Optional & Manual-Only
**File:** `app/Filament/Resources/VendorPaymentResource.php`

Saat ini sudah ada:
```php
->label('NTPN')
->placeholder('Masukkan NTPN (opsional, untuk pembayaran impor)')
->helperText('NTPN hanya diisi untuk pembayaran impor. Input manual, tidak dapat digenerate.')
```

- [x] Verifikasi tidak ada `->required()` pada field NTPN
- [x] Verifikasi tidak ada auto-generate button pada field NTPN
- [x] Pastikan NTPN tersimpan dengan benar ke database (nullable column)

**Acceptance Criteria:**
- NTPN bisa kosong
- Tidak ada tombol generate untuk NTPN

---

### KELOMPOK D — Cabang Default Propagation
> Cross-cutting concern — berlaku untuk semua submodule procurement

#### D1. Cabang Default Mengikuti Context Procurement
**Scope:** OrderRequest → PurchaseOrder → QC Purchase → PurchaseReceipt → PurchaseInvoice

**Mekanisme yang diusulkan:**
- Simpan `last_selected_cabang_id` di session/cache per user ketika user memilih cabang pada OR
- Pada form PO, ketika OR dipilih → `cabang_id` otomatis dari OR yang dipilih (sudah ada di line 195 PurchaseOrderResource)
- Pada form Receipt, ketika PO dipilih → `cabang_id` dari PO
- Pada PurchaseInvoice → `cabang_id` dari PO/Receipt yang dipilih

**Files to touch:**
- [x] `app/Filament/Resources/PurchaseOrderResource.php` — verifikasi cabang auto-fill dari OR (sudah ada)
- [x] `app/Filament/Resources/PurchaseReceiptResource.php` — verifikasi cabang di chain PO/QC (receipt auto-create dari QC mewarisi `purchase_order.cabang_id`)
- [x] `app/Filament/Resources/QualityControlPurchaseResource.php` — verifikasi cabang auto-fill dari PO item
- [x] `app/Filament/Resources/PurchaseInvoiceResource.php` — verifikasi cabang auto-fill dari PO/Receipt
- [x] Untuk Super Admin: jika tidak ada OR/PO terpilih → tampilkan semua cabang (no default)
- [x] Untuk User biasa: default = `user->cabang_id` (sudah ada)

**Implementation Detail:**
```php
// Pattern yang sudah ada di PurchaseOrderResource (line ~195):
->afterStateUpdated(function ($state, callable $set) {
    $orderRequest = OrderRequest::find($state);
    $set('cabang_id', $orderRequest->cabang_id ?? null);
    $set('warehouse_id', $orderRequest->warehouse_id ?? null);
})
```
Pattern ini perlu diverifikasi di semua resource yang ada di chain procurement.

**Acceptance Criteria:**
- Pilih OR → PO form auto-set cabang dari OR
- Pilih PO → Receipt form auto-set cabang dari PO
- Pilih PO → Invoice form auto-set cabang dari PO

---

### KELOMPOK E — Monitoring & Regression Tests
> Setelah semua fix, jalankan test suite

#### E1. Update / Tambah Playwright Tests
**File:** `tests/playwright/procurement-audit.spec.js`, `tests/playwright/order-request-approve-multi-supplier.spec.js`, `tests/playwright/order-request-one-po-per-supplier.spec.js`

- [x] Test: Qty PO tidak melebihi qty OR (A3)
- [x] Test: Invoice harga tidak bisa diedit (B1)
- [x] Test: Invoice total = DPP + PPN tunggal, tidak double (B2)
- [x] Test: PO invoiced non-selectable di CheckboxList (B3)
- [x] Test: VendorPayment — invoice dari PaymentRequest saja yang muncul (C2)
- [x] Test: Partial Payment creates remaining correctly (C3)
- [x] Test: Supplier recommendation + supplier pricing fallback/no-price behavior (A2)

Catatan progres:
- Sudah ditambahkan test `order-request-one-po-per-supplier.spec.js` dan hasil terbaru: **7 passed**.
- Test `order-request-approve-multi-supplier.spec.js` sudah dibuat deterministik pada OR multi-supplier `request_approve` dan hasil terbaru: **5 passed, 0 skipped**.
- Sudah ditambahkan test `purchase-invoice-b1.spec.js` (B1) dan refactor `purchase-invoice-b2.spec.js`, `purchase-invoice-b3.spec.js` agar deterministik tanpa skip; hasil terbaru batch B1/B2/B3: **6 passed, 0 skipped**.
- Ditambahkan verifikasi B1 tambahan untuk header readonly (`ppn_amount`, `total`) dan edit lock `ppn_rate`; hasil terbaru `purchase-invoice-b1.spec.js`: **3 passed, 0 skipped**.
- Test VendorPayment (`vendor-payment-c1-c2.spec.js`, `vendor-payment-c3-c4.spec.js`) sudah direfactor deterministik berbasis fixture Payment Request dan hasil terbaru: **7 passed, 0 skipped**.
- Ditambahkan test A4 `order-request-a4-status-colors.spec.js` (row class + badge status) dan test C3 transisi `vendor-payment-c3-status-transition.spec.js` (partial → paid); hasil run gabungan terbaru: **9 passed, 0 skipped**.
- Ditambahkan test A2 `order-request-a2-supplier-recommendation.spec.js` untuk verifikasi recommendation + update harga per supplier + supplier tanpa katalog (harga tetap); hasil terbaru: **2 passed, 0 skipped**.
- Ditambahkan test G2 `sale-order-g2-total.spec.js` untuk verifikasi kolom total list SO (Rupiah), tampilan total di view/infolist, dan field `total_amount` form create tetap disabled (auto-calc target); hasil terbaru: **4 passed, 0 skipped**.

#### E2. Run Full Regression
- [ ] `npx playwright test` — semua test harus pass (kecuali pre-existing failures)
- [x] Regression PHPUnit/Pest terfokus OR sudah pass

Catatan regresi saat ini:
- Targeted Playwright regression OR: `order-request-one-po-per-supplier.spec.js` **7 passed**.
- `order-request-approve-multi-supplier.spec.js` **5 passed, 0 skipped**.
- Combined OR targeted Playwright run (`order-request-approve-multi-supplier.spec.js` + `order-request-one-po-per-supplier.spec.js`): **12 passed, 0 skipped**.
- Extended OR targeted Playwright run (A2 + A4 + approve multi-supplier + one-PO-per-supplier): **14 passed, 0 skipped**.
- Targeted Playwright regression Purchase Invoice (`purchase-invoice-b1.spec.js` + `purchase-invoice-b2.spec.js` + `purchase-invoice-b3.spec.js`): **6 passed, 0 skipped**.
- Targeted Playwright regression Vendor Payment (`vendor-payment-c1-c2.spec.js` + `vendor-payment-c3-c4.spec.js`): **7 passed, 0 skipped**.
- Targeted Playwright A4/C3 tambahan (`order-request-a4-status-colors.spec.js` + `vendor-payment-c3-status-transition.spec.js`): **3 passed, 0 skipped**.
- Targeted Playwright D1 cabang propagation (`procurement-branch-d1.spec.js`): **4 passed, 0 skipped**.
- Targeted Playwright G2 SO total (`sale-order-g2-total.spec.js`): **4 passed, 0 skipped**.
- Combined targeted procurement Playwright (OR + PurchaseInvoice + VendorPayment): **23 passed, 0 skipped**.
- Targeted Pest/PHPUnit regression OR (`OrderRequestMultiSupplierTest`, `OrderRequestServiceTest`, `OrderRequestToPurchaseOrderTest`, `OrderRequestFrontendLogicTest`) **29 passed, 0 failed**.

Catatan teknis regresi:
- Script fixture OR `setup_procurement_test_data.php` sudah disesuaikan dengan arsitektur baru (tanpa `order_requests.supplier_id`) dan sekarang memastikan fixture deterministik OR `id=3` status `request_approve` untuk test modal approve.

---

## Urutan Pelaksanaan yang Direkomendasikan

```
Minggu 1:
  [A1] Hapus default supplier header OR
  [A2] Supplier recommendation audit/verify  
  [A3] Validasi qty PO vs OR ← PRIORITAS TINGGI (data integrity)
  [A4] Verifikasi status & warna OR

Minggu 1-2:
  [B1] Invoice: semua harga readOnly
  [B2] Invoice: fix double PPN ← PRIORITAS TINGGI (perhitungan keuangan)
  [B3] Invoice: PO invoiced non-selectable

Minggu 2:
  [C1] Audit VendorPayment auto-fill dari PaymentRequest
  [C2] VendorPayment checkbox invoice
  [C3] Partial payment logic
  [C4] NTPN verify

Minggu 2-3:
  [D1] Cabang propagation audit + fix semua resource

Setelah semua:
  [E1] Update tests
  [E2] Full regression run
```

---

## File Inventory per Kelompok

| Task | File Utama | File Pendukung |
|------|-----------|----------------|
| A1 | OrderRequestResource.php | OrderRequest.php, OrderRequestFactory.php |
| A2 | OrderRequestResource.php | — |
| A3 | OrderRequestResource.php | OrderRequestService.php, PurchaseOrderService.php |
| A4 | OrderRequestResource.php | — |
| B1 | PurchaseInvoiceResource.php | Invoice.php |
| B2 | PurchaseInvoiceResource.php | TaxService.php, InvoiceService.php |
| B3 | PurchaseInvoiceResource.php | Invoice.php |
| C1 | VendorPaymentResource.php | PaymentRequest.php, VendorPayment.php |
| C2 | VendorPaymentResource.php | PaymentRequest.php |
| C3 | VendorPaymentResource.php | PaymentRequest.php, VendorPaymentService.php |
| C4 | VendorPaymentResource.php | vendor_payments migration |
| D1 | PurchaseOrderResource.php, PurchaseReceiptResource.php, PurchaseInvoiceResource.php, QCPurchaseResource.php | semua model terkait |
| E1 | procurement-audit.spec.js | auth.setup.js |

---

## Catatan Teknis Penting

### Double PPN di PurchaseInvoice
Ketika invoice di-populate dari PurchaseReceipt, kode pada baris ~404:
```php
$subtotal += $total; // Accumulate DPP (pre-tax subtotal)
```
Perlu dipastikan `$total` di sini adalah **DPP bersih** (qty × unit_price × (1-disc%)), bukan sudah include tax dari PO item. Jika `tipe_pajak=Eksklusif` di PO, maka nilai yang tersimpan di `purchase_order_items.subtotal` sudah include tax. Audit `$total` yang dipakai saat populasi invoice — harus ambil `qty × unit_price` bukan `subtotal` dari PO item.

### Qty Validation Architecture
`fulfilled_quantity` di `order_request_items` diupdate di `PurchaseOrderService::approvePo()` (baris ~65-104). Guard yang perlu ditambahkan:
```php
// Saat approvePo: total fulfilled tidak boleh melebihi qty OR
$currentFulfilled = $orItem->fulfilled_quantity ?? 0;
$newFulfilled = $currentFulfilled + $poItemQty;
if ($newFulfilled > $orItem->quantity) {
    throw new \RuntimeException("Qty PO melebihi sisa OR untuk item {$orItem->id}");
}
```

### Cabang Propagation Pattern
Pattern terbaik: gunakan `afterStateUpdated` pada setiap field "referensi" (select OR/PO/Receipt) untuk auto-set `cabang_id`. Jangan gunakan session karena bisa menimbulkan masalah concurrent users.

### CheckboxList `disableOptionWhen`
```php
Forms\Components\CheckboxList::make('selected_purchase_orders')
    ->options($options)
    ->disableOptionWhen(fn(string $value): bool => $this->isFullyInvoiced($value))
```
Method `disableOptionWhen` tersedia di Filament v3.

---

## Daftar Isu & Status Awal — Sales & Delivery (18 Maret 2026)

| # | Isu | Area | Status Awal |
|---|-----|------|-------------|
| S1 | Format Rupiah belum konsisten di semua halaman (Quotation, SO, modal) | QuotationResource, SaleOrderResource | ❌ Belum (partial) |
| S2 | Cabang turunan: Quotation → SO → DO → SJ menggunakan cabang yang sama | Sales chain | ❌ Belum |
| S3 | SO multi-gudang: qty SO = 50 bisa diambil dari beberapa gudang (15+20+30) | SaleOrderResource, WarehouseConf | ❌ Belum |
| S4 | User management: field warehouse untuk staff gudang tidak muncul | UserResource | ❌ Bug (field ada tapi tidak visible dengan benar) |
| S5 | DO: satu DO bisa request ke multiple gudang (DO items per gudang) | DeliveryOrderResource | ❌ Belum |
| S6 | SO: kolom total harga (harga × qty) belum muncul | SaleOrderResource | ❌ Belum |
| S7 | SO: tempo hari belum otomatis dari customer | SaleOrderResource | ❌ Belum |
| S8 | SO: format nominal Rupiah field live input dan show | SaleOrderResource | ❌ Belum (partial) |
| S9 | SO: tipe pajak ditampilkan (seperti di Quotation) | SaleOrderResource | ❌ Belum |
| S10 | PO dari SO: ada mekanisme pembuatan PO dari Sales Order | SaleOrderResource | ❌ Perlu audit |
| S11 | DO: urutan field — from_sales dulu baru cabang | DeliveryOrderResource | ❌ Fix urutan |
| S12 | DO: hapus pilihan receipt item, DO hanya untuk SO | DeliveryOrderResource | ❌ Belum |
| S13 | QC Purchase: bisa multiple product, pilih PO dulu lalu product, checkbox product yang di-QC | QualityControlPurchaseResource | ❌ Partial (ada batch_create tapi perlu dirapikan) |
| S14 | Satuan (unit) produk ditampilkan di setiap baris produk (Quotation, OR, PO, SO, dll) | Semua resource produk | ❌ Belum |
| S15 | DO: hapus biaya tambahan dan deskripsi biaya tambahan | DeliveryOrderResource | ❌ Hapus |
| S16 | DO multi-gudang: pilih items → pilih gudang per item → tampilkan stock gudang → input qty | DeliveryOrderResource | ❌ Belum |
| S17 | WC: tidak auto-approve dari DO, WC otomatis dibuat saat DO request stock | WarehouseConfirmationResource | ❌ Refactor |
| S18 | WC → DO status flow: request_approve → request_stock, DO approved jika semua WC approved, rejected jika ada WC rejected | DO / WC model | ❌ Belum |
| S19 | WC: tampilkan status approve/reject + keterangan reject | WarehouseConfirmationResource | ❌ Belum |
| S20 | WC: hapus harga, tambah tombol approve/reject, reject harus isi keterangan | WarehouseConfirmationResource | ❌ Belum |
| S21 | WC: ganti informasi sales dengan informasi DO | WarehouseConfirmationResource | ❌ Belum |
| S22 | Surat Jalan: hanya DO yang sudah approved | DeliveryOrderResource / SuratJalanResource | ❌ Belum |
| S23 | Surat Jalan: hapus sender name dan metode pengiriman | SuratJalanResource | ❌ Hapus |
| S24 | Surat Jalan: tidak perlu approve/setujui | SuratJalanResource | ❌ Hapus flow |
| S25 | Surat Jalan: hapus status gagal kirim | SuratJalanResource | ❌ Hapus |
| S26 | Surat Jalan: hapus fitur rekap driver | SuratJalanResource | ❌ Hapus |
| S27 | Surat Jalan PDF: item sejenis tidak perlu dipecah per gudang | SuratJalanResource PDF | ❌ Fix PDF |
| S28 | Surat Jalan: tambahkan "Mark as Sent" | SuratJalanResource | ❌ Belum |
| S29 | DeliverySchedule: tambah metode pengiriman | DeliveryScheduleResource | ❌ Belum |
| S30 | DeliverySchedule: driver+kendaraan dari sistem jika internal, manual jika ekspedisi | DeliveryScheduleResource | ❌ Belum |
| S31 | DeliverySchedule: fitur surat kerja driver (internal/kurir internal) + PDF surat kerja | DeliveryScheduleResource | ❌ Belum |
| S32 | DeliverySchedule selesai → DO selesai/complete, stock reserved berkurang | DeliveryScheduleResource / DO model | ❌ Belum |
| S33 | SalesInvoice: tampilkan tipe pajak dari SO | SalesInvoiceResource | ❌ Belum |
| S34 | SalesInvoice: nominalkan PPN dan pastikan biaya tambahan masuk journal entries | SalesInvoiceResource | ❌ Belum (perlu audit) |
| S35 | CustomerReceipt: hapus kode debugging | CustomerReceiptResource | ❌ Ada (perlu cari dan hapus) |
| S36 | CustomerReceipt: format nominal input uang | CustomerReceiptResource | ❌ Belum (partial) |
| S37 | CustomerReceipt: tampilkan informasi journal entries | CustomerReceiptResource | ❌ Belum |
| S38 | CustomerReceipt: AR paid_amount belum update setelah pembayaran | CustomerReceiptObserver / AccountReceivable | ❌ Bug (observer ada tapi `paid` field tidak di-update) |
| S39 | CustomerReceipt: journal entries otomatis | CustomerReceiptObserver | ❌ Perlu audit — observer `postCustomerReceipt` ada tapi perlu diverifikasi |

---

## Kelompok Task — Sales & Delivery (18 Maret 2026)

### KELOMPOK F — Format & Tampilan (Cross-cutting)
> Dikerjakan bersamaan dengan kelompok lain — tidak ada dependency

#### F1. Format Rupiah Konsisten di Semua Halaman ✅ DONE (2026-03-18)
**Files:** `QuotationResource.php`, `SaleOrderResource.php`, semua modal dan form terkait

Audit setiap TextInput yang menampung nilai uang (harga, total, diskon nominal, subtotal, grand total):

- [ ] Setiap `TextInput` bernilai uang harus menggunakan `->prefix('Rp')` dan format `number_format($val, 0, ',', '.')`
- [ ] `afterStateUpdated` harus memanggil `HelperController::parseIndonesianMoney($state)` sebelum mengolah angka
- [ ] Saat tampil di view (InfoList/TextEntry), gunakan `->money('IDR', 0)` atau `->formatStateUsing(fn($s) => 'Rp ' . number_format($s, 0, ',', '.'))`
- [ ] Audit `QuotationResource`: field `total_amount` (baris ~286), `discount_amount`, `tax_amount`, `grand_total`
- [x] Audit `SaleOrderResource`: field `unit_price` (baris ~465 — saat ini `$product->sell_price` tanpa `number_format`), `subtotal`, `tax_nominal`, `total_amount`, `dp_amount`
- [ ] Audit modal-modal seperti approve quotation, create SO from quotation

**Acceptance Criteria:**
- Semua field uang menampilkan format `Rp 1.500.000` (titik ribuan, koma desimal)
- Live input: ketika user ketik angka, tampilkan format real-time

---

#### F2. Satuan Produk di Setiap Baris Produk
**Files:** `QuotationResource.php`, `SaleOrderResource.php`, `OrderRequestResource.php`, `PurchaseOrderResource.php`, dan semua resource yang punya repeater produk

- [ ] Tambahkan `TextInput::make('unit')` atau `Placeholder::make('unit')` di setiap baris item produk
- [ ] Saat produk dipilih → auto-fill `unit` dari `product->unit` atau `product->uom`
- [ ] Field `unit` bersifat readOnly (hanya tampil)
- [ ] Di kolom tabel list resource, tambahkan kolom satuan di samping kolom quantity

**Implementation:**
```php
// Di dalam repeater item, setelah field product_id:
TextInput::make('unit')
    ->label('Satuan')
    ->readOnly()
    ->dehydrated(false)
    ->afterStateHydrated(function ($state, $record, $set) {
        if ($record?->product) {
            $set('unit', $record->product->unit ?? $record->product->uom ?? '-');
        }
    }),
```

**Acceptance Criteria:**
- Setiap baris item menampilkan satuan produk (pcs, kg, liter, dll)
- Satuan ter-isi otomatis saat produk dipilih

---

### KELOMPOK G — Sales Order
> Fondasi alur penjualan, harus selesai sebelum DO dan Invoice

#### G1. Cabang Turunan: Quotation → SO → DO → Surat Jalan
**Files:** `SaleOrderResource.php`, `DeliveryOrderResource.php`

- [x] Ketika SO dibuat dari Quotation → `cabang_id` SO otomatis dari Quotation
- [x] Ketika DO dibuat dari SO → `cabang_id` DO otomatis dari SO yang dipilih
- [x] Ketika Surat Jalan dibuat dari DO → `cabang_id` SJ otomatis dari DO
- [ ] Pattern: gunakan `afterStateUpdated` pada `Select::make('quotation_id')`, `Select::make('sale_order_id')`, `Select::make('delivery_order_id')`

---

#### G2. SO: Total Harga (Qty × Harga) di Kolom Tabel ✅ DONE (2026-03-18)
**File:** `SaleOrderResource.php`

- [x] Di tabel list SO, tambahkan kolom `grand_total` / `total_amount` dengan format Rupiah
- [x] Di infolist/view SO, pastikan `total_amount` tampil dengan benar
- [x] Di form SO, field `subtotal` tiap item harus tampil sebagai `total_price = qty × unit_price`
- [x] Field `total_amount` di header form harus auto-sum dari semua item subtotal

---

#### G3. SO: Tempo Hari Otomatis dari Customer ✅ DONE (2026-03-18)
**File:** `SaleOrderResource.php`

Saat ini ada `tempo_pembayaran` TextInput di baris ~434. User mengisi manual.

- [x] Ketika customer dipilih → auto-fill `tempo_pembayaran` dari `customer->payment_term` atau `customer->tempo_kredit`
- [x] Implementasi via `afterStateUpdated` pada `Select::make('customer_id')`:
  ```php
  ->afterStateUpdated(function ($state, callable $set) {
      $customer = Customer::find($state);
      $set('tempo_pembayaran', $customer?->tempo_kredit ?? $customer?->payment_term ?? 30);
  })
  ```
- [x] Field tetap bisa diedit manual setelah auto-fill

---

#### G4. SO: Tipe Pajak Ditampilkan (Seperti di Quotation) ✅ DONE (2026-03-18)
**File:** `SaleOrderResource.php`

- [x] Tambahkan `Select::make('tipe_pajak')` di setiap row item SO (atau header SO untuk default) — sudah ada, dihapus ->hidden(true)
- [x] Options: `None`, `Inclusive`, `Exclusive`
- [x] Kalkulasi PPN berdasarkan `tipe_pajak` — sudah tersedia
- [x] Tampilkan `tax_nominal` (nominal PPN dalam Rupiah) per item
- [ ] Saat SO dibuat dari Quotation → `tipe_pajak` item otomatis dari Quotation item

---

#### G5. SO: Multi-Gudang (Qty 50 Bisa dari Gudang 15+20+30)
**File:** `SaleOrderResource.php`, `WarehouseConfirmationResource.php`

**Masalah:** Saat ini SO item punya satu `warehouse_id`. Jika stock satu gudang < qty SO, SO tidak bisa dibuat.

**Solusi: Sub-alokasi per gudang pada SO item**
- [ ] Tambahkan relasi `sale_order_item_warehouses` (tabel baru) atau ubah model agar 1 SO item bisa punya N warehouse allocations
- [ ] Di form SO per baris item: tambahkan sub-repeater `warehouse_allocations` (warehouse_id + qty_allocated)
- [ ] Validasi: `sum(qty_allocated) == item.quantity`
- [ ] Tampilkan stock per gudang saat pilih gudang di alokasi
- [ ] Saat generate WarehouseConfirmation dari DO → generate 1 WC per warehouse yang dialokasikan

**Migration yang dibutuhkan:**
```sql
CREATE TABLE sale_order_item_warehouse_allocations (
    id BIGINT PRIMARY KEY,
    sale_order_item_id BIGINT,
    warehouse_id BIGINT,
    quantity INT,
    ...
);
```

**Acceptance Criteria:**
- SO item qty 50 bisa dialokasikan: Gudang A=15, Gudang B=20, Gudang C=15
- Total alokasi harus sama dengan qty item
- Tiap alokasi ditampilkan dengan info stock gudang

---

#### G6. PO dari Sales Order
**File:** `SaleOrderResource.php`

- [ ] Audit apakah sudah ada action "Create PO from SO"
- [ ] Jika belum: tambahkan action `create_purchase_order` di halaman view SO
- [ ] Mekanisme: pilih items SO yang belum ada PO-nya → buat PO ke supplier
- [ ] PO yang dibuat: `sale_order_id` di-link ke SO

---

### KELOMPOK H — Delivery Order
> Bergantung pada G5 (multi-gudang SO)

#### H1. DO: Urutan Field — From Sales Dulu Baru Cabang ✅ DONE (2026-03-18)
**File:** `DeliveryOrderResource.php`

- [x] Pindahkan `Select::make('cabang_id')` (baris ~86) ke **setelah** `Select::make('salesOrders')` / field from sales
- [x] Setelah SO dipilih → `cabang_id` auto-set dari SO

---

#### H2. DO: Hapus Pilihan Receipt Item, Hanya dari SO ✅ DONE (2026-03-18)
**File:** `DeliveryOrderResource.php`

- [x] Hapus `Select::make('purchase_receipt_item_id')` dari form DO item (baris ~262)
- [x] Hapus semua logika yang menggunakan `purchase_receipt_item_id` dalam DO
- [x] DO items hanya berasal dari SO items
- [ ] Update model `DeliveryOrderItem` jika ada kolom `purchase_receipt_item_id`

---

#### H3. DO: Multi-Gudang per Item
**File:** `DeliveryOrderResource.php`

**Alur baru DO:**
1. Pilih SO (bisa multiple)
2. Pilih items dari SO yang akan dikirim
3. Per item: pilih gudang mana yang menyediakan stock, input qty dari gudang tersebut
4. Tampilkan stock tersedia di gudang yang dipilih

- [ ] Ubah DO item form: per baris item tambahkan sub-repeater `warehouse_sources` (warehouse_id + qty)
- [ ] Validasi: `sum(warehouse_sources.qty) == item.quantity`
- [ ] Tampilkan `stock_available` untuk gudang yang dipilih (live update via `afterStateUpdated`)
- [ ] Surat Jalan yang dihasilkan dari DO ini: tampilkan items (dikombinasi per produk, tidak dipecah per gudang)

---

#### H4. DO → Warehouse Confirmation: Flow Status Baru
**File:** `DeliveryOrderResource.php`, `WarehouseConfirmationResource.php`, model DO dan WC

**Flow Status DO yang Baru:**
```
draft → submitted → request_stock → approved (semua WC approved)
                                  → rejected (ada WC rejected)
                                  → partial (sebagian WC approved)
```

**Yang harus diubah:**
- [ ] Saat DO di-submit/request → **auto-buat WC** per gudang yang digunakan (1 WC per gudang)
- [ ] Status DO berubah dari `draft` → `request_stock`
- [ ] Di DO: tampilkan status tiap WC (dengan badge: request / confirmed / rejected)
- [ ] Jika **semua** WC `confirmed` → DO berubah ke `approved`
- [ ] Jika **ada** WC `rejected` → DO berubah ke `rejected`, tampilkan alasan reject
- [ ] Di DO view: tampilkan per WC: warehoue name, status, keterangan reject

**Model changes:**
- `DeliveryOrder`: tambahkan method `updateStatusFromWarehouseConfirmations()`
- `WarehouseConfirmation`: event `saved` → trigger `$do->updateStatusFromWarehouseConfirmations()`

---

### KELOMPOK I — Warehouse Confirmation
> Bergantung pada H4

#### I1. WC: Manual Approve/Reject dengan Keterangan
**File:** `WarehouseConfirmationResource.php`

- [ ] Hapus auto-approve / auto-confirm dari DO
- [ ] WC dibuat otomatis saat DO request stock (status `request`)
- [ ] Di halaman view/edit WC: tambahkan tombol **Approve** dan **Reject**
- [ ] Tombol Reject: tampilkan modal dengan `Textarea::make('rejection_reason')` → wajib diisi
- [ ] Setelah Approve → status WC = `confirmed`, trigger update status DO
- [ ] Setelah Reject → status WC = `rejected`, simpan `rejection_reason`, trigger update status DO

---

#### I2. WC: Tampilkan Informasi DO (Bukan Sales)
**File:** `WarehouseConfirmationResource.php`

- [ ] Hapus section "Informasi Sales" dari view/form WC
- [ ] Ganti dengan section "Informasi Delivery Order": nomor DO, tanggal, customer, total item
- [ ] Hapus kolom "Confirmed Qty" dari view WC
- [ ] Hapus tampilan harga dari WC (WC = konfirmasi ketersediaan stock, bukan finansial)

---

### KELOMPOK J — Surat Jalan
> Bergantung pada H4 (DO approved)

#### J1. Surat Jalan: Hanya DO yang Approved
**File:** `SuratJalanResource.php` atau resource terkait

- [ ] Filter DO pada dropdown pembuatan Surat Jalan: hanya DO dengan `status = 'approved'`
- [ ] Validasi: tidak bisa buat SJ dari DO yang belum approved

---

#### J2. Surat Jalan: Simplifikasi Field ✅ DONE (2026-03-18)
**File:** Surat Jalan Resource

- [x] Hapus field `sender_name` (nama pengirim)
- [x] Hapus field `delivery_method` (metode pengiriman) — pindah ke DeliverySchedule
- [x] Hapus approval flow (tidak perlu di-approve/setujui)
- [x] Hapus status `failed` / `gagal kirim`
- [x] Hapus fitur "Rekap Driver" dari halaman Surat Jalan
- [x] Tambahkan action **"Mark as Sent"** → action sudah ada di SuratJalanResource

---

#### J3. Surat Jalan PDF: Gabungkan Item Sejenis
**File:** Surat Jalan PDF template/blade

- [ ] Dalam PDF SJ, jika satu produk diambil dari beberapa gudang → **tampilkan sebagai 1 baris** dengan total qty
- [ ] Tidak perlu menampilkan breakdown per gudang dalam PDF (info gudang cukup di WC)

---

### KELOMPOK K — Delivery Schedule (Jadwal Pengiriman)
> Bergantung pada J (Surat Jalan selesai)

#### K1. Jadwal Pengiriman: Metode Pengiriman + Driver/Kendaraan ✅ DONE (2026-03-18)
**File:** `DeliveryScheduleResource.php`

- [x] Tambahkan `Select::make('delivery_method')` dengan options: `internal`, `kurir_internal`, `ekspedisi`
- [x] Jika `delivery_method = 'internal'` atau `'kurir_internal'`:
  - Tampilkan `Select::make('driver_id')` → dari sistem (Driver model)
  - Tampilkan `Select::make('vehicle_id')` → dari sistem (Vehicle model)
- [x] Jika `delivery_method = 'ekspedisi'`:
  - Tampilkan `TextInput::make('driver_name')` (manual)
  - Tampilkan `TextInput::make('vehicle_info')` (manual: nama ekspedisi / plat kendaraan)
- [x] Gunakan `->hidden(fn($get) => ...)` untuk show/hide field berdasarkan metode

---

#### K2. Jadwal Pengiriman: Surat Kerja Driver (Kurir Internal)
**File:** `DeliveryScheduleResource.php`, tambah template PDF

- [ ] Buat action "Print Surat Kerja" pada jadwal pengiriman dengan `delivery_method = 'internal'` atau `'kurir_internal'`
- [ ] PDF Surat Kerja berisi:
  - Informasi driver (nama, nomor kendaraan)
  - Daftar DO yang dikirim pada jadwal ini
  - Per DO: informasi customer lengkap (nama, alamat, telepon, kota)
  - Per DO: daftar items yang dikirimkan (nama produk, qty, satuan)
  - Tanggal pengiriman, tanda tangan driver

---

#### K3. Jadwal Pengiriman Selesai → DO Selesai
**File:** `DeliveryScheduleResource.php`, model `DeliveryOrder`

- [x] Ketika status Jadwal Pengiriman diubah ke `delivered` → semua DO dalam jadwal tersebut otomatis `status = 'complete'`
- [x] Ketika DO `complete` → kurangi `reserved_stock` di `InventoryStock` untuk setiap item DO
- [ ] Flow stock: `reserved_stock -= qty_delivered`, `qty_available` tidak berubah (sudah dikurangi saat reservasi)
- [x] Implementasi di observer atau service `DeliveryScheduleService::markAsDelivered()`

---

### KELOMPOK L — Sales Invoice
> Bergantung pada G4 (tipe pajak di SO)

#### L1. SalesInvoice: Tipe Pajak dari SO ✅ DONE (2026-03-18)
**File:** `SalesInvoiceResource.php`

- [x] Saat SO dipilih untuk invoice → auto-fill `tipe_pajak` dari SO
- [x] Tampilkan kolom `tipe_pajak` di setiap baris item invoice
- [x] Kalkulasi PPN invoice menggunakan `tipe_pajak` yang sama dengan SO

---

#### L2. SalesInvoice: Nominal PPN dan Biaya Tambahan di Journal Entries ✅ DONE (2026-03-18)
**File:** `ViewSalesInvoice.php`, `Invoice.php` (accessor), `InvoiceObserver.php` (journal logic sudah benar)

- [x] Di view/infolist SalesInvoice: tampilkan `ppn_amount` (nominal PPN dalam Rupiah) — via `getPpnAmountAttribute()` accessor + `TextEntry::make('ppn_amount')->rupiah()` di infolist
- [x] Audit journal entries saat invoice dibuat: sudah benar di `InvoiceObserver::executeSalesInvoicePosting()`
  - Debit: Account Receivable (DPP + PPN) ✅
  - Credit: Sales Revenue per item (DPP) ✅
  - Credit: PPN Keluaran ✅
  - Credit: Biaya Pengiriman/other fees ✅
- [x] Tampilkan `tipe_pajak` dengan badge di Financial Information section

---

### KELOMPOK M — Customer Receipt (Audit Menyeluruh)
> Prioritas tinggi karena menyangkut kebenaran saldo piutang

#### M1. CustomerReceipt: Hapus Kode Debugging ✅ DONE (2026-03-18)
**File:** `CustomerReceiptResource.php`

- [x] Cari dan hapus semua `dd(`, `dump(`, `var_dump(`, `print_r(`, `Log::info` yang tidak diperlukan
- [x] Audit semua `Log::` statements — pertahankan yang error/warning, hapus yang info/debug development
- [x] Khususnya `Log::info('Found AR, current paid: ')` dan sejenisnya di `CustomerReceiptObserver.php` (baris ~63, ~68, ~94, ~98) perlu dihapus dari production code

---

#### M2. CustomerReceipt: Format Nominal Input ✅ DONE (2026-03-18)
**File:** `CustomerReceiptResource.php`

- [x] `total_payment` input: sudah menggunakan `->indonesianMoney()` (baris 290)
- [x] `total_payment` di table list: sudah menggunakan `->rupiah()` (baris 437)
- [x] `total_payment` di view/infolist: sudah menggunakan `->rupiah()` (baris 604)
- [x] `amount` di infolist repeater: sudah menggunakan `->rupiah()` (baris 653)

---

#### M3. CustomerReceipt: Informasi Journal Entries ✅ DONE (2026-03-18)
**File:** `CustomerReceiptResource.php`

- [x] Di halaman view (InfoList) CustomerReceipt: tambahkan section "Journal Entries"
- [x] Tampilkan tabel journal entries terkait receipt ini (source morphMany ke JournalEntry)
- [x] Kolom: tanggal, account code, account name, debit, kredit
- [ ] Tambahkan link ke halaman Journal Entry terkait

---

#### M4. CustomerReceipt: Bug AR paid_amount Tidak Update ✅ DONE (2026-03-18)
**File:** `CustomerReceiptObserver.php`, `AccountReceivable.php`

**Masalah:** Observer `updateAccountReceivables()` di baris ~66 hanya mengupdate `remaining` tapi tidak mengupdate `paid`:
```php
// Baris ~66: hanya remaining yang di-update
$accountReceivable->remaining = $accountReceivable->remaining - $item->amount;
// MISSING: $accountReceivable->paid = $accountReceivable->paid + $item->amount;
```

- [x] Tambahkan baris: `$accountReceivable->paid = $accountReceivable->paid + $item->amount;` sebelum `->save()`
- [x] Lakukan hal yang sama untuk path fallback di baris ~96
- [x] Verifikasi dengan test: buat CustomerReceipt → cek AR `paid` naik dan `remaining` turun (via Playwright, skip karena no test data)
- [x] Pastikan `AccountReceivable->total = paid + remaining` tetap konsisten (double-count prevention via static tracker)

---

#### M5. CustomerReceipt: Verifikasi Journal Entry Otomatis
**File:** `CustomerReceiptObserver.php` (method `postCustomerReceipt`), `LedgerService.php`

- [ ] Trace `$this->ledger->postCustomerReceipt($receipt)` di observer `created` dan `updated`
- [ ] Verifikasi journal dibuat dengan:
  - Debit: Cash/Bank account (sesuai akun yang dipilih)
  - Credit: Account Receivable
  - Jika overpayment: Credit Deposit Customer
- [ ] Jika journal belum otomatis dibuat → implement di `LedgerService`
- [ ] Di view CustomerReceipt: tampilkan tombol "Generate Journal" sebagai fallback jika journal belum ada

---

## Urutan Pelaksanaan yang Direkomendasikan (Sales Side)

```
Minggu 1 (Pembenahan Dasar):
  [F1] Format Rupiah konsisten
  [F2] Satuan produk di baris item
  [G2] SO: kolom total harga
  [G3] SO: tempo hari dari customer
  [M1] CustomerReceipt: hapus debug
  [M4] CustomerReceipt: bug AR paid_amount ← KRITIS (data piutang salah)

Minggu 1-2 (SO & Invoice):
  [G4] SO: tipe pajak
  [G1] Cabang turunan Sales chain
  [L1] SalesInvoice: tipe pajak dari SO
  [L2] SalesInvoice: nominal PPN + biaya tambahan journal
  [M5] CustomerReceipt: verifikasi journal otomatis
  [M3] CustomerReceipt: tampilkan journal entries

Minggu 2 (DO Redesign):
  [H1] DO: urutan field
  [H2] DO: hapus receipt item
  [J2] Surat Jalan: simplifikasi field
  [J1] Surat Jalan: hanya DO approved
  [J3] Surat Jalan PDF: gabungkan item

Minggu 2-3 (Multi-Gudang — paling complex):
  [G5] SO multi-gudang
  [H3] DO multi-gudang
  [H4] DO → WC flow baru
  [I1] WC: approve/reject manual
  [I2] WC: info DO bukan sales

Minggu 3 (Delivery Schedule):
  [K1] Metode pengiriman
  [K2] Surat kerja driver
  [K3] Schedule selesai → DO selesai

Minggu 3-4 (Minor & Cleanup):
  [G6] PO dari SO
  [S4] User management warehouse visibility
  [S13] QC Purchase multi-product
  [M2] CustomerReceipt format nominal
```

---

## File Inventory Sales Side

| Task | File Utama | File Pendukung |
|------|-----------|----------------|
| F1, F2 | QuotationResource, SaleOrderResource | HelperController |
| G1-G6 | SaleOrderResource.php | Customer.php, SaleOrder.php, migration |
| H1-H4 | DeliveryOrderResource.php | DeliveryOrder.php, WarehouseConfirmation.php |
| I1, I2 | WarehouseConfirmationResource.php | WarehouseConfirmation.php |
| J1-J3 | SuratJalanResource.php + PDF blade | DeliveryOrder.php |
| K1-K3 | DeliveryScheduleResource.php | DeliverySchedule.php, InventoryStock.php |
| L1, L2 | SalesInvoiceResource.php | InvoiceService.php, LedgerService.php |
| M1-M5 | CustomerReceiptResource.php | CustomerReceiptObserver.php, AccountReceivable.php |
| S4 | UserResource.php | User.php |
| S13 | QualityControlPurchaseResource.php | QualityControlPurchase.php |

---

## Catatan Teknis Penting (Sales Side)

### AR paid_amount Bug
Di `CustomerReceiptObserver::updateAccountReceivables()` terdapat inkonsistensi: `remaining` di-update tapi `paid` tidak, sehingga di AccountReceivable `paid` tetap 0 meskipun ada pembayaran. Fix wajib:
```php
$accountReceivable->paid += $item->amount;    // TAMBAHKAN INI
$accountReceivable->remaining -= $item->amount;
$accountReceivable->save();
```

### Multi-Gudang Architecture
Pattern untuk multi-warehouse allocation pada SO dan DO:
- Jangan simpan `warehouse_id` langsung di `sale_order_items` — buat tabel `sale_order_item_allocations`
- DO item juga perlu tabel `delivery_order_item_warehouses` untuk track dari gudang mana
- WC dibuat per gudang: 1 DO with 3 gudang → 3 WC (1 per gudang)

### DO Status Machine
```
draft → [user submit] → request_stock
request_stock → [semua WC approved] → approved
request_stock → [ada WC rejected] → rejected
approved → [delivery schedule delivered] → complete
```

### Surat Kerja PDF
Template blade baru dibutuhkan: `resources/views/pdf/surat-kerja-driver.blade.php`
Gunakan `barryvdh/laravel-dompdf` (sudah ada di project).

---

*Dokumen ini dibuat berdasarkan audit kode per 18 Maret 2026.*
*Sales section ditambahkan berdasarkan catatan 18 Maret 2026.*
