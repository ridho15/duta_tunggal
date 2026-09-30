# RENCANA PERBAIKAN: RETEST BUG 29 SEPTEMBER 2026

**Dasar:** [`docs/AUDIT-RETEST-29-SEP-2026.md`](./AUDIT-RETEST-29-SEP-2026.md) — audit teknis lengkap, semua root cause & file:line sudah diverifikasi di sana.
**Status:** Perencanaan. Belum ada kode yang diubah.
**Prinsip kerja** (mengikuti kebiasaan proyek ini): tiap tahap = 1 commit, dengan test regresi **terarah** (bukan suite penuh) sebagai gerbang sebelum lanjut ke tahap berikutnya. Suite penuh dijalankan di latar belakang di akhir, pada snapshot + DB uji terpisah.

Urutan tahap mengikuti prioritas yang sudah Anda tetapkan. Bug kecil (A–H) disisipkan ke tahap yang paling relevan agar tidak menyentuh file yang sama dua kali di commit berbeda.

---

## Tahap 0 — Persiapan

- Buat branch kerja: `fix/retest-29sep-2026` dari `main`.
- Catatan: working tree saat ini punya perubahan tak terkait milik proses lain (`QuotationResource.php`, `RoleSeeder.php` — fix NEG-3.3 permission approve/reject quotation; `FinanceSalesSeeder.php` — tambah `cabang_id`) dan beberapa skrip `.cjs` UAT di root/`scratch/`. **Tidak disentuh/di-stage**, sesuai praktik proyek ini untuk WIP pihak lain.
- Siapkan DB uji terpisah untuk regresi tiap tahap (bukan DB dev yang dipakai UAT manual).

---

## Tahap 1 — Stok: hentikan penggandaan efek (Bug 1, Bug 3, andil ke Bug 11) — ✅ SELESAI (30 Sep 2026)

**Prioritas #1 di daftar Anda — paling kritis, banyak bug lain bergantung padanya.**

**Status: selesai & terverifikasi.** Regresi terarah 45/45 lolos (`CustomerReturnFeatureTest`, `PurchaseReturnFeatureTest`, `Sprint2LogisticsAndStockVerificationTest`), termasuk 2 test baru yang mengunci perilaku ini ke depan. Regresi lebih luas (14 file terkait alur penerimaan/QC/akuntansi) juga dijalankan — ditemukan klaster test yang sudah flaky sejak sebelum perubahan ini (lolos sendirian, gagal acak saat digabung banyak file); dicatat di memori proyek, bukan disebabkan perubahan Tahap 1 (dibuktikan lewat perbandingan baseline dan re-run terisolasi).

1. Tambahkan `'skip_stock_update' => true` ke `meta` pada `StockMovement::create()`:
   - `app/Services/PurchaseReturnService.php` (blok ~baris 571-595)
   - `app/Services/CustomerReturnService.php` (blok ~baris 113-124 dan 150-161)
   — karena kedua service ini sudah menerapkan efek stok manual (`decrement`/`increment`), dan `StockMovementObserver` sekarang juga menerapkannya untuk tipe `purchase_return`/`customer_return`.
2. Untuk cabang "repair" di `CustomerReturnService` (barang belum boleh masuk stok jual): pastikan movement-nya memakai tipe yang **tidak** termasuk daftar efek `StockMovementObserver::stockEffectDelta()`, atau tetap disertai `skip_stock_update`.
3. Bug 3 — `app/Services/PurchaseReceiptService.php::syncInventoryStockFromMovements()` (baris ~479-480): tambahkan tipe retur (`purchase_return`, `return_out`, `customer_return`, `return_in`, dst.) ke daftar `$inTypes`/`$outTypes`, supaya recompute stok saat penerimaan barang tidak "menghapus" efek retur yang sudah tercatat. Satukan daftar tipe ini dengan yang dipakai `StockMovementObserver` (satu sumber, misal constant/config bersama) — dan terapkan perbaikan yang sama di `app/Console/Commands/ReconcilePurchaseReceiptStock.php` (baris ~148-149) dan `app/Console/Commands/AuditInventoryConsistency.php` (baris ~32-33) yang punya daftar basi serupa.
4. Perbaiki test yang sudah **FAIL** sebagai bukti bug: `tests/Feature/CustomerReturnFeatureTest.php` ("restores inventory stock for replace decision"). Tambahkan assertion qty (bukan cuma `toBeTrue()`) di `tests/Feature/PurchaseReturnFeatureTest.php` ("stock adjustment on approval") supaya double-decrement pembelian juga tertangkap ke depannya.

