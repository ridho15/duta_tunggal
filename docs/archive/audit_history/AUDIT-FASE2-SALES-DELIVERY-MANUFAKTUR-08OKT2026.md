# Audit Mendalam ERP Duta Tunggal — Fase 2: Penjualan, Pengiriman, Manufaktur

**Tanggal audit:** 7–8 Oktober 2026
**Metodologi:** Sama seperti Fase 1 — audit statis kode (service classes, observer, policy, config) dikombinasikan dengan pengujian langsung di browser memakai data riil (akun `sales_manager@example.com`, `owner@example.com`). Skenario dibangun dari nol karena database tidak punya Quotation/Sale Order/Delivery Order sebelumnya (beda dari Fase 1 yang sudah ada fixture UAT).
**Cakupan:** Quotation, Sale Order, Kredit Limit, Delivery Order (Perintah Pengiriman), Konfirmasi Gudang, Surat Jalan, dan Manufaktur (BOM → Production Plan → Manufacturing Order → Material Issue → Production → QC Produksi) — semua diuji live end-to-end dengan data riil.

---

## Ringkasan Eksekutif

Temuan paling penting di fase ini bukan satu bug tunggal, tapi sebuah **pola sistemik**: tim developer SUDAH membangun perbaikan untuk sebagian besar risiko yang diaudit di sini (lihat `config/sales.php`) — mulai dari larangan approve SO dengan stok kurang, reservasi stok sejak SO disetujui, sampai penguncian dokumen yang sudah diproses. Tapi **seluruh flag perbaikan itu (`sales.stock.*` dan `sales.controls.*`) masih berstatus mati (`false`) secara default, dan tidak ada satu pun yang diaktifkan di `.env` lingkungan ini**. Artinya: perbaikan sudah "dibayar" tapi belum "dinyalakan" — perilaku lama (termasuk bug yang sudah tercatat di `docs/audit_uat_bug_sedang.md` Isu 13) masih berlaku aktif di sistem yang berjalan sekarang.

Di atas kondisi itu, ditemukan **1 bug Kritis yang terbukti live** (hapus DO yang sudah terkirim tidak membalik stok — pola identik Purchase Receipt di Fase 1) dan **1 bug Kritis laten** (potensi stok terpotong dua kali untuk SO "Ambil Sendiri", tergantung jalur UI yang dipakai untuk mengisi gudang item). Kabar baik: validasi limit kredit pelanggan sudah benar diblokir di backend dengan proteksi race-condition yang baik — sayangnya pesan penolakannya tidak pernah sampai ke layar pengguna.

---

## 1. Critical Bugs & Logical Flaws

### 🔴 P0-1 — Hapus Delivery Order yang sudah "Dikirim" tidak membalikkan stok maupun jurnal (TERBUKTI, pola identik Fase 1)

**Dampak finansial/bisnis:** Persis seperti temuan Purchase Receipt di Fase 1. Begitu DO berstatus `sent` (barang sudah keluar gudang, `qty_available` sudah terpotong) atau `completed`, menghapus DO tersebut **tidak mengembalikan stok fisik** dan **menghapus langsung jurnal terkait (bukan membalik)**. Barang yang sudah keluar gudang akan tetap tercatat "hilang" dari sistem, sementara histori akuntansi penjualan terkait raib tanpa jejak reversal.

**Bukti teknis (diverifikasi langsung):**
- `app/Observers/DeliveryOrderObserver.php:490-517` — method `deleted()` hanya: (1) menghapus `JournalEntry` terkait dengan `$entry->delete()` langsung (baris 502-509, bukan jurnal pembalik), (2) melepas `StockReservation` (baris 512-513), (3) menghitung ulang progres SO. **Tidak ada satu baris pun yang membuat `StockMovement` pembalik untuk mengembalikan `qty_available` yang sudah terpotong saat status `sent`.**
- `app/Policies/DeliveryOrderPolicy.php:86-94` — method `delete()` hanya diblokir oleh `DocumentLock::blocks($deliveryOrder, 'delete')`, yang menurut komentar kodenya sendiri (baris 88) dikendalikan oleh flag `sales.controls.doc_lock`.
- `config/sales.php:64` — `'doc_lock' => (bool) env('SALES_CONTROLS_DOC_LOCK', false)` — **default `false`**, dan dikonfirmasi `.env` lingkungan ini tidak menyetel `SALES_CONTROLS_DOC_LOCK` sama sekali (grep kosong). **Artinya proteksi ini TIDAK AKTIF saat ini — siapa pun dengan peran Sales Manager/Admin/Owner/Super Admin benar-benar bisa menghapus DO yang sudah terkirim, hari ini, di sistem yang berjalan.**

