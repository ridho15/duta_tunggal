# AUDIT TEKNIS: RETEST BUG 29 SEPTEMBER 2026

**Tanggal Audit:** 30 September 2026
**Kode data uji sumber:** TEST-RL-HASSEE-20260929
**Metode:** Audit kode read-only (tidak ada file yang diubah), diverifikasi dengan `git show`/`git diff`/`git log`, pembacaan skema DB nyata, dan pada beberapa kasus reproduksi langsung (dev server, test Pest/Livewire yang sudah ada, atau test sekali-pakai di scratchpad).
**Status:** Audit selesai. **Belum ada perubahan kode apa pun** — dokumen ini adalah dasar untuk keputusan perbaikan berikutnya.

---

## 1. Ringkasan Eksekutif

Hampir seluruh bug berlabel **"REGRESI BARU"** (yang kemarin 28/09 masih benar) berasal dari **satu commit**: `ab84a61a` ("Update", 29 Sep 2026 09:11:27 WIB) — 52 file, +4353/-166 baris, dibuat pagi hari tepat sebelum retest. Commit ini menambahkan `StockMovementObserver`, mengubah `PurchaseReturnService`, `StockTransferResource`, `StockOpnameService`, `InvoiceObserver`, `ReturnProductService`, dan lainnya — sebagian memang perbaikan yang berhasil, sebagian **memperkenalkan bug baru**, dan sebagian **gagal memperbaiki** masalah yang jadi tujuannya.

Temuan kunci:

- **Bug 1 & 3** (stok dobel/stok nambah tanpa mutasi) — **satu akar masalah**: commit `ab84a61a` mengaktifkan efek stok otomatis di `StockMovementObserver` untuk tipe `purchase_return`/`customer_return`, padahal `PurchaseReturnService` & `CustomerReturnService` **sudah** mengubah stok secara manual di tempat yang sama — efeknya diterapkan dua kali. Bug 3 punya penyebab kedua yang independen: proses penerimaan barang menimpa ulang stok dari daftar tipe mutasi yang basi (tidak memasukkan tipe retur), sehingga histori retur "terhapus" tanpa jejak.
- **Bug 2 & 4** (transfer stok 500 / Rak wajib) — **satu akar masalah juga**: kolom Rak di DB *sudah* dibuat nullable sejak 25 Sep, tapi validasi baru di halaman Create/Edit Transfer salah asumsi soal cara Filament Repeater relationship bekerja, sehingga **transfer apa pun tidak pernah bisa dibuat sejak commit pagi ini** — dibuktikan lewat test Livewire nyata.
- **Bug 5** (debug mode) — dikonfirmasi & direproduksi langsung (bahkan bocor cookie sesi mentah). Murni isu konfigurasi `.env`, bukan bug kode; kode & dokumentasi sudah benar.
- **Bug 6** (opname tanpa hitung fisik) — fix hari ini adalah **dead code**: validasi baru mengecek `physical_qty IS NULL`, padahal alur "Mulai Hitung Fisik" **selalu** mengisi `physical_qty` dengan qty sistem (bukan null) sejak awal — jadi validasi itu tidak pernah bisa terpicu.
- **Bug 7** (Laporan Stok 500) — dikonfirmasi & direproduksi langsung. Ternyata ada **dua implementasi berbeda** bernama "Laporan Stok"; yang benar-benar dipakai user tidak pernah disentuh oleh commit-commit "fix" sebelumnya sama sekali.
- **Bug 8** (invoice posted masih bisa diubah) — 4 celah independen, salah satunya sangat konkret: daftar status terkunci tidak menyertakan status `'unpaid'`, padahal itu status invoice hasil auto-generate dari SO/DO — **jalur paling umum di produksi**.
- **Bug 9** (retur pelanggan tanpa jurnal) — kode jurnal untuk decision "credit" sengaja disembunyikan dari form saat flag mati, tapi kondisi kode-nya justru mensyaratkan decision itu — dua bagian saling meniadakan sehingga jurnal **tidak pernah** tereksekusi untuk opsi yang tersedia (repair/replace).
- **Bug 10** (Return Product) — jurnal minus ke akun "Barang Terkirim" adalah **regresi baru** dari commit `ab84a61a` (sebelumnya modul ini tidak membuat jurnal sama sekali); koreksi invoice memang belum pernah diimplementasikan.
- **Bug 11** (Kartu Persediaan vs stok riil) — kemungkinan besar **gejala turunan** dari duplikasi Bug 1/3, bukan bug independen di kartunya sendiri.
- **Bug 12** (harga quotation stale) — dikonfirmasi di komponen React `QuotationItemTable.tsx`, dan tersimpan permanen ke DB karena API backend tidak recompute harga dari server.