**Regresi terarah:** `vendor/bin/pest tests/Feature/CustomerReturnFeatureTest.php tests/Feature/PurchaseReturnFeatureTest.php tests/Feature/Sprint2LogisticsAndStockVerificationTest.php`

**Kriteria selesai:** retur pembelian 2 pcs → stok berkurang tepat 2 (bukan 4); retur pelanggan 1 pcs → stok bertambah tepat 1; setiap perubahan stok punya tepat 1 baris mutasi.

**Setelah tahap ini**, retest manual Bug 11 (Kartu Persediaan) — kemungkinan besar otomatis membaik karena akar dugaannya (movement dobel) sudah hilang. Kalau masih ada selisih, baru masuk pekerjaan tambahan di Tahap 7.

---

## Tahap 2 — Transfer Stok (Bug 2, Bug 4) — ✅ SELESAI (30 Sep 2026)

**Status: selesai & terverifikasi.** Root cause persis sesuai dugaan: `Repeater::make('stockTransferItem')->relationship()` tidak pernah mengisi `$data['stockTransferItem']` yang diterima `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` (Filament men-dehydrate=false field itu, item disimpan lewat jalur relasi terpisah setelah method itu dipanggil) — sehingga pengecekan `isset($data['stockTransferItem'])` SELALU throw, apa pun isian user. Dihapus seluruhnya (bukan diperbaiki jadi kondisional), karena validasi "minimal 1 item" sudah ditangani `Repeater::minItems(1)` yang sudah ada di form, dan normalisasi rak kosong→null sudah ditangani `StockTransferItemObserver::saving()` di level model — kedua mekanisme itu bekerja terlepas dari jalur mana item disimpan. Dibuktikan lewat test Livewire yang benar-benar submit form (bukan panggil method langsung): transfer baru berhasil dibuat dengan DAN tanpa rak asal/tujuan. 2 test lama yang menguji perilaku (rusak) yang dihapus diganti dengan test form Livewire sungguhan. 49/49 test regresi terarah lolos.

Temuan sampingan (dicatat, di luar cakupan Tahap 2, sudah di-flag sebagai tugas terpisah): `database/factories/StockTransferFactory.php` memakai `status` acak yang kadang bernilai 'Approved', memicu validasi stok minus baru (dari commit pagi ini) pada test yang tidak meng-seed `InventoryStock` — menyebabkan `StockTransferTest`, `WarehouseAuditTest`, `ResourceSortingTest` flaky ~20% kemunculan. Tidak terkait Bug 2/4.

1. `app/Filament/Resources/StockTransferResource/Pages/CreateStockTransfer.php` (baris 18-22) dan `EditStockTransfer.php` (baris 22-26): hapus pengecekan `isset($data['stockTransferItem'])` yang salah asumsi (field ini memang tidak pernah ada di `$data` untuk Repeater `->relationship()`). Andalkan `Repeater::minItems(1)` yang sudah ada di `StockTransferResource.php:107`, atau baca raw Livewire state bila validasi custom tetap diperlukan.
2. Rak asal/tujuan tidak perlu perubahan tambahan — kolom DB sudah nullable, observer & service sudah menangani `null` dengan benar (dikonfirmasi via test yang sudah ada).
3. Tambahkan test Livewire baru (promosikan dari test sekali-pakai yang sudah dibuat auditor di scratchpad) ke `tests/Feature/` untuk mengunci skenario "create transfer via form lengkap, dengan dan tanpa rak" — supaya regresi Repeater seperti ini tertangkap otomatis ke depan.

**Regresi terarah:** `vendor/bin/pest tests/Feature/Sprint2LogisticsAndStockVerificationTest.php <test-baru>`

**Kriteria selesai:** transfer baru berhasil dibuat lewat UI, baik dengan rak maupun tanpa rak asal/tujuan.

---

## Tahap 3 — Matikan mode debug (Bug 5) — ✅ SELESAI (30 Sep 2026)

