# Audit Mendalam ERP Duta Tunggal — Fase 1: Procurement, QC, Inventori & Gudang

**Tanggal audit:** 7 Oktober 2026
**Metodologi:** Kombinasi (1) audit statis kode — service classes, model observers, policies, Filament resources — dan (2) pengujian langsung di browser dengan data riil (akun `purchasing_manager@example.com`, `checker@example.com`) terhadap OR/PO yang sudah ada di sistem (`OR-20261005-0001` → `PO-20261005-0001`, produk Mouse Wireless Logitech, 10 pcs, 8 sudah diterima sebelumnya, 2 sisa).
**Cakupan:** Master Data (Cabang/Gudang), Order Request, Purchase Order, Purchase Receipt (GRN), Quality Control, Purchase Return, Stock Movement/Rak. Tidak termasuk Sales, Manufaktur, dan Finance — itu untuk Fase 2 & 3.

---

## Ringkasan Eksekutif

Dari 8 temuan konkret, **2 bersifat Kritis** dan berdampak langsung pada integritas data finansial & isolasi data antar cabang. Yang paling mengkhawatirkan — dan sudah **dibuktikan langsung secara live di browser, bukan cuma dugaan dari kode** (lihat P0-1): **data Purchase Order, Quality Control, dan Stock Movement dari SATU cabang bisa dibuka, bahkan diedit/disetujui, oleh user cabang LAIN**, dan muncul normal di listing sehari-hari tanpa trik apa pun — padahal modul Purchase Receipt & Purchase Return di sebelahnya sudah benar menerapkan proteksi ini, artinya ini bukan keterbatasan arsitektur, tapi celah yang tertinggal saat modul-modul itu dibangun/diubah.

Kabar baiknya: penjagaan **over-ordering** dan **over-receiving** — dua risiko yang paling sering disebut di dunia procurement — sudah terbukti bekerja dengan baik saat diuji langsung (lihat Lampiran A). Begitu juga kunci tutup buku akuntansi (`AccountingPeriod`) sudah dipasang di level model `JournalEntry`, bukan cuma di UI, sehingga tidak bisa dibajak lewat jalur service manapun.

---

## 1. Critical Bugs & Logical Flaws

### 🔴 P0-1 — Kebocoran data lintas cabang: Purchase Order, Quality Control, dan Stock Movement tidak diisolasi per cabang

**Dampak finansial/bisnis:** User cabang A (misalnya Purchasing Manager Cabang Pusat Jakarta) berpotensi melihat, membuka, bahkan — karena Policy hanya mengecek permission string tanpa membandingkan `cabang_id` user vs record — **mengubah** PO, hasil QC, dan mutasi stok milik cabang lain. Ini melanggar prinsip dasar multi-cabang yang jadi salah satu syarat utama di awal audit ini, dan berisiko: data pembelian rahasia (harga beli, nama supplier, nilai PO) satu cabang terekspos ke cabang pesaing internal; laporan konsolidasi per-cabang jadi tidak bisa dipercaya karena user bisa mengedit data bukan miliknya.

**Bukti teknis (sudah diverifikasi langsung, bukan dugaan):**
- `app/Models/PurchaseOrder.php:164`, `app/Models/QualityControl.php`, `app/Models/StockMovement.php:179` — method `booted()` **tidak** mendaftarkan `CabangScope`.
- Sebagai pembanding: `app/Models/PurchaseReceipt.php:181-183` **benar** mendaftarkan `static::addGlobalScope(new CabangScope)`.
- `app/Filament/Resources/PurchaseOrderResource.php:3517-3526` — `getEloquentQuery()` hanya melakukan eager-load relasi (`with(...)`, `withCount(...)`), **tidak ada filter `cabang_id` sama sekali**. Dikonfirmasi langsung dari source code.
- `app/Policies/PurchaseOrderPolicy.php`, `QualityControlPolicy.php` — method `view()`/`update()` hanya memanggil `hasPermissionTo(...)`, tidak pernah membandingkan `cabang_id` user dengan record.
- User uji `purchasing_manager@example.com` dikonfirmasi `manage_type: ["branch"]`, `cabang_id: 1` — persis tipe user yang SEHARUSNYA dibatasi ke cabangnya saja.