**Steps to Reproduce:**
1. Buat SO → Approve → buat DO → Konfirmasi Gudang → "Kirim" (status DO jadi `sent`, stok terpotong — persis seperti yang saya lakukan di DO-TEST-0001 pada sesi audit ini).
2. Sebagai Sales Manager/Owner, hapus DO tersebut.
3. Cek `inventory_stocks`: qty tidak kembali. Cek Buku Besar: jurnal penjualan terkait hilang total, bukan dibalik.

---

### 🔴 P0-2 — Potensi stok terpotong dua kali untuk SO "Ambil Sendiri" (laten — tergantung jalur UI)

**Dampak finansial/bisnis:** Jika risiko ini terpicu, stok fisik sebuah produk akan berkurang DUA KALI dari jumlah yang sebenarnya keluar gudang, mencemari laporan stok dan HPP.

**Bukti teknis (diverifikasi langsung + dinuansakan lewat tes live):**
- `app/Observers/SaleOrderObserver.php:74-85` — method `updated()` **tanpa syarat apa pun** memanggil `handleStockReductionForSelfPickup($saleOrder)` setiap kali status SO berubah menjadi `completed` dan tipe pengirimannya `Ambil Sendiri`. Tidak ada pengecekan apakah kuantitas tersebut sudah pernah dipotong lewat Delivery Order.
- `handleStockReductionForSelfPickup()` (baris 480-554) memotong stok berdasarkan `$item->warehouse_id` langsung atau `$item->warehouseAllocations` — **bukan** berdasarkan apa yang sudah benar-benar dikirim via DO.
- **Saya reproduksi live** alur penuh: SO Mandiri (tipe Ambil Sendiri, 2 pcs) → Approve → buat DO → Konfirmasi Gudang → Kirim. Status SO otomatis ikut berubah ke `completed` **bersamaan** dengan DO menjadi `sent` (lewat `SaleOrderStatusSynchronizer`). Stok akhir: `qty_available` turun dari 2 → 0 (BENAR, tidak dobel) — **tapi bukan karena ada penjagaan**, melainkan kebetulan: form React "SO Mandiri" yang saya pakai untuk membuat SO **tidak pernah mengisi `warehouse_id` di level item** (dikonfirmasi: `grep warehouse_id` di `resources/js/components/SaleOrderForm/` nihil hasil), sehingga `handleStockReductionForSelfPickup()` masuk ke cabang "tidak ada warehouse_id dan tidak ada alokasi" dan **diam-diam `continue` (skip) sambil hanya menulis `Log::warning` yang tidak pernah dilihat siapa pun** (baris 534-539).
- **Tapi jalur ini TIDAK selalu kosong**: `app/Filament/Resources/SaleOrderResource.php` punya beberapa `Select::make('warehouse_id')` di level item (baris 1560, 2124, dan `ViewSaleOrder.php:389`) serta fitur alokasi multi-gudang (`warehouseAllocations`, baris ~1120-1250) — jalur Filament (bukan form React) **bisa** mengisi `warehouse_id`/alokasi pada item SO. Begitu salah satu dari jalur itu dipakai dan SO yang sama diselesaikan lewat DO, potongan ganda akan benar-benar terjadi karena tidak ada guard di `updated()`.