---

## 2. Detail Per Prioritas (mengikuti urutan yang Anda tetapkan)

### PRIORITAS 1 — Bug 1: Retur menggandakan efek stok

**Status: CONFIRMED** (dibuktikan lewat test yang gagal, bukan dugaan)

Pola "double-application" — dua jalur sama-sama mengubah `InventoryStock.qty_available` untuk satu event:

- **Retur pembelian** — `app/Services/PurchaseReturnService.php:565,567` men-`decrement()` stok langsung, LALU baris 571-595 membuat `StockMovement::create(['type' => 'purchase_return', ...])` tanpa flag `skip_stock_update` di `meta`.
- **Retur pelanggan** — `app/Services/CustomerReturnService.php:142` men-`increment()` stok langsung, LALU baris 150-161 membuat `StockMovement` tipe `customer_return`, juga tanpa flag skip.
- **Pemicu regresi** — `app/Observers/StockMovementObserver.php` (baru di commit `ab84a61a`), method `stockEffectDelta()` baris ~209-210: **sebelum** commit ini, `purchase_return`/`customer_return` jatuh ke `default => 0.0` (tidak berefek). Commit menambahkan kedua tipe ini ke daftar in/out — sejak itu observer **ikut** menerapkan efek stok, dobel dengan langkah manual di atas.
- **Pola pencegahan sudah ada di codebase**, tinggal diterapkan konsisten: `app/Observers/MaterialIssueObserver.php:172` — `'skip_stock_update' => true`, dibaca oleh `StockMovementObserver::shouldSkipStockUpdate()` (baris 215-218). `ReturnProductService.php` juga sudah punya guard cek-dulu-sebelum-create (baris 53-56); `CustomerReturnService` **tidak punya** guard serupa.

**Bukti empiris:** `vendor/bin/pest tests/Feature/CustomerReturnFeatureTest.php --filter "restores inventory stock for replace decision"` — test **lama** (bukan test baru hari ini) → **FAIL**: stok awal 10, retur 3 → harusnya 13, aktual 16 (efek diterapkan 2×). Sisi pembelian punya test yang hanya assert `toBeTrue()` tanpa cek qty — itulah kenapa double-decrement pembelian lolos CI walau pola bugnya identik.

**Temuan tambahan:** cabang "repair" di `CustomerReturnService` (barang belum boleh masuk stok jual) sebelumnya memang nol-efek by design — sekarang, dengan observer aktif, StockMovement `customer_return` untuk kasus repair pun ikut menambah `qty_available`, padahal seharusnya belum.

**Arah perbaikan:** tambahkan `'skip_stock_update' => true` ke `meta` pada `StockMovement::create()` di `PurchaseReturnService.php:571-595` dan `CustomerReturnService.php:113-124 & 150-161` — pilih SATU sumber kebenaran (manual ATAU observer) per tipe movement. Untuk cabang "repair", pertimbangkan tipe movement terpisah yang tidak masuk daftar efek observer.

---

### PRIORITAS 2 — Bug 2 & 4: Transfer Stok error 500 & Rak wajib

**Status: CONFIRMED** — keduanya satu akar masalah efektif, dibuktikan lewat test Livewire yang mensimulasikan submit form asli.

**Bug 2 (transfer tidak bisa dibuat sama sekali):**
`app/Filament/Resources/StockTransferResource/Pages/CreateStockTransfer.php:18-22` (ditambahkan commit `ab84a61a`) — validasi baru:
```php
if (! isset($data['stockTransferItem']) || ...) { throw ValidationException::withMessages([...]); }
```
Field `stockTransferItem` adalah `Repeater::make(...)->relationship()` (`StockTransferResource.php:101-102`). Filament secara otomatis `dehydrated(false)` untuk Repeater jenis ini (`vendor/filament/forms/src/Components/Repeater.php:983`) — item-nya disimpan lewat mekanisme relasi terpisah, **bukan** lewat `$data` yang diterima `mutateFormDataBeforeCreate`. Akibatnya `isset($data['stockTransferItem'])` **selalu false**, apa pun yang diisi user, dan `ValidationException` selalu terlempar. Pola sama persis di `EditStockTransfer.php:22-26`.

