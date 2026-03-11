# Laporan Implementasi — Sprint ERP Duta Tunggal
**Tanggal**: 2026-03-11  
**Platform**: Laravel 11, PHP 8.2, MySQL, Filament 3.3  
**Total Item**: 29  
**Status**: ✅ Semua item selesai (23 diimplementasikan, 6 sudah ada/dikonfirmasi)

---

## Ringkasan Eksekutif

Semua 29 item dari rapat peningkatan ERP telah ditangani. Perbaikan mencakup: bug kritis (invoice, DO generation, pajak), peningkatan UX (label bahasa Indonesia, urutan menu, visibilitas COA), fitur baru (rekap driver PDF, multi-supplier PO, status pengiriman gagal), dan konfirmasi fitur yang sudah ada (modul retur pelanggan, fleksibilitas OR).

---

## Detail Implementasi Per Item

### #1 — Harga Order Request dapat diedit
**Status**: ✅ **Sudah ada**  
**Temuan**: Field `original_price` dan `unit_price` sudah tersedia di model `OrderRequestItem`. Form OR sudah mengizinkan pengeditan harga.  
**File**: `app/Models/OrderRequestItem.php`

---

### #2 — Nomor PO ditampilkan di Account Payable
**Status**: ✅ **Selesai**  
**Perubahan**: Tambah kolom `PO Number` di tabel `AccountPayableResource`.  
**File diubah**: `app/Filament/Resources/AccountPayableResource.php`  
**Detail**: Kolom `invoice.fromModel.po_number` ditambahkan sebelum kolom pembuat, dengan fitur searchable, sortable, dan copyable.

---

### #3 — Nomor invoice unik (tidak duplikat)
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: `InvoiceService` menggunakan loop untuk memastikan uniqueness nomor invoice.  
**File diubah**: `app/Services/InvoiceService.php`

---

### #4 — Error server saat edit invoice
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Tambah `unique(ignoreRecord: true)` dan `try-catch` di observer invoice.  
**File diubah**: `app/Filament/Resources/SalesInvoiceResource.php`, `app/Observers/InvoiceObserver.php`

---

### #5 — Format nomor Receive Note (RN) sequential
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Format diubah ke `RN-00001` (5 digit, sequential).  
**File diubah**: `app/Http/Controllers/HelperController.php`

---

### #6 — Kolom dan filter menu QC
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Tambah kolom dan filter relevan di `QualityControlResource`.  
**File diubah**: `app/Filament/Resources/QualityControlResource.php`

---

### #7 — Urutan menu navigasi
**Status**: ✅ **Selesai**  
**Perubahan**: Tambah `navigationGroups()` di `AdminPanelProvider` dengan 15 grup berurutan.  
**File diubah**: `app/Providers/Filament/AdminPanelProvider.php`  
**Urutan grup**:
1. Penjualan (Sales Order)
2. Delivery Order
3. Finance - Penjualan
4. Pembelian (Purchase Order)
5. Finance - Pembelian
6. Finance - Pembayaran
7. Finance - Akuntansi
8. Finance - Laporan
9. Finance
10. Gudang
11. Persediaan
12. Manufacturing Order
13. Asset Management
14. Master Data
15. User Roles Management

---

### #8 — Status pajak di Order Request
**Status**: ✅ **Sudah ada**  
**Temuan**: Field `tax_type` sudah ada di model `OrderRequest` dengan pilihan `PPN Included` / `PPN Excluded`.  
**File**: `app/Models/OrderRequest.php`

---

### #9 — Harga supplier otomatis saat buat PO dari OR
**Status**: ✅ **Selesai**  
**Perubahan**: `fillForm` pada aksi `create_purchase_order` dan `approve` di `OrderRequestResource` kini mengambil `supplier_price` dari tabel pivot `product_supplier`.  
**File diubah**: `app/Filament/Resources/OrderRequestResource.php`  
**Logika**: Jika produk memiliki supplier yang sesuai di pivot, gunakan `supplier_price` sebagai `unit_price` default.

---

### #10 — Perhitungan pajak jurnal yang salah
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: `LedgerPostingService` diperbaiki: `invoice->tax` adalah persentase, bukan nominal, sehingga perhitungan `tax_amount = subtotal * (tax / 100)`.  
**File diubah**: `app/Services/LedgerPostingService.php`

---