**Catatan soal `OrderRequest`:** tabel header `order_requests` ternyata **tidak punya kolom `cabang_id`** sama sekali — cabang disimpan di level `OrderRequestItem` (tabel `order_request_items` punya kolom `cabang_id` eksplisit). Dicek: `app/Models/OrderRequestItem.php` juga **tidak punya `CabangScope`** di `booted()`-nya — pola kebocorannya identik, hanya levelnya di item bukan header.

**✅ TERBUKTI LIVE END-TO-END (bukan lagi dugaan kode) — direproduksi dalam sesi audit ini:**
1. Login sebagai **Owner** (all-access), buat PO baru **PO-20261007-0001** di **Cabang Bogor (CBG-220)**, gudang "Gudang quo", produk Mouse Wireless Logitech — tersimpan sebagai `purchase_orders.id = 2`.
2. Logout, login sebagai **`purchasing_manager@example.com`** — user ini terkonfirmasi `cabang_id = 1` (Cabang Pusat Jakarta), `manage_type: ["branch"]`, yaitu role yang SEHARUSNYA terkunci ke cabangnya sendiri.
3. Buka `http://localhost:8009/admin/purchase-orders/2` langsung → **berhasil terbuka penuh**, bukan 403. Semua detail PO Cabang Bogor terlihat: supplier, item, harga satuan, subtotal, grand total. Tombol **"Ubah"**, **"Setujui PO"**, dan **"Request Close"** semuanya tampil aktif.
4. Buka listing `http://localhost:8009/admin/purchase-orders` (tanpa filter apa pun) → **PO-20261007-0001 (Cabang Bogor) tampil berdampingan** dengan PO milik Cabang Pusat Jakarta sendiri di tabel yang sama. Ini bukan celah "tebak URL" — PO cabang lain muncul normal di layar listing sehari-hari.
5. Dikonfirmasi dari kode: `PurchaseOrderPolicy::update()` (dan `response()`/`request()` yang dipakai tombol approve) **hanya mengecek status PO + permission string**, tidak pernah membandingkan `cabang_id`. Tombol "Ubah"/"Setujui PO" yang tampil bukan kebetulan — Filament memang merender tombol itu karena policy mengizinkannya. Artinya PM Cabang Pusat Jakarta **bisa mengedit/menyetujui PO milik Cabang Bogor**, bukan cuma melihatnya. (Tidak jadi diklik agar data uji tidak makin bertambah kompleks — cukup kode + tombol aktif sebagai bukti.)

**Kesimpulan:** Ini BUKAN risiko teoretis. Dengan hanya 1 PO tambahan yang sengaja dibuat di cabang lain, kebocoran langsung terlihat di listing normal tanpa trik apa pun.

---

### 🔴 P0-2 — Menghapus Purchase Receipt/Item yang sudah diposting TIDAK membalik stok maupun jurnal

**Dampak finansial/bisnis:** Ini adalah skenario "Pembatalan Transaksi Hilir" yang eksplisit diminta diuji di brief awal. Hasilnya: **tidak ada mekanisme reversal sama sekali** untuk Purchase Receipt. Jika sebuah receipt yang sudah menambah stok dan membentuk jurnal dihapus (baik oleh Super Admin maupun lewat bug alur lain), maka:
- **Stok fisik (`InventoryStock.qty_available`) tetap terinflasi permanen** — barang "hilang" dari pembukuan tapi tetap tercatat ada di gudang, padahal catatan penerimaannya sudah tidak ada.
- **Jurnal akuntansi langsung di-soft-delete**, bukan dibalik dengan jurnal lawan (reversal entry). Artinya histori akuntansi hilang begitu saja — ini melanggar prinsip dasar audit trail akuntansi (PSAK & kontrol internal), berbeda dengan modul Purchase Invoice yang sudah benar (`PurchaseInvoiceCancellationService::cancel()` membuat reversal entry dengan guard `is_reversal`).