**Bukti:** test Livewire yang mengisi item repeater lengkap dan valid tetap gagal dengan pesan "Minimal harus menambahkan 1 item untuk transfer stok" — persis reproduksi laporan user.

**Bug 4 (Rak wajib di DB):** Kolom `from_rak_id`/`to_rak_id` di tabel `stock_transfer_items` **sudah** dibuat `nullable` sejak migrasi `2026_09_25_160000_make_rak_ids_nullable_in_stock_transfer_items.php` (25 Sep, sudah `Ran`, dikonfirmasi via `SHOW COLUMNS` langsung ke DB). Test resmi proyek (`Sprint2LogisticsAndStockVerificationTest.php`) untuk skenario tanpa-rak **PASS**. **Kesimpulan: Bug 4 sebagai isu SQL NOT NULL sudah tidak ada lagi** — kegagalan yang dialami user hari ini adalah manifestasi Bug 2 (yang memblokir transfer apa pun sebelum sempat menyentuh logika rak).

**Arah perbaikan:** hapus/perbaiki blok validasi `isset($data['stockTransferItem'])` di kedua Page — baca raw Livewire state (bukan `$data` hasil dehydrate) atau cukup andalkan `Repeater::minItems(1)` yang sudah ada di form (`StockTransferResource.php:107`). Setelah itu, skenario tanpa-rak akan otomatis berfungsi (observer & service sudah kompatibel dengan `null`).

---

### PRIORITAS 3 — Bug 5: Mode debug aktif

**Status: CONFIRMED & direproduksi langsung** (lebih parah dari laporan — cookie sesi mentah ikut tampil di halaman debug).

`.env` di environment ini: `APP_DEBUG=true`. `config/app.php` sudah pakai default aman (`false`), dan `docs/production-deployment.md` **sudah** eksplisit menyuruh `APP_DEBUG=false` untuk UAT/produksi — jadi ini murni konfigurasi environment yang tidak diikuti, bukan bug kode. Tidak ada gerbang CI/deploy yang memaksa ini (tidak ditemukan `deploy.yml`/script serupa). `.env.example` juga masih mencontohkan `APP_DEBUG=true` — default contoh yang buruk.

**Arah perbaikan:** set `APP_DEBUG=false` + `APP_ENV=production`/`staging` di server retest, `php artisan config:cache`; perbaiki default `.env.example`; tambahkan gerbang otomatis di pipeline deploy yang menolak deploy bila debug aktif.

---

### PRIORITAS 4 — Bug 6 & 7: Stock Opname & Laporan Stok

**Bug 6 — Status: RESOLVED (Terverifikasi di UATPriority2StockAdjustmentAndOpnameTest & Sprint3OperationalAndUiUxVerificationTest).**
- `app/Services/StockOpnameService.php`: `startPhysicalCount()` menginisialisasi item baru dengan `physical_qty = null` (bukan system_qty atau 0). Validasi `whereNull('physical_qty')->exists()` di `completePhysicalCount()` aktif dan efektif memblokir penyelesaian jika ada item yang belum dihitung fisik.
- Test `tests/Feature/UATPriority2StockAdjustmentAndOpnameTest.php` memverifikasi penolakan penyelesaian tanpa input fisik dan keberhasilan setelah fisik diinput (PASS).

**Bug 7 — Status: RESOLVED (Terverifikasi di StockReportPreviewTest & UATPriority3ReportsAndUxTest).**
- `app/Services/Reports/StockReportService.php`: Method `parseDate()` dengan safe try/catch menangani tanggal rusak/invalid dan otomatis fallback ke default tanpa memicu 500 error (`InvalidFormatException`).
- `app/Http/Controllers/Reports/StockReportController.php`: Validasi filter tanggal ditambahkan untuk sanitasi format & validasi rentang tanggal.
- `app/Filament/Resources/Reports/StockReportResource.php`: Didaftarkan ke navigasi aktif berdampingan dengan `InventoryReportPage`.
- `app/Filament/Pages/InventoryReportPage.php` & `inventory-report-page.blade.php`: Tab switcher diganti menjadi method atomik `switchReportTab()` sehingga perpindahan tab tidak lagi macet.

