# Analisis Akar Masalah — Retest UAT RL Hassee (10 Oktober 2026)

**Sumber:** Laporan retest manual dari tester (marker `TEST-RL-HASSEE-20261010`), diuji di `dutatunggal.playgo.id` (staging). Dokumen ini **bukan hasil pengujian live saya di staging** (saya tidak login ke sana — lihat catatan akses di bawah), melainkan **investigasi akar masalah langsung ke kode lokal**, dilengkapi pengujian live di environment lokal (`localhost:8009`) untuk sebagian temuan (ditandai per bagian).
**Hubungan dengan audit 3-fase:** Beberapa temuan di sini beririsan langsung dengan pola sistemik yang sudah diidentifikasi di `AUDIT-SINTESIS-3FASE-08OKT2026.md` — dicatat di tiap bagian.
**Catatan akses:** Kredensial staging (`ralamzah@gmail.com`) tidak saya masukkan ke `dutatunggal.playgo.id` karena itu bukan host pengembangan lokal (di luar cakupan kebijakan "uji aplikasi sendiri" yang saya pegang). Semua temuan di bawah berasal dari pembacaan kode di repo lokal, yang menurut `git log` migrasinya identik dengan yang disebut di laporan tester.

---

## Bagian A — Migration Belum Dijalankan di Staging (PRIORITAS, memblokir SO→DO→SJ→Invoice)

**Status: bukan kode yang belum selesai — sudah ada dan sudah jalan di lokal.**

- `database/migrations/2026_10_08_120000_add_cashier_approval_to_sale_orders_table.php` — menambah `sale_orders.cashier_approved_at`.
- `database/migrations/2026_10_09_120000_add_surat_jalan_ids_to_invoices_table.php` — menambah `invoices.surat_jalan_ids`.

Dikonfirmasi lewat `php artisan migrate:status` di lokal: keduanya **sudah `Ran`**, dan kolomnya **ada** di skema (`Schema::getColumnListing` dicek langsung). Berarti masalahnya murni **staging belum menarik kode terbaru dan/atau belum menjalankan `php artisan migrate`** — bukan bug.

**Tindakan:** di server staging, pastikan `git pull` ke commit terbaru lalu jalankan `php artisan migrate`. Setelah itu seluruh alur SO→DO→SJ→Invoice di laporan (Bagian A, D.4, E) seharusnya bisa diuji lagi.

---

## Bagian C — Masih Bermasalah (Investigasi Akar Masalah)

### C.2 — Retur dobel dari reject QC: AKAR MASALAH DITEMUKAN

**Klaim tester:** 1 pcs reject QC menghasilkan 2 dokumen retur (`NR-20261010-8339` dan `NR-20261010-2201`).

**Root cause (dikonfirmasi langsung dari kode):** Ada **dua tempat berbeda** di UI Filament yang memanggil `PurchaseReturnService::createFromQualityControl()` untuk aksi "Selesaikan QC" yang SAMA — logikanya digandakan hampir identik:
- `app/Filament/Resources/QualityControlPurchaseResource.php:1535` (aksi di halaman daftar/tabel)
- `app/Filament/Resources/QualityControlPurchaseResource/Pages/ViewQualityControlPurchase.php:95` (aksi yang sama persis, di halaman detail)

Masalahnya: `createFromQualityControl()` (`app/Services/PurchaseReturnService.php:45-111`) **hanya memvalidasi `$qc->rejected_quantity > 0`** — tidak pernah mengecek apakah retur untuk QC ini **sudah pernah dibuat** sebelumnya (kolom `quality_controls.purchase_return_processed` memang ada dan DIISI di akhir method, tapi tidak pernah DIBACA sebagai syarat sebelum membuat retur baru).

Sebagai pembanding, `app/Services/PurchaseReturnAutomationService.php:183` justru SUDAH benar mengecek `if ($qc->purchase_return_processed) { ... }` sebelum membuat retur — menunjukkan tim developer tahu pola yang benar, hanya belum diterapkan konsisten di 2 titik Filament action di atas. **Ini persis Pola 6 di dokumen sintesis 3-fase** (anti-duplikat/`lockForUpdate` tidak merata meski sudah dikuasai tim di tempat lain).

**Rekomendasi:** tambahkan guard `if ($qc->purchase_return_processed) throw ...` di awal `createFromQualityControl()` — satu perbaikan ini otomatis menutup celah di kedua titik Filament sekaligus (dan tiga titik lain yang memanggil method yang sama, kalau ada).

### C.1 — Saldo hutang salah setelah reject QC: SEBAGIAN diverifikasi, SEBAGIAN perlu akses staging

**Klaim tester:** nilai 1 pcs reject QC (Rp6.290,37) ikut mengurangi saldo hutang padahal barang itu tidak pernah ditagih/dijurnal.