Bukan perubahan kode — murni konfigurasi:
1. ~~Set `APP_DEBUG=false`, `APP_ENV=production` (atau `staging`) di `.env` server yang dipakai untuk UAT/produksi~~ → **Keputusan:** `APP_ENV` tetap `local` (mesin ini memang dev/UAT lokal Anda, bukan server produksi/staging sungguhan; mengganti `APP_ENV` berisiko mengubah perilaku lain yang tidak terkait bug ini). Hanya `APP_DEBUG` diubah ke `false` di `.env` lokal — dikonfirmasi Anda, dengan konsekuensi: halaman error jadi generik untuk sisa sesi ini, error tetap lengkap tercatat di `storage/logs/laravel-<tanggal>.log`.
2. Perbaiki default di `.env.example` → `APP_DEBUG=false` (selesai, sudah di-commit).
3. `php artisan config:clear` dijalankan agar perubahan langsung berlaku (dikonfirmasi via tinker: `config('app.debug')` = false).
4. (Opsional, boleh ditunda) tambahkan gerbang di pipeline deploy yang menolak deploy bila `APP_DEBUG=true` terdeteksi di environment target — belum ada `deploy.yml`/script serupa di repo ini saat ini.

**Verifikasi:** direproduksi ulang skenario 500 yang sama (`/reports/stock-report/preview?start_date=not-a-date&end_date=also-bad`) di browser — sebelumnya menampilkan stack trace lengkap + query SQL + cookie sesi mentah, sekarang menampilkan halaman generik "500 Server Error" saja. Log tetap lengkap di server.

**Penting untuk server UAT/produksi Anda yang sesungguhnya (di luar mesin ini):** pastikan `APP_DEBUG=false` juga disetel di sana secara terpisah — perubahan ini hanya berlaku untuk `.env` di mesin lokal yang dipakai sesi ini.

---

## Tahap 4 — Stock Opname & Laporan Stok (Bug 6, Bug 7) — [SELESAI]

1. **Opname**: `app/Services/StockOpnameService.php::startPhysicalCount()` — `physical_qty` diisi `null` saat item dibuat. Validasi `whereNull('physical_qty')->exists()` di `completePhysicalCount()` aktif dan memblokir penyelesaian jika belum dihitung. Test `UATPriority2StockAdjustmentAndOpnameTest.php` disesuaikan dan lulus.
2. **Laporan Stok**: Validasi dan sanitasi filter tanggal ditambahkan di `StockReportController.php`. Parsing aman `parseDate()` dengan try/catch diterapkan di `StockReportService.php` (anti-500). Tab switcher atomik `switchReportTab()` diterapkan di `InventoryReportPage.php` dan `inventory-report-page.blade.php`.
3. **Keputusan (dikonfirmasi): pertahankan keduanya.** `StockReportResource` (label nav "Laporan Stok") didaftarkan ke navigasi aktif berdampingan dengan `InventoryReportPage` (label nav "Laporan Inventori").

**Hasil Regresi Terarah (28 passed, 102 assertions):**
- `tests/Feature/UATPriority2StockAdjustmentAndOpnameTest.php`: 9 passed
- `tests/Feature/StockReportPreviewTest.php`: 4 passed
- `tests/Feature/Sprint3OperationalAndUiUxVerificationTest.php`: 9 passed
- `tests/Feature/UATPriority3ReportsAndUxTest.php`: 6 passed

**Kriteria selesai terpenuhi:** Opname memblokir approval jika item belum dihitung fisik; Laporan Stok tidak error 500 pada input tanggal apa pun; Tab Laporan Inventori berganti secara mulus.

---

## Tahap 5 — Kunci invoice penjualan yang sudah diposting (Bug 8) — ✅ SELESAI (30 Sep 2026)

**Keputusan (dikonfirmasi Anda): Super Admin tetap boleh membuka & mengedit invoice yang sudah diposting untuk koreksi darurat.** Jadi kuncinya bukan "tanpa kecuali", melainkan: **default terkunci untuk semua role, kecuali Super Admin** — dan karena ini jalur override finansial, override itu perlu tercatat (audit trail), bukan diam-diam.

