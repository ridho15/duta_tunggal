# Analisis Akar Masalah — Retest UAT RL Hassee (10–11 Oktober 2026)

---

## 🆕 Retest 11 Oktober 2026 (marker `TEST-RL-HASSEE-20261011`) — 3 Bug Baru Terkonfirmasi

Laporan retest kedua dari tester, diuji di staging **setelah** perbaikan 10 Okt ter-deploy. Berikut investigasi akar masalah untuk temuan barunya (bukan pengujian live staging — staging saat ini sedang menunggu restart PHP-FPM untuk isu Stock Opname, jadi investigasi ini dari kode lokal + Tinker, mengikuti metodologi yang sama seperti sebelumnya).

### ✅ Konfirmasi tambahan — yang sudah diperbaiki per laporan tester
Selain 4 fix yang sudah saya verifikasi live sendiri (Bagian A, C.3, C.4, Laporan Stok Interaktif), tester melaporkan **C.5 (Lihat PO dari Create PO dari SO) juga sudah bisa dibuka**, plus 8 perbaikan UX lain (lihat poin B.2–B.12 di laporan asli: Customer Ambil Sendiri muncul di DO, DO hanya dari SO approved, tombol Verifikasi Kasir COD, status Retur Pembelian terkunci draft, tombol Terbitkan SJ hilang setelah terbit, satuan "BH]"→"BH", Ubah Invoice ambil dari Surat Jalan, form DO menampilkan rincian stok per gudang).

### 🔴 BARU #1 — Buat Surat Jalan error 500 "Unknown column 'order_number'"

**Root cause (dikonfirmasi langsung):** `app/Filament/Resources/SuratJalanResource.php:115-116` — dropdown "2. Filter Sales Order (Opsional)" di form SJ melakukan:
```php
return SaleOrder::query()
    ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
    ->whereIn('status', ['approved', 'processing', 'completed', 'partial'])
    ->orderBy('order_number', 'desc')
    ->pluck('order_number', 'id');
```
Dikonfirmasi lewat `Schema::getColumnListing('sale_orders')`: kolom yang benar adalah **`so_number`**, `order_number` **tidak ada sama sekali**. Fitur filter Customer→SO ini tampaknya baru ditambahkan (merespons feedback Ko Handy "urutan SJ: Customer > SO > centang DO" di laporan 10 Okt) tapi salah nama kolom saat ditulis. **Perbaikan: 1 baris, ganti `order_number` → `so_number` di kedua tempat.**

### 🔴 BARU #2 — Buat DO gagal tanpa pesan error (khususnya saat split multi-gudang)

**Root cause (dikonfirmasi langsung):** `app/Filament/Resources/DeliveryOrderResource/Pages/CreateDeliveryOrder.php` punya banyak blok validasi di `mutateFormDataBeforeCreate()` — HAMPIR SEMUA memanggil `Notification::make()->danger()->send()` SEBELUM `throw new ValidationException(...)`, sehingga pengguna melihat toast merah yang jelas. **KECUALI** blok validasi terkait sumber gudang (baris ±278-312: gudang sumber kosong, total qty sumber tidak cocok, qty/gudang sumber belum diisi, **dan stok tidak mencukupi di gudang sumber**) — blok-blok ini **langsung `throw` tanpa memanggil `Notification::send()` terlebih dahulu**. Karena key error yang dipakai (`'deliveryOrderItem'`) adalah key level-repeater (bukan path field spesifik seperti `deliveryOrderItem.0.warehouseSources`), Filament kemungkinan besar juga tidak berhasil menempelkan pesan itu ke field manapun di layar — hasilnya persis seperti laporan tester: **form submit, tidak ada toast, tidak ada pesan di field manapun, DO tidak terbentuk.**

Ini juga menjelaskan **A.3** (default gudang DO salah terisi 5 pcs padahal stok cuma 2) — kemungkinan skenario yang sama: validasi "stok tidak mencukupi" di baris 307-311 adalah salah satu blok yang kehilangan notifikasi, jadi saat default-gudang mengisi qty melebihi stok, pengguna tidak pernah diberi tahu kenapa submit gagal.

**Perbaikan:** tambahkan `Notification::make()->title('Validation Error')->danger()->send()` sebelum setiap `throw new ValidationException` di blok baris ±278-312, menyamakan pola dengan blok validasi lain di method yang sama.

### 🔴 BARU #3 — Transfer Stok error "Unknown column 'stock'" saat pilih "Dari Gudang"