**Yang SUDAH dikonfirmasi benar di kode lokal (bukan sumber bug ini):**
1. `PurchaseReturnService::calculateApprovedReturnsTotalForInvoice()` (baris 897) sudah punya `->whereNull('quality_control_id')` — **ini persis fix yang saya lihat masih *uncommitted* saat audit Fase 1 (7 Okt), sekarang sudah ter-commit.** Method ini sudah benar mengecualikan retur-dari-QC dari perhitungan kredit retur per invoice.
2. `PurchaseReturnService::approve()` (baris 834-864) sudah benar: `adjustAccountPayable()`, `createJournalEntry()`, dan `adjustStock()` **secara eksplisit di-skip untuk retur yang berasal dari QC** (`if (! $purchaseReturn->isQcReturn() && ...)`, baris 853) — jadi approve retur-dari-QC tidak menyentuh saldo hutang lewat jalur ini.
3. `PurchaseInvoiceAccountingService` membangun invoice dari `qty_accepted` (bukan qty diterima/termasuk reject) — jadi nilai barang reject semestinya memang tidak pernah masuk total invoice/hutang dari awal.

**Kesimpulan:** dua mekanisme paling mungkin jadi biang kerok sudah diperbaiki di kode saat ini. Kalau laporan tester berasal dari **kode staging yang lebih lama** (konsisten dengan temuan Bagian A bahwa staging belum menarik commit terbaru), kemungkinan besar **C.1 juga sudah ikut terselesaikan** begitu staging di-update+migrate. Tapi saya **tidak bisa memastikan 100%** tanpa data staging langsung (angka spesifik Rp48.871,11 "dibayar" tidak berhasil saya telusuri asal-usulnya dari kode yang saya baca). **Rekomendasi:** setelah staging di-update (Bagian A), retest transaksi C.1 dari awal di data baru — kalau selisih Rp6.290,37 masih muncul, baru perlu investigasi lanjutan dengan akses database staging langsung.

### C.5 — Error 500 "Lihat PO hasil Create PO dari SO": AKAR MASALAH DITEMUKAN

**Klaim tester:** `Call to undefined relationship [cabang] on model SaleOrderItem`.

**Root cause (dikonfirmasi langsung):** `app/Filament/Resources/PurchaseOrderResource/RelationManagers/PurchaseOrderItemRelationManager.php` melakukan eager-load **tanpa penyaringan tipe**:
- baris 277: `'referItemModel.cabang:id,kode,nama'`
- baris 654: `$record->loadMissing('referItemModel.cabang', 'product.cabang', 'purchaseOrder.cabang')`

`referItemModel` adalah relasi polymorphic (`morphTo`) yang bisa mengarah ke `OrderRequestItem` (punya method `cabang()`) ATAU `SaleOrderItem` (fitur "Create PO dari SO" — **tidak punya** method `cabang()`, dan tabel `sale_order_items` memang tidak punya kolom `cabang_id` sama sekali). Begitu PO dibuat dari Sale Order lalu dibuka, Eloquent mencoba eager-load `cabang` pada `SaleOrderItem` dan crash.

Menariknya, di file yang SAMA ada pola yang BENAR (baris ~2762 di `PurchaseOrderResource.php`): `whereHasMorph('referItemModel', [OrderRequestItem::class], ...)` — secara eksplisit membatasi hanya ke tipe yang punya relasi itu. Celahnya hanya ada di 2 baris eager-load di atas yang tidak memakai pembatasan tipe yang sama.

**Rekomendasi:** ganti kedua eager-load itu agar type-safe (mis. `->with(['referItemModel' => fn ($q) => $q->when($q->getModel() instanceof OrderRequestItem, fn ($q) => $q->with('cabang'))])`), atau tambahkan accessor `cabang()` di `SaleOrderItem` yang mengarah lewat `saleOrder->cabang` (SaleOrder sendiri sudah benar punya `cabang_id` + `CabangScope`, dikonfirmasi di audit Fase 2).

### C.3 — Batas harga belum berlaku di SO Mandiri: AKAR MASALAH DITEMUKAN

**Klaim tester:** validasi harga minimum sudah benar di Quotation (`Rp1.000 ditolak, minimum Rp5.667`) tapi SO Mandiri (`SO-00006`) masih menyimpan harga Rp1.000.

**Root cause (dikonfirmasi langsung dari kode):** validasi batas harga memakai class terpusat `App\Support\ProductPriceLimitation` (`validatePrice()` + `getMinimumAllowedPrice()`). Dicek lewat `grep -rln "ProductPriceLimitation"`: class ini **hanya dipakai di 2 tempat** — `app/Filament/Resources/QuotationResource.php` dan `app/Http/Controllers/Api/QuotationApiController.php`. **Tidak ada satu pemanggilan pun** di `app/Http/Controllers/Api/SaleOrderApiController.php` (backend form React "SO Mandiri") maupun `app/Filament/Resources/SaleOrderResource.php`. Validasinya memang betul-betul belum pernah ditulis untuk Sale Order — bukan bug regresi, tapi fitur yang belum direplikasi dari Quotation ke SO.

**Rekomendasi:** panggil `ProductPriceLimitation::validatePrice()` yang sama di `SaleOrderApiController` (dan idealnya di form Filament SO juga) — satu class sudah reusable, tinggal dipasang di titik yang belum.