**Status: selesai & terverifikasi.** Semua 6 poin di bawah diimplementasikan persis sesuai rencana. Audit trail dibuat lewat pemanggilan `activity('emergency_invoice_override')->performedOn($record)->causedBy(...)->log(...)` langsung di `EditSalesInvoice::handleRecordUpdate()` (bukan trait `LogsActivity` di model, karena ini hanya perlu dicatat utuk jalur override darurat, bukan setiap perubahan invoice). Test baru `tests/Feature/SalesInvoicePostingLockTest.php` (4 test) membuktikan end-to-end: role biasa ditolak pada status non-draft, Super Admin lolos, dan skenario penuh (Super Admin ubah PPN invoice ter-posting → total invoice, AR, dan jurnal semuanya sinkron ke nilai baru + tercatat di activity log). Regresi lebih luas (`InvoiceArFeatureTest` yang dikenal flaky, `SalesInvoiceResourceTest`, `InvoiceServiceFeatureTest`, dll — 42 test) semuanya lolos.

1. `app/Filament/Resources/SalesInvoiceResource/Pages/EditSalesInvoice.php`: ganti pendekatan denylist → **allowlist eksplisit dengan bypass Super Admin** — field kunci (customer, SO, cabang, tipe pajak, PPN) terkunci untuk semua status selain `draft`, KECUALI `Auth::user()->hasRole('Super Admin')`. Ini ditulis eksplisit di titik pengecekan (bukan mengandalkan `Gate::before()` yang implisit), supaya niatnya jelas dibaca ulang nanti. Ini otomatis menutup celah `'unpaid'` yang selama ini lolos untuk role selain Super Admin.
2. Tambahkan guard **server-side** di `mutateFormDataBeforeSave()`/`beforeSave()` dengan logika bypass yang sama (Super Admin lolos, role lain ditolak bila status ≠ draft) — jangan andalkan `->disabled()` UI saja (bisa dilewati lewat manipulasi request langsung).
3. Tambahkan `->disabled()` kondisional (dengan bypass yang sama) di `SalesInvoiceResource.php` pada field terkait, sebagai lapisan UX.
4. **Audit trail untuk override darurat**: begitu Super Admin menyimpan perubahan pada invoice non-draft, catat lewat `spatie/laravel-activitylog` (paket ini sudah ada di `composer.json` dan sudah dipakai dengan pola `LogsActivity` trait + `getActivitylogOptions()` di `app/Models/VoucherRequest.php` — ikuti pola yang sama pada `Invoice`), minimal berisi: siapa, kapan, field apa yang diubah, nilai lama/baru. Ini murni pencatatan, tidak menghalangi override-nya.
5. Di `app/Observers/InvoiceObserver.php` blok `financialChanged` (baris ~181-214): panggil ulang `createSalesInvoiceAr()` setelah repost jurnal berhasil, supaya piutang ikut sinkron — berlaku untuk semua kasus (Super Admin maupun bukan), karena ini soal konsistensi data, bukan soal izin.
6. Perbaiki urutan: rebuild `invoiceItem`/tax breakdown (lewat `SalesInvoiceLineBuilder`) **sebelum** header `total`/`ppn_rate` disimpan, supaya balance-check jurnal tidak membandingkan header baru vs item lama (ini juga penyebab notifikasi "Gagal"+"Berhasil" bersamaan — begitu balance-check tidak pernah gagal palsu, notifikasi ganda ikut hilang).

**Regresi terarah:** test invoice terkait (`InvoiceArFeatureTest` — catatan: ini termasuk test yang dikenal flaky, jalankan 2× bila gagal sekali) + test baru untuk skenario status `unpaid` (role biasa ditolak, Super Admin lolos + tercatat di activity log) & sinkronisasi AR.

**Kriteria selesai:** invoice status apa pun selain draft tidak bisa diubah field kuncinya oleh role biasa (dicoba lewat UI maupun manipulasi request langsung); Super Admin tetap bisa mengedit invoice terposting dan perubahannya tercatat di activity log; mengubah PPN pada invoice draft (role mana pun) maupun override Super Admin pada invoice non-draft sama-sama menyinkronkan total, piutang, dan jurnal dengan benar.

---

## Tahap 6 — Jurnal retur pelanggan & koreksi Return Product (Bug 9, Bug 10) — [SELESAI 30 Sep 2026]