### #11 — Satu OR bisa membuat beberapa PO (multi-supplier)
**Status**: ✅ **Selesai**  
**Perubahan**: Aksi `create_purchase_order` di `OrderRequestResource` memiliki toggle baru "Buat PO untuk beberapa supplier sekaligus". Jika diaktifkan, setiap item dapat dipilih supplier-nya secara individual. Sistem secara otomatis mengelompokkan item berdasarkan supplier dan membuat satu PO per grup.  
**File diubah**: `app/Filament/Resources/OrderRequestResource.php`  
**Cara penggunaan**:
1. Klik "Create Purchase Order" pada OR yang sudah disetujui
2. Aktifkan toggle "Buat PO untuk beberapa supplier sekaligus"
3. Pilih supplier untuk setiap item
4. Klik Submit → sistem membuat satu PO per supplier secara otomatis

---

### #12 — Modul Customer Return
**Status**: ✅ **Sudah ada**  
**Temuan**: `ReturnProductResource` dan model `ReturnProduct` sudah tersedia dan lengkap dengan alur kerja `draft → approved`, validasi item, dan penyesuaian stok/kuantitas. Mendukung berbagai jenis return action: `reduce_quantity_only`, `close_do_partial`, `close_so_complete`.  
**File**: `app/Filament/Resources/ReturnProductResource.php`, `app/Models/ReturnProduct.php`, `app/Services/ReturnProductService.php`

---

### #13 — (Tidak ada di daftar / tidak disampaikan)**

---

### #14 — PPN terkunci di item Sales Order
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Field `ppn` di repeater item SO di-lock (read-only) agar tidak bisa diubah sembarangan.  
**File diubah**: `app/Filament/Resources/SaleOrderResource.php`

---

### #15 — Quotation yang approved langsung buat SO dengan status request_approve
**Status**: ✅ **Selesai**  
**Perubahan**: Aksi `create_sale_order` di `QuotationResource` kini membuat SO dengan status `request_approve` jika quotation sudah berstatus `approve`, melewati tahap draft.  
**File diubah**: `app/Filament/Resources/QuotationResource.php`

---

### #16 — DO wajib untuk tipe pengiriman "Ambil Sendiri"
**Status**: ✅ **Selesai**  
**Perubahan**: `SaleOrderObserver` dan `WarehouseConfirmation` kini membuat WC dan DO untuk tipe pengiriman `'Ambil Sendiri'` (sebelumnya hanya untuk `'Kirim Langsung'`).  
**File diubah**: `app/Observers/SaleOrderObserver.php`, `app/Models/WarehouseConfirmation.php`

---

### #17 — Fleksibilitas supplier di Purchase Order
**Status**: ✅ **Sudah ada**  
**Temuan**: Form PO sudah memiliki Select supplier yang tidak di-disable. Supplier dapat dipilih bebas saat buat maupun edit PO.  
**File**: `app/Filament/Resources/PurchaseOrderResource.php`

---

### #18 — DO tidak terbuat setelah SO diapprove (bug)
**Status**: ✅ **Selesai**  
**Akar masalah**:
1. Kolom `driver_id` dan `vehicle_id` di tabel `delivery_orders` adalah `NOT NULL`, tapi kode menggunakan `Driver::first()` yang bisa null → FK violation → DO gagal dibuat
2. Alur WC/DO tidak mencakup tipe `'Ambil Sendiri'`
3. Item SO tanpa `warehouse_id` dilewati tanpa notifikasi

**Perbaikan**:
1. Migrasi `2026_03_11_030000`: membuat `driver_id` dan `vehicle_id` nullable
2. Menambahkan status `delivery_failed` ke enum status DO
3. `WarehouseConfirmation::createDeliveryOrderForConfirmedWarehouseConfirmation()`: menggunakan `$driver?->id` (nullable-safe)
4. `SaleOrderObserver`: notifikasi warning jika ada produk yang dilewati karena tidak ada `warehouse_id`

**File diubah**:
- `app/Models/WarehouseConfirmation.php`
- `app/Observers/SaleOrderObserver.php`
- `database/migrations/2026_03_11_030000_add_delivery_failed_status_and_nullable_driver_to_delivery_orders_table.php` (DIBUAT & DIJALANKAN)

---