### C.4 — Return Product belum terkunci setelah SJ: AKAR MASALAH DITEMUKAN

**Klaim tester:** semua DO masih bisa dipilih di form Return Product, termasuk yang sudah punya SJ dan sudah ditagih.

**Root cause (dikonfirmasi langsung dari kode):** `app/Filament/Resources/ReturnProductResource.php` baris 102-104 — dropdown "From Order" untuk sumber `DeliveryOrder` memuat **seluruh** data tanpa filter apa pun:
```php
if ($get('from_model_type') == 'App\Models\DeliveryOrder') {
    return DeliveryOrder::select(['id', 'do_number'])->get()->pluck('do_number', 'id');
}
```
Tidak ada `whereDoesntHave('suratJalan')`, tidak ada pengecekan status invoice, tidak ada filter apa pun — persis seperti yang dilaporkan tester, dan juga sama persis dengan feedback "Ko Handy" di Bagian D.3 laporan asli ("Return Product belum dibatasi hanya untuk DO yang belum punya SJ"). Pola ini **berbeda** dari Pola 5 (flag `doc_lock` default mati) — di sini validasinya memang belum pernah ditulis sama sekali, bukan soal flag yang belum dinyalakan.

**Rekomendasi:** tambahkan filter pada query di baris 104, misalnya mengecualikan DO yang sudah terhubung ke Surat Jalan dan/atau Invoice.

---

## Bagian E — Error 500: Tidak Reproduksi di Lokal (Dugaan Kuat: Staging Belum Update)

Dua error yang dilaporkan tester **tidak berhasil saya reproduksi** di `localhost:8009` meski dicoba dengan skenario realistis:

- **Transfer Stok**: diuji live penuh — buat Transfer Stock baru → Request Transfer → Approve, dengan 2 skenario (qty normal yang tersedia, dan qty jauh melebihi stok/999 unit). Keduanya berjalan tanpa error: skenario normal berhasil memindahkan stok dengan benar (`InventoryStock` terverifikasi berubah di kedua gudang); skenario over-qty tidak menyebabkan crash, hanya transfer tidak ter-approve (tetap status `Request`) — tidak ideal (idealnya ada pesan error eksplisit "stok tidak cukup"), tapi sama sekali bukan error 500.
- **Laporan Stok Interaktif**: halaman `/admin/inventory-report-page` (tujuan tombol "Laporan Interaktif" di `/admin/reports/stock-report`) dimuat dan dicoba langsung — tampil sempurna dengan data lengkap (839 item stok, filter gudang/produk berfungsi), tidak ada error sama sekali.

**Kesimpulan:** karena kedua fitur ini terbukti bekerja bersih di kode terbaru (lokal), dan sudah dikonfirmasi di Bagian A bahwa staging kemungkinan besar belum `git pull` + `migrate` ke commit terbaru, dugaan paling kuat adalah **staging menjalankan versi kode yang lebih lama** yang memang masih bermasalah di dua halaman ini. Rekomendasi: retest ulang kedua fitur ini setelah staging di-deploy ke commit terbaru — kalau errornya masih muncul di kode yang benar-benar sama, baru perlu investigasi lanjutan dengan akses log staging langsung.

### Item Stock Opname tidak tampil di Lihat/Ubah: TIDAK REPRODUKSI (dugaan kuat: staging versi lama)

Diuji live penuh di lokal: buat Stock Opname baru → klik "Muat / Perbarui Stok Produk" (memuat 7 item stok otomatis) → cek halaman **Lihat**: item tampil lengkap (Product, SKU, Rak, Qty Sistem, harga, dst). Cek halaman **Ubah**: item juga tampil lengkap dengan tombol Ubah/Hapus per baris, plus checkbox bulk action. Tidak ada satu pun indikasi item hilang atau kosong.

Catatan tambahan: ditemukan file `app/Filament/Resources/StockOpnameResource.php.backup` di repo — di-diff terhadap versi aktif, terlihat `StockOpnameResource.php` baru saja mengalami **refactor besar** (filter gudang per-cabang ditambahkan, kolom tabel diperluas, badge status, dll). Resource ini jelas baru saja banyak diubah — memperkuat dugaan yang sama seperti Bagian A, Transfer Stok, dan Laporan Stok Interaktif: **staging kemungkinan besar menjalankan commit yang lebih lama** dari perbaikan-perbaikan ini. Rekomendasi: retest setelah staging di-deploy ke commit terbaru.

`APP_DEBUG` yang masih `true` di staging (stack trace Laravel tampil ke user) adalah masalah konfigurasi server, bukan kode — cukup set `APP_DEBUG=false` di `.env` staging.

---

*Dokumen ini melengkapi `AUDIT-SINTESIS-3FASE-08OKT2026.md`. Temuan C.2 dan C.5 di atas adalah instance baru dari Pola 6 dan pola terkait polymorphic-relation yang belum masuk daftar pola sistemik sebelumnya — pertimbangkan ditambahkan sebagai "Pola 7" jika retest berikutnya menemukan kasus serupa di tempat lain.*