**Kesimpulan:** Ini bukan "mungkin bug", tapi **kode yang memang tidak punya penjaga** — saat ini "selamat" secara kebetulan karena satu form tertentu tidak mengisi satu kolom tertentu. Ini rapuh, bukan aman.

---

## 2. Business Gap & Internal Control

### 🟠 P1-1 — Pesan penolakan limit kredit tidak pernah sampai ke layar pengguna (TERBUKTI LIVE, direproduksi 2x)

Saya buat SO untuk customer "CV Sukses Makmur" (limit kredit Rp 25.000.000, tipe pembayaran Kredit) dengan total Rp 30.000.000 — melebihi limit Rp 5.000.000. Backend **menolak dengan benar dan pesan yang jelas**:

> `"Kredit limit tidak mencukupi. Limit: Rp 25.000.000, Terpakai: Rp 0, Order: Rp 30.000.000, Total akan menjadi: Rp 30.000.000"`

(`POST /api/v1/sales-orders` → `422 Unprocessable Content`, dikonfirmasi lewat `read_network_requests`)

Tapi **di layar, tidak terjadi apa-apa** — tidak ada toast, tidak ada alert, tidak ada perubahan visual sama sekali. Console browser hanya mencatat:
```
Error saving sales order: {message: Request failed with status code 422, ...}
```
Sales rep yang mencoba membuat order ini akan klik "Buat Sales Order", tidak melihat respons apa pun, dan tidak tahu bahwa order-nya ditolak karena limit kredit — apalagi tahu berapa sisa limit yang tersedia. Direproduksi dua kali untuk memastikan ini bukan sesi yang basi.

**Catatan positif:** validasi itu sendiri sudah solid — `CreditValidationService` + `SalesOrderService::approveLocked()` memakai `lockForUpdate()` (mencegah race condition saat dua SO disetujui bersamaan untuk customer yang sama), dan widget "Info Customer" di form menampilkan Limit/Terpakai/Sisa secara real-time sebelum submit — hanya saja saat validasi GAGAL, tidak ada umpan balik.

### 🟠 P1-2 — `Quotation` tidak menerapkan `CabangScope` (pola sama seperti Fase 1)

`app/Models/Quotation.php` punya kolom `cabang_id` (baris 158) dan otomatis mengisinya saat dibuat (baris 205-217), tapi **tidak pernah mendaftarkan `CabangScope`** — berbeda dari `SaleOrder.php:286`, `DeliveryOrder.php:176`, dan `Invoice.php:433` yang semuanya **sudah benar** menerapkannya (dikonfirmasi langsung lewat grep). Mitigasi yang ada hanya filter manual di `QuotationResource.php` pada satu tempat (listing utama) — tidak berlaku untuk relasi atau akses lain di luar Resource itu. Pola ini konsisten dengan temuan P0-1 di Fase 1 (PurchaseOrder/QualityControl/StockMovement) — sepertinya `CabangScope` memang belum jadi standar wajib yang dicek ulang setiap kali model baru dibuat.

### 🟡 P2-1 — Rak kosong lagi di Konfirmasi Gudang (pola sama seperti QC Fase 1)

Saat saya approve Warehouse Confirmation untuk DO-TEST-0001, field "Rak" pada baris item konfirmasi **kosong/tidak wajib diisi** — sama persis dengan temuan Rak di form QC Pembelian (Fase 1). Konsisten dengan whitepaper bahwa penelusuran lokasi fisik barang jadi lemah di banyak titik transaksi, bukan cuma satu modul.

### 🟡 P2-2 — Seluruh flag perbaikan Penjualan T2/T3 (`config/sales.php`) masih mati secara default