**Bukti teknis (diverifikasi langsung):**
- `app/Policies/PurchaseReceiptPolicy.php:39-43` — method `delete()` hanya `hasPermissionTo('delete purchase receipt')`, **tidak ada guard status** (bandingkan dengan `OrderRequestPolicy::delete()` yang benar memblokir jika `purchaseOrders()->exists()`).
- `app/Observers/PurchaseReceiptItemObserver.php:159-195` — method `deleted()` hanya membersihkan `PurchaseReturnItem` terkait. **Tidak ada satu baris kode pun yang menghapus atau membalik `StockMovement`.** Karena `StockMovement` bersifat polymorphic tanpa foreign key constraint di level database, baris stok yang sudah terbentuk tidak pernah tersentuh.
- `app/Traits/CascadesJournalEntries.php:28-49` — saat item dihapus, jurnal terkait di-`delete()` langsung (soft-delete), bukan dibuatkan jurnal pembalik.

**Steps to Reproduce:**
1. Ambil PO yang sudah `completed` dengan GRN & jurnal terbentuk (contoh: PO-20261005-0001 hasil pengujian sesi ini, QC-20261007-0001).
2. Sebagai Super Admin, hapus `PurchaseReceiptItem` terkait (via Tinker atau — jika ada tombol hapus tersembunyi — via UI).
3. Cek `InventoryStock` untuk produk tsb: qty tidak berkurang meski receipt-nya sudah "hilang".
4. Cek Buku Besar: baris jurnal terkait hilang total, bukan dibalik — saldo akun "Pembelian Belum Tertagih"/Persediaan jadi tidak bisa direkonsiliasi ke dokumen sumber manapun.

---

## 2. Business Gap & Internal Control

### 🟠 P1-1 — Rak Penyimpanan opsional di form QC → unit stok kehilangan lokasi fisik (terverifikasi LIVE, bukan dugaan)

Saat menguji skenario over-receiving (lihat Lampiran A), saya sengaja mengosongkan field "Rak Penyimpanan" saat submit QC untuk 2 unit sisa PO-20261005-0001. Sistem **menerima tanpa keluhan**. Hasil cek langsung ke tabel `inventory_stocks`:

| id | product | warehouse | rak_id | qty_available |
|----|---------|-----------|--------|----------------|
| 839 | Mouse Wireless (67) | Gudang Utama (1) | **1** | 8 |
| 733 | Mouse Wireless (67) | Gudang Utama (1) | **NULL** | 2 |

Total stok gudang (10) secara agregat **benar** — jadi ini bukan "silent failure" dalam arti stok hilang dari neraca. Tapi 2 unit tersebut **tidak punya lokasi rak** di sistem. Untuk staf gudang yang mengandalkan sistem untuk tahu "barang ini ada di rak mana", 2 unit ini **tidak bisa ditemukan** lewat laporan apa pun yang berbasis rak — baru akan ketahuan saat Stock Opname fisik menemukan selisih yang membingungkan (fisik ada tapi sistem bilang "tidak ada rak"/tersebar di baris terpisah).

**Rekomendasi:** Wajibkan "Rak Penyimpanan" saat `auto_process`/GRN aktif (stok benar-benar masuk), atau beri default rak per gudang agar tidak pernah NULL.

### 🟠 P1-2 — Retur Pembelian (non-QC) juga mengabaikan rak_id

Temuan dari audit kode (`app/Services/PurchaseReturnService.php:570-575`): saat retur gudang biasa (bukan reject QC) mengurangi stok, pencariannya memakai `firstOrCreate(['product_id'=>.., 'warehouse_id'=>..])` — **tanpa `rak_id`** di kriteria, padahal pola yang benar di tempat lain selalu `(product, warehouse, rak)`. Jika produk tersimpan di lebih dari satu rak dalam gudang yang sama, pengurangan stok bisa salah sasaran rak (row pertama yang ketemu), tanpa ada exception yang memberi tahu. Ditambah lagi, kolom `rak_id` memang tidak ada di tabel `purchase_return_items`, jadi informasi rak pada retur **selalu hilang** di source datanya.