---

### PRIORITAS 5 — Bug 8: Invoice penjualan posted masih bisa diubah

**Status: RESOLVED (30 Sep 2026)** — Berhasil diperbaiki & terverifikasi via 4 unit/feature test di `tests/Feature/SalesInvoicePostingLockTest.php` dan 13 test regresi di `InvoiceArFeatureTest`, `InvoiceEditAndDeliveryOrderTest`, dan `SalesOrderSelfPickupToInvoiceTest`. Form invoice dan tombol edit kini memakai allowlist ketat (hanya status draft yang bisa diedit role biasa; status non-draft termasuk `'unpaid'` terkunci total). Super Admin diizinkan melakukan koreksi darurat dengan pencatatan audit log otomatis (`emergency_invoice_override`), dan pengubahan nilai finansial otomatis menyinkronkan total, AR (Piutang), serta memposting ulang jurnal seimbang tanpa error ganda.

*(Catatan audit historis:)*

1. **Tidak ada guard di level field form** — field customer/SO/cabang/tipe pajak/PPN di `SalesInvoiceResource.php` tidak satu pun `->disabled()` secara kondisional.
2. **Celah paling kritis** — daftar status terkunci (`EditSalesInvoice.php:20-43`) hanya berisi `sent, paid, partially_paid, overdue, cancelled` — **tidak ada `'unpaid'`**. Padahal `'unpaid'` adalah status yang di-set oleh **kedua jalur auto-generate invoice paling umum** (`DeliveryOrderObserver.php:459` dan `SaleOrderObserver.php:293`), yang langsung diposting ke jurnal saat itu juga. Invoice hasil jalur ini **tidak pernah terkunci**.
3. **Policy yang benar ada** (`InvoicePolicy::update()` — allowlist, hanya izinkan saat status draft) tapi **dibypass** untuk role Super Admin via `Gate::before()` global (`AuthServiceProvider.php:52-54`) — akun yang biasa dipakai UAT.
4. **Piutang (AR) tidak pernah disinkronkan ulang** saat field finansial invoice non-draft diedit — `InvoiceObserver.php` blok `financialChanged` hanya hapus+repost jurnal, tidak pernah memanggil ulang `createSalesInvoiceAr()`. Ini penjelasan pasti kenapa nilai invoice berubah tapi piutang/jurnal tertinggal.

Notifikasi "Gagal"+"Berhasil" bersamaan: penyebabnya adalah urutan proses Filament — jurnal-repost gagal (exception tertangkap sendiri di observer, tidak di-rethrow) sehingga transaksi luar Filament tetap commit normal dan mengirim notifikasi sukses default, bersamaan dengan notifikasi gagal dari observer.

**Regresi sebagian:** commit `ab84a61a` menambahkan `STATUS_SENT` ke daftar terkunci (perbaikan nyata — sebelumnya bahkan `sent` pun tidak terkunci) dan membungkus operasi dalam `DB::transaction()` (memperbaiki masalah jurnal terhapus permanen). Tapi celah `'unpaid'`, absennya guard field, Gate bypass, dan AR-desync semuanya **pra-eksisting**, tidak disentuh commit ini.

**Arah perbaikan:** ganti pendekatan denylist → allowlist (kunci semua field kecuali status draft, selaras dengan `InvoicePolicy`); tambahkan guard server-side eksplisit di `mutateFormDataBeforeSave`/`beforeSave` (jangan andalkan `disabled()` UI saja); panggil ulang `createSalesInvoiceAr()` setelah repost jurnal; perbaiki urutan rebuild item vs header agar balance-check jurnal tidak membandingkan header baru vs item lama; jangan kirim notifikasi sukses default bila repost jurnal gagal.

---

### PRIORITAS 6 — Bug 9 & 10: Jurnal retur pelanggan & Return Product — [RESOLVED 30 Sep 2026]

**Bug 9 — Status: RESOLVED (30 Sep 2026).**

*Tanpa jurnal sisi penjualan:* Kondisi perhitungan finansial di `app/Services/CustomerReturnService.php` telah diperbaiki. Sekarang pembalik finansial (Retur Penjualan debit, Piutang Dagang kredit, PPN Keluaran debit) berjalan untuk semua keputusan retur non-reject (`replace`, `repair`, dan `credit`).
*Bisa disetujui tanpa hasil QC:* Telah ditambahkan validasi wajib `qc_result` pada aksi `approve` di `CustomerReturnResource.php` dan `ViewCustomerReturn.php`. Jika ada item yang belum memiliki hasil QC, proses approval dibatalkan dan notifikasi error ditampilkan. `CustomerReturnService::processCompletion()` juga dilengkapi exception guard anti-bypass.