Dikonfirmasi langsung dari isi `config/sales.php`: *semua* 4 flag `stock.*` (`ledger`, `strict_dispatch`, `reserve_on_so_approve`, `block_short_approval`) dan 7 flag `controls.*` (`approval_rules`, `credit_policy`, `doc_lock`, `accounting_settings`, `central_numbering`, `credit_notes`, `customer_dedup`) berdefault `false`, dan `.env` lingkungan ini tidak menyalakan satu pun. Dampak konkret yang relevan dengan audit ini:
- `block_short_approval` mati → SO masih bisa di-approve meski stok fisik kurang (Isu 13 di `docs/audit_uat_bug_sedang.md` **masih berlaku aktif**, bukan sudah selesai seperti anggapan sebelumnya — status itu hanya berarti *kode perbaikannya sudah ditulis*, bukan *sudah berlaku*).
- `reserve_on_so_approve` mati → reservasi stok baru terjadi saat DO dibuat (bukan saat SO disetujui), membuka jendela waktu antara approval dan pembuatan DO di mana stok yang sama bisa "dijanjikan" ke SO lain tanpa penjagaan.
- `doc_lock` mati → langsung berkaitan dengan P0-1 di atas.

**Rekomendasi kebijakan**: tim bisnis & dev perlu sesi khusus untuk memutuskan kapan setiap flag ini dinyalakan secara bertahap (sudah ada rencana urutannya di `docs/PLAN-T2-STOK-PENGIRIMAN.md` §9 dan `docs/PLAN-T3-T4-PENJUALAN.md` §7) — ini bukan temuan baru untuk diperbaiki developer, tapi keputusan bisnis yang tertunda.

---

## 3. Manufaktur (BOM → Production Plan → MO → Material Issue → Production → QC) — DIUJI LIVE END-TO-END

Berbeda dari draf laporan sebelumnya, bagian ini sudah diuji penuh secara live (bukan cuma audit kode) dengan skenario nyata: BOM "Fitting Tee" (1 bahan baku, 1 barang jadi) di Cabang Manufaktur & Pabrik. Karena seluruh bahan baku di 6 BOM yang ada berstok 0, stok awal bahan baku diisi lewat **Stock Adjustment resmi** (fitur Inventory yang sah, bukan jalan pintas) sebelum alur produksi dijalankan.

**Alur yang berhasil dijalankan penuh:** Production Plan (manual formula, auto-schedule) → otomatis membuat Material Issue draft → Request Konfirmasi Gudang → Approve → Material Issue `completed` (stok bahan baku terpotong tepat sesuai kebutuhan: 20→10) → Manufacturing Order "Produksi" (sempat diblokir dengan benar oleh workflow guard karena Material Issue belum dikonfirmasi — **ini penjagaan yang baik**, bukan bug) → Production `in_progress` (jurnal WIP otomatis & seimbang) → "Finished" → QC Manufacture otomatis dibuat → QC "Complete" → stok barang jadi masuk (1 pcs) dengan jurnal WIP→Persediaan Barang Jadi yang seimbang.

### 🟡 P2-3 — Biaya Overhead Produksi salah coding ke akun Biaya Tenaga Kerja (BARU, terverifikasi dari baris jurnal nyata)

Saat Production dibuat (status in_progress), 4 baris jurnal WIP terbentuk dan seimbang (Debit = Kredit = Rp 208.000). Tapi dua baris biaya — "tenaga kerja langsung" (Rp 5.000) DAN "overhead produksi" (Rp 3.000) — **sama-sama diposting ke COA yang sama**: `5230 BIAYA TENAGA KERJA PROSES PRODUKSI`. Seharusnya overhead produksi punya akun sendiri (mis. "Biaya Overhead Pabrik"). Dampaknya: laporan Laba Rugi per kategori biaya (tenaga kerja vs overhead) tidak akurat untuk setiap produksi — overhead selalu "tersembunyi" di dalam angka biaya tenaga kerja.

### 🟡 P2-4 — Rak barang jadi hasil produksi selalu kosong (TERVERIFIKASI LIVE, pola sama P2-1/P1-1 Fase 1)

QC Manufacture (`QC-M-20261008-0001`) menampilkan field "Rak: -" dan `StockMovement` hasil produksi (`type: manufacture_in`) tersimpan dengan `rak_id: null`. Pola rak kosong ini sekarang terkonfirmasi di **tiga titik berbeda**: QC Pembelian (Fase 1), Konfirmasi Gudang (Fase 2 Sales), dan sekarang QC Produksi — bukan bug satu modul, tapi kelemahan sistemik penelusuran lokasi fisik di seluruh aplikasi.