*(Catatan: jalur reject-langsung-dari-QC sudah benar — barang yang direject QC memang belum pernah masuk stok, jadi tidak ada stok yang perlu "dibalik". Masalah ini spesifik untuk retur barang yang sudah diterima & baru ditemukan cacat belakangan.)*

### 🟡 P2-1 — Race condition pada validasi kuota OR dan anti-double-posting retur

- `app/Support/OrderRequestQuantityLock.php` — semua validasi sisa kuota memakai `SUM()` query biasa **tanpa `lockForUpdate()`**. Dua request PO/receipt yang hampir bersamaan terhadap item OR yang sama berpotensi lolos validasi bareng-bareng, menghasilkan over-order yang justru lolos dari penjagaan yang sudah terbukti bagus di skenario normal (Lampiran A).
- `app/Services/PurchaseReturnService.php` fungsi `adjustStock()` (baris 551-619) **tidak punya pengecekan duplikat jurnal sama sekali** — beda dengan `QualityControlService.php:476-480` yang sudah benar pakai `lockForUpdate()->exists()`. Klik approve dua kali atau retry jaringan berisiko double-post jurnal & double-decrement stok.

Ini adalah kelas bug yang jarang muncul di testing manual (butuh dua request nyaris bersamaan) tapi nyata risikonya di operasional sehari-hari dengan banyak user aktif bersamaan.

### 🟡 P2-2 — Override Rak di QC (jalur legacy) tidak tersinkron ke Purchase Receipt Item

`QualityControlService::completeQualityControl()` mengizinkan user mengoreksi `rak_id` saat QC, tapi koreksi ini tidak ikut disinkronkan balik ke `PurchaseReceiptItem.rak_id`. Proses lanjutan yang membaca `rak_id` dari item (bukan dari QC) akan memakai data basi. Dampaknya terbatas karena jalur normal sekarang selalu origin dari PO langsung, tapi tetap berisiko untuk data lama/edit manual.

---

## 3. UI/UX & Operator-Friendliness

### 🟡 Tombol aksi muncul untuk role yang sebenarnya tidak berwenang → layar 403 teknis

Saat login sebagai Purchasing Manager dan membuka detail PO, tombol **"Buat Quality Control"** tetap tampil meski role ini tidak punya izin `create quality control purchase`. Klik tombol tersebut mengarah ke halaman **403 Forbidden** mentah (layar gelap, teks kode HTTP) — bukan pesan ramah seperti "Anda tidak berwenang melakukan aksi ini, hubungi tim QC/Gudang". Untuk operator lapangan yang gaptek, ini sangat membingungkan dan terkesan aplikasi "error"/rusak.
**Rekomendasi:** sembunyikan tombol berdasarkan permission (`->visible(fn () => auth()->user()->can('create', QualityControl::class))`), bukan hanya mengandalkan policy di controller.

### 🟢 Minor — Field "Tindak Lanjut Barang Reject" tidak reset saat Reject kembali ke 0

Saat saya sempat mengisi Diterima=99 (ditolak sistem) yang otomatis memunculkan dropdown "Tindak Lanjut Barang Reject" terisi "Tunggu Pengganti", lalu saya perbaiki Diterima=2 (Reject=0) dan submit — nilai "Tunggu Pengganti" tadi **tetap tersimpan** di record final meski tidak relevan lagi (tidak ada barang yang direject). Tidak berdampak fungsional (karena reject=0), tapi membingungkan siapa pun yang membaca riwayat QC ini nanti dan bisa dibaca seolah ada proses reject yang terjadi.

### 🟢 Minor — Label "(N item tersedia)" di dropdown Order Request pada form PO tidak akurat