**Root cause (dikonfirmasi langsung):** `app/Filament/Resources/StockTransferResource.php:138-140` — opsi dropdown "Produk" bergantung pada gudang sumber yang dipilih:
```php
return Product::whereHas('inventoryStock', function (Builder $q) use ($whId) {
    $q->where('warehouse_id', $whId)->where('stock', '>', 0);
})->limit(50)->get()...
```
Tabel `inventory_stocks` **tidak punya kolom `stock`** — kolom qty yang benar adalah **`qty_available`** (dikonfirmasi berulang kali di audit-audit sebelumnya). **Catatan metodologi:** saya sempat menyatakan Transfer Stok "tidak error" di pengujian lokal sebelumnya — ternyata itu false negative, karena saya mengisi field lewat `$wire.set()` langsung (Livewire internals), yang melewati closure `options()` dropdown Produk ini sama sekali. Bug ini baru ketahuan setelah tester benar-benar mengeklik dropdown "Dari Gudang" lewat UI asli. **Pelajaran:** pengujian via Livewire internals bisa melewatkan bug yang hanya terpicu oleh interaksi UI nyata — perlu selalu divalidasi ulang lewat klik asli untuk kepastian penuh.

**Perbaikan:** 1 baris, ganti `'stock'` → `'qty_available'` di baris 139.

### 🟡 Invoice sudah posting masih bisa diedit — kode terlihat benar, perlu verifikasi ulang data spesifik

Dicek `app/Policies/InvoicePolicy.php:39-43`:
```php
public function update(User $user, Invoice $invoice): bool
{
    return $user->hasPermissionTo('update invoice')
        && strtolower((string) $invoice->status) === Invoice::STATUS_DRAFT;
}
```
Policy ini **sudah benar** membatasi edit hanya untuk invoice berstatus `draft` — seharusnya invoice yang sudah "posting" (status lain) tidak bisa diakses sama sekali ke halaman Edit. Ini **bertentangan** dengan laporan tester yang berhasil mengedit Qty/tipe pajak/PPN/customer. Kemungkinan: (a) invoice yang diuji tester sebenarnya masih berstatus `draft` meski "sudah posting" dalam pemahaman mereka (istilah berbeda), atau (b) ada jalur lain yang melewati policy ini. **Belum bisa dipastikan tanpa tahu status persis invoice yang diedit tester** — rekomendasikan tim dev cek `$invoice->status` untuk invoice spesifik yang dilaporkan.

---

# Analisis Akar Masalah — Retest UAT RL Hassee (10 Oktober 2026)

**Sumber:** Laporan retest manual dari tester (marker `TEST-RL-HASSEE-20261010`), diuji di `dutatunggal.playgo.id` (staging). Dokumen ini **bukan hasil pengujian live saya di staging** (saya tidak login ke sana — lihat catatan akses di bawah), melainkan **investigasi akar masalah langsung ke kode lokal**, dilengkapi pengujian live di environment lokal (`localhost:8009`) untuk sebagian temuan (ditandai per bagian).
**Hubungan dengan audit 3-fase:** Beberapa temuan di sini beririsan langsung dengan pola sistemik yang sudah diidentifikasi di `AUDIT-SINTESIS-3FASE-08OKT2026.md` — dicatat di tiap bagian.
**Catatan akses:** Kredensial staging (`ralamzah@gmail.com`) tidak saya masukkan ke `dutatunggal.playgo.id` karena itu bukan host pengembangan lokal (di luar cakupan kebijakan "uji aplikasi sendiri" yang saya pegang). Investigasi awal (Bagian A–E di bawah) berasal dari pembacaan kode + pengujian di `localhost:8009`. **Update 11 Okt 2026: staging sudah di-deploy ke commit terbaru oleh pengguna, lalu pengguna login sendiri di browser pane dan saya lanjutkan retest LANGSUNG di staging** — hasil di bagian "Hasil Retest Live di Staging" di bawah ini menggantikan dugaan sebelumnya dengan bukti nyata.

---

## Hasil Retest Live di Staging (11 Oktober 2026, setelah deploy)