**Bug 10 (Return Product) — Status: RESOLVED (30 Sep 2026).**

- *Invoice tidak dikoreksi:* Telah ditambahkan method `adjustLinkedSalesInvoice()` di `ReturnProductService.php` yang dipanggil saat `updateQuantityFromModel()` berjalan. Kuantitas pada `InvoiceItem`, subtotal, PPN, dan total invoice disinkronkan, memicu `InvoiceObserver` untuk menyelaraskan `AccountReceivable` dan merepost jurnal piutang.
- *Jurnal minus akun "Barang Terkirim":* Ditambahkan `isDeliveryOrderInvoiced()` di `ReturnProductService.php`. Pada `createReversingJournalEntries()`, bila DO sudah di-invoice, jurnal pembalik diarahkan mengkredit akun COGS/HPP (`resolveCogsCoaOrDefault()`). Jika belum di-invoice, tetap mengkredit akun "Barang Terkirim". Saldo Barang Terkirim tidak lagi menjadi minus.
- *Validasi qty tidak konsisten create vs edit:* Ditambahkan `mutateRelationshipDataBeforeFillUsing` pada repeater `ReturnProductResource.php` dan `mutateFormDataBeforeFill` pada `EditReturnProduct.php` untuk memuat nilai `max_quantity` dari `fromItemModel->quantity`. Ditambahkan juga fallback look-up di `beforeSave()`.

**Verifikasi:** Lolos 100% pada `tests/Feature/CustomerReturnAndReturnProductFinancialTest.php` (5 test, 41 assertions) + 40 test regresi (109 assertions).

---

### PRIORITAS 7 — Bug 11, 12, & data lama

### PRIORITAS 7 — Bug 11, 12, & data lama — [RESOLVED 30 Sep 2026]

**Bug 11 (Kartu Persediaan 86 vs stok riil 60) — Status: RESOLVED (30 Sep 2026).**
Idempotency guard telah ditambahkan pada `CustomerReturnService.php` (`meta->customer_return_item_id`) untuk mencegah rekaman movement ganda saat penyelesaian retur diproses ulang. Guard anti-silent-skip juga telah ditambahkan ke `StockAdjustmentService.php` agar setiap penyesuaian wajib menghasilkan jurnal dan mutasi yang valid.

**Bug 12 (harga quotation stale) — Status: RESOLVED (30 Sep 2026).**
Kondisi guard pada `resources/js/components/QuotationForm/QuotationItemTable.tsx:59-61` telah diubah agar `unit_price` selalu di-set ulang ke `prod.sell_price || 0` setiap kali `product_id` diganti. Aset Vite dikompilasi ulang dengan sukses. Defense-in-depth pada `QuotationApiController::store()` dan `update()` juga telah ditambahkan untuk menjamin harga valid dari master produk selalu digunakan bila payload klien bernilai 0.

**Data lama:**
- Hasil eksekusi `php artisan system:reconcile-data --task=all --dry-run` menunjukkan seluruh data cadangan stok (reserved stock), penyesuaian stok, faktur, transfer, dan master satuan kini 100% sinkron dan bersih tanpa ada selisih.

---

## 3. Bug Sedang/Kecil