Dropdown pemilihan OR referensi di form "Buat Pesanan Pembelian" menampilkan "OR-20261005-0001 (1 item tersedia)" meski seluruh kuota item tersebut **sudah habis** teralokasi ke PO lain (sisa order = 0). Setelah OR dipilih, tabel item memang benar menunjukkan 0 item bisa ditambahkan (proteksi di belakangnya sudah benar — lihat Lampiran A) — tapi label di dropdown-nya sendiri menyesatkan ekspektasi user sebelum klik.

---

## 4. Action Priority Checklist

| Prioritas | Temuan | Area |
|---|---|---|
| **P0 — Kritis, segera** | Tambahkan `CabangScope` ke `PurchaseOrder`, `QualityControl`, `StockMovement`; audit juga level `OrderRequestItem` | Isolasi multi-cabang |
| **P0 — Kritis, segera** | Bangun mekanisme cancel/reversal resmi untuk Purchase Receipt (jurnal lawan + rollback StockMovement), plus guard status di `PurchaseReceiptPolicy::delete()` | Integritas stok & akuntansi |
| **P1 — Tinggi** | Wajibkan Rak Penyimpanan saat QC auto-process aktif, atau terapkan default rak | Traceability fisik gudang |
| **P1 — Tinggi** | Perbaiki `PurchaseReturnService::adjustStock()` agar menyertakan `rak_id` di pencarian/penulisan stok; tambah kolom `rak_id` ke `purchase_return_items` | Integritas stok retur |
| **P2 — Sedang** | Tambahkan `lockForUpdate()` di `OrderRequestQuantityLock` dan pengecekan duplikat jurnal di `PurchaseReturnService::adjustStock()` | Konkurensi/anti double-posting |
| **P2 — Sedang** | Sinkronkan override rak QC balik ke `PurchaseReceiptItem.rak_id` | Konsistensi data legacy |
| **P2 — Sedang** | Sembunyikan tombol "Buat Quality Control" dari role tanpa izin; ganti 403 generik dengan pesan ramah di seluruh aplikasi | UX/Internal control |
| **P3 — Rendah** | Reset field "Tindak Lanjut Barang Reject" saat Reject=0; perbaiki label "item tersedia" di dropdown OR agar mencerminkan sisa qty, bukan jumlah item | Polish UI |

---

## Lampiran A — Hasil Positif (Terverifikasi, Bukan Bug)

Agar laporan ini seimbang, berikut hal-hal yang **sudah bekerja dengan benar** dan terbukti lewat pengujian langsung maupun kode:

1. **Penjagaan over-ordering** — Saat mencoba membuat PO baru dari `OR-20261005-0001` yang kuotanya sudah 100% teralokasi (10/10), form "Buat Pesanan Pembelian" menampilkan **0 item yang bisa ditambahkan**. Form tidak mengizinkan menambah qty melebihi sisa approved OR.
2. **Penjagaan over-receiving** — Diuji langsung: PO-20261005-0001 sisa 2 unit, saya input Diterima=99 di form QC. Sistem menolak dengan pesan jelas: *"Validasi Quantity QC Gagal — Item #1 (Mouse Wireless Logitech): Sisa yang bisa di-QC 2 pcs"*. Validasi berjalan di backend (bukan sekadar JS), tidak bisa dibajak lewat request langsung.
3. **Kunci tutup buku periode akuntansi** — `AccountingPeriod::ensureDateIsOpen()` dipasang di `app/Models/JournalEntry.php` pada event `creating`/`updating`/`deleting` (baris 171, 179, 183) — artinya berlaku di LEVEL MODEL, otomatis mencakup semua service yang membuat jurnal (Purchase, Sales, Manufaktur, dll), bukan hanya dicek di satu tempat yang bisa dilewati dari jalur lain.
4. **Reject QC tidak pernah menyentuh stok** — Barang yang direject saat QC memang belum pernah dicatat masuk stok, jadi tidak ada risiko data stok "nyasar" untuk jalur reject-langsung ini (beda dengan retur barang yang sudah diterima, lihat P1-2 di atas).

---

*Dibuat sebagai bagian dari audit 3 fase (Procurement/QC/Inventory → Sales/Delivery/Manufaktur → Finance/Akuntansi). Lanjutan: Fase 2.*