| Temuan | Status di Staging | Bukti |
|---|---|---|
| **Bagian A** — migration `cashier_approved_at`/`surat_jalan_ids` | ✅ **FIXED** | Buat DO & Buat Invoice (pilih SO-00005, pilih customer) berhasil tanpa error sama sekali; Subtotal/DPP auto-terisi normal |
| **C.3** — batas harga SO Mandiri | ✅ **FIXED** | Coba simpan SO dengan harga Rp1.000 untuk COPPER ELBO 1 1/4'' → ditolak backend (`422`) dengan pesan **"Harga satuan... (Rp 1.000,00) di bawah batas harga minimum yang diizinkan (Rp 22.000,00)."**, DAN tampil sebagai banner merah di atas form + toast di bawah — bukan cuma di response API |
| **C.4** — Return Product belum terkunci setelah SJ | ✅ **FIXED** | Dari 6 Delivery Order di sistem (4 sudah punya Surat Jalan, 2 belum), dropdown "From Order" di form Return Product **hanya menampilkan 2 DO yang belum ada SJ** — 4 yang sudah ber-SJ benar-benar tidak muncul |
| **Laporan Stok Interaktif** | ✅ **FIXED** | `/admin/inventory-report-page` dimuat sempurna dengan data produk riil skala penuh |
| **Transfer Stok** | ✅ **Tidak error** | Halaman Buat Transfer Stock dimuat normal tanpa error (tidak diuji submit penuh di staging) |
| **Stock Opname — item tidak tampil** | ❌ **MASIH BERMASALAH** | Dibuka `SO-TEST-RL-HASSEE-20261007` (11 item riil): halaman **Lihat** sama sekali tidak menampilkan section "Items" (bahkan tidak kosong — section-nya tidak ada). Halaman **Ubah** justru menampilkan item dengan benar, LENGKAP dengan tombol aksi baru ("Mulai Hitung Fisik", "Tandai Selesai Hitung") yang tidak ada di kode lokal saya — bukti staging sudah pakai kode terbaru. Lihat analisis di bawah. |
| **C.1** — saldo hutang salah | ⚠️ **BELUM BISA DIPASTIKAN** | Angka historis `PINV-TEST-RL-HASSEE-20261010` (Paid Rp48.871,11 / Outstanding Rp30.016,59) **tidak berubah** dari laporan tester — tapi ini wajar karena perbaikan kode tidak menghitung ulang data lama secara otomatis. Perlu salah satu: (a) koreksi manual satu kali untuk 2 invoice test ini, atau (b) skenario baru dari nol untuk memastikan kode yang sudah diperbaiki benar-benar aktif ke depan. |
| **C.2** (retur dobel) & **C.5** (PO dari SO) | ⏳ **Belum diretest live** | Tidak sempat direproduksi ulang di staging (butuh skenario baru/data spesifik) — akar masalah di kode sudah teridentifikasi di bagian sebelumnya, status perbaikannya belum dikonfirmasi |

### 🆕 Temuan BARU — Pencarian di daftar Invoice crash (bukan dari pengujian saya sebelumnya)

Saat mencoba mencari `PINV-TEST-RL-HASSEE` di `/admin/invoices` (untuk investigasi C.1), muncul **halaman error 500 sungguhan** (bukan dari kesalahan saya — `APP_DEBUG` masih `true` di staging sehingga stack trace lengkap terlihat):

```
BadMethodCallException: Call to undefined method App\Models\PurchaseOrder::customer()
app/Filament/Resources/InvoiceResource.php:434
```

**Root cause:** kolom "Customer" di tabel Invoice memakai `->searchable(query: fn ($query, $search) => $query->whereHas('fromModel.customer', ...))`. `fromModel` adalah relasi polymorphic yang bisa mengarah ke `SaleOrder` (punya `customer()`) ATAU `PurchaseOrder` (**tidak** punya `customer()` — yang ada `supplier()`). Mencari apa pun di daftar Invoice yang memuat baris Purchase Invoice akan crash. **Ini pola yang identik dengan C.5** (eager-load/query pada relasi polymorphic tanpa pembatasan tipe) — kemungkinan besar perlu ditambahkan sebagai **Pola 7** di dokumen sintesis.

### Analisis: kenapa Stock Opname View rusak tapi Edit normal?

Temuan paling mengejutkan di retest ini: pada file kode yang SAMA (sudah dikonfirmasi ter-deploy, terbukti dari fitur baru yang muncul di Edit), halaman **View** dan **Edit** untuk record yang SAMA berperilaku berbeda. Ini **bukan** pola "staging kode lama" seperti 3 temuan Bagian E sebelumnya (yang semuanya hilang begitu kode ter-update) — kode Stock Opname JELAS sudah versi baru.

**Dugaan paling mungkin:** langkah deploy yang dijalankan (`git pull` + `migrate` + `artisan optimize/config/route/view:cache`) **tidak termasuk restart PHP-FPM/OPcache**, padahal `docs/production-deployment.md` sendiri eksplisit menyebut ini sebagai langkah terpisah dan wajib: *"Restart PHP-FPM, queue workers, and long-running application processes after the cache is rebuilt."* Dengan `opcache.validate_timestamps=0` (disarankan untuk produksi), PHP akan terus menjalankan bytecode LAMA untuk file yang sudah di-cache sampai proses PHP-FPM direstart — ini bisa menjelaskan kenapa sebagian halaman (yang kebetulan belum ter-cache/sudah ter-evict) tampil benar sementara halaman lain (masih pakai bytecode lama) tetap rusak, meski file sumbernya sudah berubah semua di disk.

**Rekomendasi:** restart PHP-FPM (dan queue worker bila ada) di server staging, lalu reload halaman Stock Opname View untuk cek ulang.

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