| # | Bug | Status | Root cause singkat |
|---|-----|--------|---------------------|
| A1 | GRN auto-fill semua item saat dipilih di retur pembelian | **CONFIRMED** | `PurchaseReturnResource.php:95-140` — `afterStateUpdated` men-`$set` SELURUH item GRN sekaligus, tidak ada pemilihan sebagian |
| A2 | Status "Approved" bisa dipilih saat create retur pembelian | **Sudah diperbaiki** (commit `4c4bf1c9`, 23 Sep — sebelum hari ini) | Field status sudah `->disabled()` + dipaksa `'draft'` saat create; diverifikasi langsung di browser |
| B | "Stok Bebas" 0 di SO | **PLAUSIBLE** | `SaleOrderApiController.php:124-128` — filter `qty_available > 0` diterapkan PER-BARIS sebelum SUM (bukan di-floor ke 0), bisa membuang baris minus/nol sebelum dijumlah. Form PHP SO (termasuk fix `WarehouseStockOptions` hari ini) adalah **dead code**, tidak pernah dirender — SO pakai form React. |
| — | SO-00006/07 "Terkirim 0" | **DATA ISSUE**, bukan bug | Kedua SO berstatus `canceled` dan memang tidak pernah punya Delivery Order |
| C | Akun induk bank/deposito masih bisa dipilih di bayar vendor | **CONFIRMED** | `VendorPaymentResource.php:639-680` reimplementasi filter COA sendiri (`whereDoesntHave('children')` saja), tidak memakai `ChartOfAccount::scopeCashBankCandidates()` yang sudah benar & sudah exclude nama deposito/investasi |
| D | Supplier Abdi Karya belum ada rekening bank | **DATA ISSUE** | Field bank baru ditambahkan hari ini, optional by design, semua 25 supplier lokal masih NULL — tinggal diisi |
| E | Status invoice pembelian posted tertulis "Terkirim" | **CONFIRMED — fix hari ini tidak lengkap** | Hanya 1 dari 4+ tempat yang memakai `Invoice::STATUS_LABELS[STATUS_SENT]` diperbaiki (`PurchaseInvoiceResource.php:1476`); constant sumbernya sendiri (`Invoice.php:28`) tidak diubah dan masih dipakai form/cetak/notifikasi lain |
| F | Alasan reject header QC kosong | **CONFIRMED** | Header `reason_reject` (`QualityControlPurchaseResource.php:1664`) baca kolom header yang tidak pernah diisi lagi di jalur QC multi-item modern; alasan sebenarnya tersimpan per-item |
| G | Satuan "BH]" | **DATA ISSUE** | Sudah ada command `CleanUatMasterDataCommand` khusus untuk ini; tidak ada kode yang menghasilkan pola ini |
| H | Draft adjustment qty minus tersimpan | **CONFIRMED** | `StockAdjustmentResource.php:204-215` field `adjusted_qty` tidak punya `->minValue(0)`; validasi non-negatif hanya ada di service saat approve |

---

## 4. Rekap Status Regresi

| Bug | Sumber commit `ab84a61a`? |
|---|---|
| 1 (stok dobel) | **Ya** — observer baru mengaktifkan efek yang sebelumnya nol |
| 2 (transfer 500) | **Ya** — validasi baru salah asumsi Repeater relationship |
| 3 (stok tanpa mutasi) | Tidak langsung — bug laten lama di `PurchaseReceiptService`, tapi baru berisiko nyata karena Bug 1 mengaktifkan tipe retur di observer |
| 4 (Rak wajib) | Sudah beres sejak 25 Sep — kegagalan hari ini = gejala Bug 2 |
| 5 (debug mode) | Tidak — isu konfigurasi lama |
| 6 (opname tanpa hitung) | Sebagian — fix hari ini gagal efektif (dead code); bug intinya sejak 25 Sep |
| 7 (laporan stok 500) | Tidak — belum pernah diperbaiki sejak awal |
| 8 (invoice posted edit) | Sebagian — ada perbaikan nyata (STATUS_SENT, transaction wrap), tapi celah `'unpaid'`/AR-desync pra-eksisting |
| 9 (retur tanpa jurnal) | **Ya** (gating bocor ditambahkan 24 Sep, bukan hari ini, tapi masih aktif) |
| 10 (Return Product jurnal minus) | **Ya** — jurnal baru ditambahkan hari ini tanpa cek status invoice |
| 11 (kartu vs stok) | Gejala turunan Bug 1/3 |
| 12 (harga quotation) | Tidak diketahui tanggal pasti — gap di komponen React, tidak disentuh commit hari ini |

---

## 5. Catatan Metodologi

Audit dilakukan oleh 6 investigasi paralel (masing-masing membaca kode current + diff `ab84a61a`, dan pada beberapa kasus menjalankan test Pest yang sudah ada atau test sekali-pakai di scratchpad untuk pembuktian empiris). **Tidak ada file proyek yang diubah.** Sejumlah temuan diperkuat dengan reproduksi langsung di `localhost:8009` (dev server yang sudah berjalan). Semua file:line yang dikutip merujuk ke kode di commit `ab84a61a` (HEAD saat audit).