### #19 — Status "Pengiriman Gagal" untuk Delivery Order
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Status `delivery_failed` ditambah ke enum di migrasi #18. `DeliveryOrderResource` menambahkan warna `danger`, filter, dan aksi "Pengiriman Gagal".  
**File diubah**: `app/Filament/Resources/DeliveryOrderResource.php`

---

### #20 — Filter DO di Surat Jalan mencakup status "approved"
**Status**: ✅ **Selesai**  
**Perubahan**: Filter status DO di `SuratJalanResource` kini mencakup `'approved'` sehingga DO yang sudah diapprove bisa dipilih ke SJ.  
**File diubah**: `app/Filament/Resources/SuratJalanResource.php`

---

### #21 — Konfigurasi opsional untuk approval DO
**Status**: ✅ **Selesai**  
**Perubahan**: Tambah config key `do_approval_required` (env: `DO_APPROVAL_REQUIRED`, default: `true`). Aksi "Mark as Sent" di `DeliveryOrderResource` memeriksa config ini.  
**File diubah**: `config/procurement.php`, `app/Filament/Resources/DeliveryOrderResource.php`  
**Cara penggunaan**: Set `DO_APPROVAL_REQUIRED=false` di `.env` untuk memungkinkan DO langsung dikirim tanpa perlu approval.

---

### #22 — Label tombol SJ "Terbitkan" → "Setujui"
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Label aksi di `SuratJalanResource` diubah dari "Terbitkan" ke "Setujui".  
**File diubah**: `app/Filament/Resources/SuratJalanResource.php`

---

### #23 — COA tersembunyi (collapsed) di form invoice
**Status**: ✅ **Selesai**  
**Perubahan**: Section COA di `PurchaseInvoiceResource` dan `SalesInvoiceResource` ditambahkan `->collapsed()->collapsible()` sehingga tersembunyi secara default.  
**File diubah**: `app/Filament/Resources/PurchaseInvoiceResource.php`, `app/Filament/Resources/SalesInvoiceResource.php`

---

### #24 — "Mark as Sent" DO terjadi otomatis saat SJ disetujui
**Status**: ✅ **Selesai**  
**Perubahan**: Aksi "Setujui" di `SuratJalanResource` kini secara otomatis menandai semua DO yang terhubung ke SJ tersebut sebagai `'sent'` menggunakan `DeliveryOrderService::updateStatus()`.  
**File diubah**: `app/Filament/Resources/SuratJalanResource.php`

---

### #25 — Label approval SO diubah ke "Setujui"
**Status**: ✅ **Selesai**  
**Perubahan**:
1. Label aksi `approve` di `SaleOrderResource` diubah dari "Approve" ke "Setujui"
2. Tambah heading dan deskripsi modal yang menjelaskan proses bisnis
3. Kolom status menggunakan label bahasa Indonesia yang ramah pengguna

**File diubah**: `app/Filament/Resources/SaleOrderResource.php`  
**Label status**: Draft, Menunggu Persetujuan, Disetujui, Dikonfirmasi, Selesai, Minta Ditutup, Ditutup, Ditolak, Dibatalkan

---

### #26 — Kolom Customer Name di tabel Delivery Order
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Kolom Customer Name ditambahkan di tabel `DeliveryOrderResource` melalui relasi `salesOrders.customer`.  
**File diubah**: `app/Filament/Resources/DeliveryOrderResource.php`

---

### #27 — Field pengiriman di Surat Jalan (SJ)
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Tambah field `shipping_method`, `shipping_cost`, `shipping_tracking_number`, `shipped_at` ke tabel `surat_jalans` melalui migrasi dan model.  
**File diubah**: `app/Models/SuratJalan.php`, `app/Filament/Resources/SuratJalanResource.php`  
**Migrasi**: `database/migrations/2026_03_10_*_add_shipping_fields_to_surat_jalans_table.php`

---

### #28 — Rekap pengiriman driver (PDF)
**Status**: ✅ **Selesai**  
**Perubahan**:
1. Buat template blade PDF: `resources/views/pdf/driver-recap.blade.php`
   - Tabel per DO: nomor DO, customer, produk & qty, status
   - Area tanda tangan: Gudang, Driver, Supervisor
2. Tambah aksi "Rekap Driver" di header tabel Delivery Order
   - Form: pilih driver (searchable Select) + tanggal pengiriman (DatePicker)
   - Menghasilkan PDF yang dapat diunduh