### 🟡 P2-5 — `ProductionPlan` tidak menerapkan `CabangScope` meski punya kolom `cabang_id` (BARU)

Sama seperti `Quotation` (P1-2): tabel `production_plans` punya kolom `cabang_id` (dikonfirmasi langsung, dan terisi otomatis `23` saat saya buat Production Plan untuk BOM Cabang Manufaktur & Pabrik), tapi `app/Models/ProductionPlan.php` **tidak punya `booted()`/`CabangScope` sama sekali** (dikonfirmasi grep langsung). `Production` dan `MaterialIssue` malah tidak punya kolom `cabang_id` di skema sama sekali — isolasi cabang untuk keduanya sepenuhnya bergantung pada join manual di level Resource (relasi ke `ManufacturingOrder`), yang menurut catatan sebelumnya bisa membuat data "hilang dari listing" untuk Material Issue yang hanya terhubung lewat `production_plan_id` tanpa `manufacturing_order_id`.

### ✅ Catatan penting — status perbaikan P0-1 Fase 1 berubah SELAMA sesi audit ini berjalan

Saat memverifikasi ulang temuan P0-1 Fase 1 (kebocoran `CabangScope`), ditemukan bahwa **`app/Models/QualityControl.php` SEKARANG SUDAH menerapkan `CabangScope`** (baris 130-132) — ini TIDAK ADA saat audit Fase 1 (berdasarkan temuan agent saat itu). Dikombinasikan dengan `PurchaseOrderPolicy.php` yang juga berubah di luar sesi (lihat Lampiran A Fase 1), ini mengonfirmasi ada pihak lain yang sedang aktif menindaklanjuti laporan Fase 1 **saat sesi Fase 2 ini berjalan**. Yang **BELUM** diperbaiki (dicek ulang barusan): `app/Models/StockMovement.php` masih tidak punya `CabangScope` sampai saat ini.

### Hasil Positif Lain (Manufaktur)

- **Workflow guard material-sufficiency bekerja dengan benar**: mencoba memulai Production sebelum Material Issue dikonfirmasi gudang **diblokir dengan pesan jelas** ("Material Issue MI-20261008-0233 masih berstatus draft atau konfirmasi gudang belum selesai"), tercatat rapi di log — bukan dibiarkan lolos atau error 500 seperti dugaan awal saya.
- **QC-sebelum-stok konsisten**: Production selesai (`finished`) TIDAK langsung menambah stok barang jadi — stok baru masuk setelah QC Manufacture di-Complete. Tidak ada jendela waktu di mana barang "ada" di stok padahal belum lolos QC.
- **Jurnal WIP → Finished Goods seimbang dan tertelusur**: Debit = Kredit di setiap tahap (in-progress maupun completion), dengan `source_type`/`source_id` yang jelas mengarah ke `Production`, memudahkan audit trail akuntansi.
- **`ManufacturingOrder` sudah benar menerapkan `CabangScope`** (beda dari `ProductionPlan`/`Production`/`MaterialIssue` di atas) — konsisten dengan pola campur-aduk yang sama terlihat di Fase 1 & 2: sebagian model dalam satu alur kerja sudah benar, sebagian tertinggal.

**Belum diuji di sesi ini:** skenario reject QC Produksi (barang jadi gagal QC), varian biaya Standard vs Actual Costing, dan Manufacturing Order dengan multi-item/multi-level BOM.

---

## 4. Action Priority Checklist