1. **Retur pelanggan** (`app/Services/CustomerReturnService.php`):
   - Perbaiki kondisi finansial sehingga jurnal retur penjualan + PPN Keluaran + penyesuaian piutang (AR) berjalan untuk semua keputusan non-reject (`replace`, `repair`, dan `credit` bila credit note belum aktif):
     `$shouldReverseFinancial = ($item->decision !== CustomerReturnItem::DECISION_REJECT) && (! CreditNoteService::enabled() || $item->decision !== CustomerReturnItem::DECISION_CREDIT);`
   - Ditambahkan validasi wajib `qc_result` terisi sebelum aksi "Setujui" pada tabel & view (`CustomerReturnResource.php` dan `ViewCustomerReturn.php`) serta exception guard pada `CustomerReturnService::processCompletion()`.
2. **Return Product** (`app/Services/ReturnProductService.php` & `ReturnProductResource`):
   - Ditambahkan deteksi status invoice sumber: `isDeliveryOrderInvoiced(DeliveryOrder $deliveryOrder): bool`.
   - Di `createReversingJournalEntries()`: Kredit jurnal dinamis diarahkan ke akun HPP/COGS (`$product->resolveCogsCoaOrDefault() ?? anyOf('cogs')`) jika DO sumber sudah di-invoice, dan ke akun "Barang Terkirim" jika belum di-invoice (mencegah saldo minus).
   - Di `updateQuantityFromModel()`: Ditambahkan pemanggilan `adjustLinkedSalesInvoice()` untuk mengoreksi baris invoice terkait (kuantitas, subtotal, PPN, dan total) serta memicu `InvoiceObserver` untuk menyinkronkan saldo `AccountReceivable` dan repost jurnal piutang secara simetris.
   - Di `ReturnProductResource.php` & `EditReturnProduct.php`: Ditambahkan `mutateRelationshipDataBeforeFillUsing` pada Repeater dan `mutateFormDataBeforeFill` pada halaman Edit untuk memuat `max_quantity` dari `fromItemModel->quantity`. Ditambahkan juga fallback look-up pada `beforeSave()`.
3. **Uji Coba**:
   - Dibuat suite pengujian lengkap `tests/Feature/CustomerReturnAndReturnProductFinancialTest.php` (5 skenario: replace decision journal & AR sync, QC validation guard, uninvoiced DO goods delivery reversal, invoiced DO COGS reversal & invoice adjustment, and edit max_quantity guard).
   - Seluruh test lolos: 5/5 passing (41 assertions).
   - Regresi: 40/40 passing di `tests/Feature/CustomerReturnFeatureTest.php`, `tests/Feature/ERP/CustomerReturnTest.php`, dan `tests/Feature/UATPriority1StockTransferAndReturnTest.php`.

**Status:** Selesai 100%. Saldo "Barang Terkirim" aman dari angka minus, piutang dan invoice terkoreksi otomatis, retur pelanggan terposting dengan benar.

---

## Tahap 7 — Kartu Persediaan, harga Quotation, data lama (Bug 11, Bug 12)

1. Verifikasi ulang Bug 11 setelah Tahap 1. Bila masih ada selisih, tambahkan guard anti-duplikasi pada `StockMovement::create()` di `CustomerReturnService` (constraint/cek-dulu, meniru pola yang sudah ada di `ReturnProductService.php:53-56`), dan pertimbangkan task rekonsiliasi baru di `ReconcileHistoricalDataCommand` untuk membersihkan movement duplikat lama.
2. **Quotation**: `resources/js/components/QuotationForm/QuotationItemTable.tsx` (baris ~59-61) — balik syarat guard supaya `unit_price` selalu di-set ulang ke `product.sell_price` saat `product_id` berubah (bukan hanya saat field masih 0). Tambahkan recompute harga di server (`QuotationApiController::store()`/`update()`) sebagai defense-in-depth agar payload harga basi dari klien tidak tersimpan mentah.
3. **Data lama** (jalankan setelah root cause terkait sudah diperbaiki, satu per satu, dengan konfirmasi Anda sebelum eksekusi karena menyentuh data langsung):
   - Stok cadangan COPPER ELBO (41 → ~14): jalankan `system:reconcile-data --task=reserved-stock` (setelah cek dry-run).
   - Adjustment 22/09 & 24/09 tanpa jurnal: perbaiki dulu data produk (cost_price/COA) yang memicu early-return senyap di `StockAdjustmentService::syncJournalEntries()`, baru jalankan task rekonsiliasi adjustment.
   - Item lain (stok -22 HUMMER, jurnal INV-20260928-0001 terhapus, SO-00006/07) — dikoreksi manual per kasus, dikonfirmasi ke Anda dulu sebelum eksekusi karena berpotensi mengubah data final.