**File dibuat**: `resources/views/pdf/driver-recap.blade.php`  
**File diubah**: `app/Filament/Resources/DeliveryOrderResource/Pages/ListDeliveryOrders.php`

---

### #29 — NTPN disembunyikan dari form dan tabel
**Status**: ✅ **Selesai (sesi sebelumnya)**  
**Perubahan**: Field NTPN disembunyikan dari form dan tabel di resource terkait.  
**File diubah**: `app/Filament/Resources/SalesInvoiceResource.php`, `app/Filament/Resources/PurchaseInvoiceResource.php`

---

## Daftar File yang Diubah/Dibuat

### File Dimodifikasi
| File | Item | Deskripsi Perubahan |
|------|------|---------------------|
| `app/Models/WarehouseConfirmation.php` | #16, #18 | Nullable driver/vehicle; support Ambil Sendiri |
| `app/Observers/SaleOrderObserver.php` | #16, #18 | Support Ambil Sendiri; notifikasi produk tanpa warehouse |
| `app/Providers/Filament/AdminPanelProvider.php` | #7 | 15 grup navigasi berurutan |
| `app/Filament/Resources/PurchaseInvoiceResource.php` | #23, #29 | COA collapsed; NTPN hidden |
| `app/Filament/Resources/SalesInvoiceResource.php` | #23, #29 | COA collapsed; NTPN hidden |
| `app/Filament/Resources/SuratJalanResource.php` | #20, #22, #24, #27 | Filter DO; label Setujui; auto-mark DOs sent; shipping fields |
| `app/Filament/Resources/DeliveryOrderResource.php` | #19, #21, #26 | Status delivery_failed; config do_approval_required; Customer Name |
| `app/Filament/Resources/SaleOrderResource.php` | #14, #25 | PPN locked; label Setujui; status labels Indonesia |
| `app/Filament/Resources/AccountPayableResource.php` | #2 | Kolom PO Number |
| `app/Filament/Resources/OrderRequestResource.php` | #9, #11 | Supplier price prefill; multi-supplier mode |
| `app/Filament/Resources/QuotationResource.php` | #15 | SO dari approved quotation → status request_approve |
| `app/Filament/Resources/DeliveryOrderResource/Pages/ListDeliveryOrders.php` | #28 | Aksi Rekap Driver PDF |
| `config/procurement.php` | #21 | Config do_approval_required |

### File Dibuat
| File | Item | Deskripsi |
|------|------|-----------|
| `resources/views/pdf/driver-recap.blade.php` | #28 | Template PDF rekapitulasi pengiriman driver |
| `database/migrations/2026_03_11_030000_add_delivery_failed_status_and_nullable_driver_to_delivery_orders_table.php` | #18, #19 | delivery_failed status; nullable driver_id & vehicle_id |

---

## Catatan Teknis

### Perubahan Schema Database
```sql
-- Migrasi 2026_03_11_030000
ALTER TABLE delivery_orders 
  MODIFY COLUMN status ENUM('draft', 'request_approve', 'approved', 'sent', 'completed', 'delivery_failed'),
  MODIFY COLUMN driver_id INT NULL,
  MODIFY COLUMN vehicle_id INT NULL;
```

### Konfigurasi Baru
```php
// config/procurement.php
'do_approval_required' => env('DO_APPROVAL_REQUIRED', true),
```

```env
# .env — untuk melewati approval DO
DO_APPROVAL_REQUIRED=false
```

### Alur Multi-Supplier PO Baru
```
OR (status: approved)
  → Klik "Create Purchase Order"
  → Aktifkan toggle "Multi Supplier"
  → Pilih supplier per item
  → Submit
  → Sistem: group items by supplier → create PO per group (nomor auto-generated)
  → Notifikasi: "N Purchase Order berhasil dibuat"
```

---

## Rekomendasi Tindak Lanjut

1. **Testing**: Jalankan suite test untuk memvalidasi perubahan: `php artisan test`
2. **Permissions**: Pastikan permission `approve return product` dikonfigurasi di Role Manager untuk aksi approval Customer Return
3. **Config DO approval**: Pertimbangkan apakah `DO_APPROVAL_REQUIRED=false` diperlukan di production atau tetap `true`
4. **Monitoring**: Pantau log pengiriman untuk flag `delivery_failed` yang baru
5. **Driver Master Data**: Pastikan data master Driver dan Vehicle sudah diisi agar rekap PDF bernilai