| Prioritas | Temuan | Area |
|---|---|---|
| **P0 — Kritis** | Bangun mekanisme reversal resmi untuk Delivery Order (StockMovement pembalik + jurnal lawan) saat DO `sent`/`completed` dihapus atau dibatalkan; pertimbangkan menyalakan `sales.controls.doc_lock` segera sebagai mitigasi sementara | Integritas stok & akuntansi |
| **P0 — Kritis** | Tambahkan guard di `SaleOrderObserver::handleStockReductionForSelfPickup()` agar tidak memotong stok untuk kuantitas yang sudah dipotong lewat DO (cek `delivered_quantity` atau keberadaan DO terkait sebelum memotong) | Integritas stok |
| **P1 — Tinggi** | Tampilkan pesan error validasi (termasuk penolakan limit kredit) sebagai notifikasi/toast di form Sales Order — saat ini hanya ter-log ke console browser | UX kritis — sales rep tidak tahu order ditolak |
| **P1 — Tinggi** | Tambahkan `CabangScope` ke model `Quotation` | Isolasi multi-cabang |
| **P2 — Sedang** | Jadwalkan keputusan bisnis kapan mengaktifkan flag `sales.stock.*`/`sales.controls.*` secara bertahap sesuai rencana yang sudah ada di `docs/PLAN-T2-STOK-PENGIRIMAN.md` dan `docs/PLAN-T3-T4-PENJUALAN.md` | Keputusan bisnis, bukan bug kode |
| **P2 — Sedang** | Wajibkan Rak di Konfirmasi Gudang, QC Pembelian, dan QC Produksi — pola berulang di 3 titik berbeda | Traceability fisik gudang |
| **P2 — Sedang** | Pisahkan akun jurnal Biaya Overhead Produksi dari Biaya Tenaga Kerja (keduanya saat ini masuk COA 5230) | Akurasi laporan biaya produksi |
| **P2 — Sedang** | Tambahkan `CabangScope` ke `ProductionPlan`; evaluasi apakah `Production`/`MaterialIssue` perlu kolom `cabang_id` eksplisit (saat ini isolasi hanya lewat join manual, berisiko data "hilang" dari listing) | Isolasi multi-cabang Manufaktur |
| **Info** | `StockMovement.php` masih belum menerapkan `CabangScope` (per pengecekan ulang hari ini) — `PurchaseOrderPolicy` dan `QualityControl` sudah diperbaiki pihak lain selama sesi ini berjalan | Tindak lanjut P0-1 Fase 1 |

---

## Lampiran A — Hasil Positif (Terverifikasi)

1. **Validasi limit kredit solid di backend** — benar-benar memblokir (bukan sekadar warning), dengan `lockForUpdate()` yang mencegah race condition saat dua SO disetujui bersamaan untuk customer yang sama sedekat mungkin dengan limitnya.
2. **Alur Sale Order → Approve → DO → Konfirmasi Gudang → Kirim berjalan benar** untuk kasus yang saya uji: `qty_available` turun tepat sesuai qty dikirim, `StockReservation` dibuat saat konfirmasi gudang dan dilepas dengan benar saat pengiriman — tidak ada kebocoran/kekurangan qty dalam satu siklus penuh yang saya jalankan.
3. **Maker-checker pada approval SO berjalan** — Sales Manager yang membuat SO tidak bisa approve SO-nya sendiri (tombol "Approve" tidak muncul untuknya), harus role lain (Owner/Manajer berwenang) yang menyetujui.
4. **`PurchaseOrderPolicy` mulai diperbaiki** — saat audit Fase 2 ini berjalan, `app/Policies/PurchaseOrderPolicy.php` berubah di luar sesi ini: method `view`/`update`/`delete`/`restore`/`forceDelete`/`response`/`request` sekarang memanggil `$user->canAccessCabang($purchaseOrder->cabang_id)` — persis perbaikan yang direkomendasikan untuk P0-1 Fase 1. Perlu verifikasi apakah perbaikan serupa juga diterapkan ke `QualityControl` dan `StockMovement` (belum dicek ulang di sesi ini).

---

*Bagian dari audit 3 fase (Procurement/QC/Inventory ✅ → Sales/Delivery/Manufaktur ✅ sebagian (Manufaktur perlu live-test) → Finance/Akuntansi, belum dimulai). Laporan Fase 1: `docs/AUDIT-FASE1-PROCUREMENT-QC-INVENTORY-07OKT2026.md`.*