**Kriteria selesai:** saldo kartu persediaan = stok riil untuk sampel produk yang diuji; ganti produk di quotation langsung menampilkan & menyimpan harga produk baru.

---

## Tahap 8 — Bug kecil (sisa dari kategori Sedang/Kecil)

Dikerjakan sebagai satu commit ringan setelah tahap-tahap besar selesai (tidak memblokir apa pun di atas):

- **A1** — `PurchaseReturnResource.php:95-140`: ubah auto-fill GRN agar user bisa pilih sebagian item, bukan semua sekaligus.
- **B** — `SaleOrderApiController.php:124-128`: pindahkan floor-to-zero dari per-baris ke level SUM total, supaya baris stok negatif/nol tidak membuang seluruh perhitungan "Stok Bebas".
- **C** — `VendorPaymentResource.php:639-680`: ganti query COA custom dengan `ChartOfAccount::scopeCashBankCandidates()` yang sudah benar (sudah exclude akun induk & deposito/investasi).
- **E** — `app/Models/Invoice.php:28` (`STATUS_LABELS`): jadikan satu-satunya sumber label status, hapus closure duplikat di `PurchaseInvoiceResource.php:1473-1483`.
- **F** — `QualityControlService.php::handleMultiItemPurchaseOrderQcCompletion()`: agregasikan `reason_reject` per item ke kolom header, atau ubah tampilan header untuk membaca dari item.
- **H** — `StockAdjustmentResource.php:204-215`: tambahkan `->minValue(0)` pada field `adjusted_qty`.
- **D, G, A2, SO-00006/07** — tidak perlu perubahan kode (data issue / sudah diperbaiki commit sebelumnya); cukup isi data (rekening bank supplier) atau jalankan command pembersihan yang sudah ada (`app:clean-uat-master-data`) bila masih ada data kotor di environment yang bersangkutan.

**Kriteria selesai:** masing-masing sesuai deskripsi bug asal, diverifikasi manual satu per satu (bug ini kecil, tidak perlu test baru kecuali sudah ada test yang relevan).

---

## Tahap 9 — Regresi penuh & penutupan

1. Setelah Tahap 1-8 selesai dan masing-masing lolos regresi terarah, jalankan suite penuh di latar belakang pada **snapshot terpisah** (`git archive HEAD` + salin `vendor`/`.env`/`public/build`) dengan DB uji sendiri, dibandingkan ke baseline (`scripts/run-tests-chunked.php --compare=tests/baseline-failures.txt`).
2. Update `docs/AUDIT-RETEST-29-SEP-2026.md` dengan status akhir per bug (selesai/sebagian/ditunda) untuk jadi acuan retest manual berikutnya.
3. Siapkan ringkasan untuk retest manual Anda, mengikuti urutan yang sama seperti daftar bug asli supaya mudah dicocokkan satu-satu.

---

## Ringkasan urutan commit

| Tahap | Bug yang diselesaikan | Ketergantungan |
|---|---|---|
| 1 | 1, 3, (andil 11) | — |
| 2 | 2, 4 | — |
| 3 | 5 | — (config, bisa paralel kapan saja) |
| 4 | 6, 7 | — |
| 5 | 8 | — |
| 6 | 9, 10 | Sebaiknya setelah Tahap 5 (pola repost invoice yang diperbaiki di sana dipakai ulang untuk koreksi invoice di Return Product) |
| 7 | 11 (verifikasi), 12, data lama | Setelah Tahap 1 (untuk Bug 11) |
| 8 | A1, B, C, E, F, H | — (independen, bisa dikerjakan kapan saja) |
| 9 | Regresi & penutupan | Setelah semua tahap |

**Keputusan sudah dikonfirmasi:** Tahap 4 mempertahankan kedua implementasi Laporan Stok/Laporan Inventori; Tahap 5 mengunci invoice posted untuk semua role kecuali Super Admin (dengan audit trail untuk overridenya). Siap dieksekusi mulai Tahap 1.
