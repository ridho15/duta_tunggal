# Audit & Rencana Penyesuaian — 10 Bug Sedang UAT (Alur Penjualan)

> **Status dokumen:** hasil audit + rencana tahapan. **Belum ada satu pun baris kode yang saya ubah** — sesuai permintaan, hasil ini untuk Anda tinjau dulu.
> **Tanggal audit:** 19 September 2026, ±22:30 WIB · **Basis kode:** commit `c1c4c72` (branch `chore/cleanup-artifacts`) **ditambah** perubahan yang belum di-commit di working tree (lihat §0.2).
> **Cakupan:** Quotation → Sales Order → Delivery Order → Jadwal → Surat Jalan → Invoice → Penerimaan Customer → Laporan.

---

## Daftar Isi

0. [Baca dulu: 3 hal yang perlu perhatian Anda](#0-baca-dulu-3-hal-yang-perlu-perhatian-anda)
1. [Cara membaca & metode audit](#1-cara-membaca--metode-audit)
2. [Matriks ringkas 10 temuan](#2-matriks-ringkas-10-temuan)
3. [Audit rinci per temuan](#3-audit-rinci-per-temuan)
   - [Isu 1 — Data quotation tidak terbawa ke SO](#isu-1--data-quotation-tidak-terbawa-ke-so)
   - [Isu 2 — Angka modal "Buat SO dari Quotation" salah 100×](#isu-2--angka-modal-buat-so-dari-quotation-salah-100)
   - [Isu 3 — Ringkasan & status SO tidak ikut terupdate](#isu-3--ringkasan--status-so-tidak-ikut-terupdate)
   - [Isu 4 — Quotation approved masih bebas diubah](#isu-4--quotation-approved-masih-bebas-diubah)
   - [Isu 5 — Pemilihan SO di DO tidak dibatasi](#isu-5--pemilihan-so-di-do-tidak-dibatasi)
   - [Isu 6 — Surat Jalan belum layak cetak](#isu-6--surat-jalan-belum-layak-cetak)
   - [Isu 7 — Kontrol penerimaan uang customer lemah](#isu-7--kontrol-penerimaan-uang-customer-lemah)
   - [Isu 8 — Master driver & kendaraan kosong tapi wajib](#isu-8--master-driver--kendaraan-kosong-tapi-wajib)
   - [Isu 9 — Laporan penjualan belum memadai](#isu-9--laporan-penjualan-belum-memadai)
   - [Isu 10 — Tampilan nilai di invoice membingungkan](#isu-10--tampilan-nilai-di-invoice-membingungkan)
4. [Temuan tambahan di luar 10 poin](#4-temuan-tambahan-di-luar-10-poin)
5. [Perbandingan dengan dokumen audit lain (22:03)](#5-perbandingan-dengan-dokumen-audit-lain-2203)
6. [Tahapan update & penyesuaian (roadmap)](#6-tahapan-update--penyesuaian-roadmap)
7. [Keputusan yang saya butuhkan dari Anda](#7-keputusan-yang-saya-butuhkan-dari-anda)
8. [Strategi pengujian, rollout & rollback](#8-strategi-pengujian-rollout--rollback)

---

## 0. Baca dulu: 3 hal yang perlu perhatian Anda

### 0.1 Sebagian temuan UAT sudah tidak cocok dengan kode terbaru
Commit `c1c4c72` (19 Sep 21:34) memuat perbaikan yang menyentuh beberapa isu ini. Artinya build yang diuji UAT kemungkinan **lebih lama** dari `HEAD`. Saya memisahkan setiap temuan menjadi *masih terbukti di HEAD* vs *sudah diperbaiki sebagian di HEAD* (kolom "Status" di §2). Contoh: typo **"Develiry Order Number"** hanya tersisa di berkas `DeliveryOrderResource.php.backup`; resource aktif sudah `Delivery Order Number`. **Saran: ulangi UAT pada build `HEAD` sebagai baseline** sebelum fase perbaikan dimulai, supaya kita tidak memperbaiki yang sudah beres.

### 0.2 Ada pekerjaan paralel di repo ini — dan satu perubahannya berisiko tinggi
Saat audit berjalan, muncul: dokumen audit lain (`docs/audit_dan_rencana_penyesuaian_10_bug_sedang_penjualan.md`, 22:03), migrasi baru `2026_09_19_220401_add_notes_to_sale_orders_table.php`, dan **9 berkas kode yang berubah** setelah commit terakhir (`QuotationResource`, `ViewQuotation`, `SaleOrderResource`, `SaleOrderApiController`, `SaleOrder`, `SaleOrderItem`, `DeliveryOrderObserver`, `AppServiceProvider`, `SaleOrderApp.tsx`). Saya **tidak menyentuh** satu pun dari berkas itu. Saya menyebutnya "*in-flight*" di dokumen ini.

**Yang perlu Anda putuskan sebelum ada commit:** satu pemilik perubahan, dan tinjau dulu risiko berikut.

| # | Perubahan in-flight | Masalah | Tingkat |
|---|---|---|---|
| a | `app/Providers/AppServiceProvider.php:156` mengubah mask `$money($input, ',', '.', 2)` → `$money($input, '.', ',', 2)` (membalik separator **secara global**) | Macro `indonesianMoney()` dipakai **139 kali di 48 berkas**. Semua `formatStateUsing`, `MoneyHelper::safeParse`, dan handler `blur` di macro itu sendiri memakai format Indonesia (`.` ribuan, `,` desimal). Membalik mask membuat input uang di seluruh aplikasi bertentangan dengan format state-nya. Ini **bukan** akar masalah Isu 2 (lihat Isu 2). | 🔴 Tinggi ⚠️ |
| b | `SaleOrderApp.tsx:98, 226` memakai `q.tempo_pembayaran \|\| 30` | `0` (tunai/COD — nilai sah) dianggap kosong dan berubah jadi **30 hari**. Harus `??`. | 🟠 Sedang |
| c | `SaleOrderItem::getDeliveredQuantityAttribute()` menghitung ulang **hanya bila kolom bernilai 0/NULL** | Nilai basi yang > 0 tetap dipakai; dan tiap item menjalankan query sendiri bila relasi belum dimuat (N+1). | 🟠 Sedang |
| d | Migrasi `notes` + `$fillable` sudah ditambah, tetapi `SaleOrderApiController::store()/update()` **belum menyimpan** `header.notes` | Catatan dari form React tetap hilang. | 🟠 Sedang |

> ⚠️ Poin (a) saya nilai dari semantik `$money(input, pemisah_desimal, pemisah_ribuan, presisi)` dan dari konsistensi dengan seluruh kode Indonesia di macro tersebut. **Saya belum mengujinya di browser** — perlu uji manual sebelum diputuskan.

### 0.3 Beberapa temuan lebih dalam dari laporan UAT
- **Isu 6:** bukan sekadar "belum layak cetak" — endpoint PDF Surat Jalan **error 500** untuk setiap SJ yang punya DO (terbukti, §Isu 6).
- **Isu 7:** kolom "Penyesuaian Sisa" ternyata **tidak berfungsi sama sekali** (tidak dikirim ke server, tidak diposting); dan teks bantuan "kelebihan bayar dicatat sebagai deposit" **tidak diimplementasikan**.
- **Isu 3:** status baru `partially_delivered` belum dikenal UI, dan ada dua "jebakan regresi" (guard pembuatan DO & filter invoice di penerimaan customer).

---

## 1. Cara membaca & metode audit

**Legenda tingkat bukti**

| Ikon | Arti |
|---|---|
| ✅ | **Terbukti** — direproduksi / dihitung ulang / dibuktikan lewat query atau `tinker` (semuanya *read-only*) |
| 📖 | **Terbaca di kode** — kesimpulan dari membaca kode, belum dijalankan end-to-end |
| ⚠️ | **Perlu uji browser / UAT** — tidak bisa saya buktikan dari sini |

**Yang saya lakukan:** membaca kode sumber tiap isu (resource, page, service, observer, model, blade, React), membandingkan `HEAD` dengan working tree (`git diff`), menjalankan query *read-only* ke database lokal `duta_tunggal`, dan 3 reproduksi lewat `php artisan tinker` (relasi PDF Surat Jalan, angka 100× Isu 2, selisih pembulatan Isu 10).

**Batasan yang jujur:**
- Database lokal adalah **data dev**, bukan database UAT. SO-00005 milik UAT tidak bisa saya lihat; angka lokal saya pakai hanya untuk menunjukkan pola & cakupan.
- Saya **tidak menjalankan** test suite dan **tidak** membuka UI di browser.
- Nomor baris dirujuk pada kondisi kode saat audit; bisa bergeser bila ada commit baru.

---

## 2. Matriks ringkas 10 temuan

"Status" = kondisi pada `HEAD` (`c1c4c72`) + catatan in-flight. "Penilaian saya" adalah pendapat saya soal dampak riil, bukan klasifikasi UAT.

| No | Temuan | Status di kode | Akar masalah utama | Penilaian saya | Fase |
|:-:|---|---|---|:-:|:-:|
| 1 | Data quotation tak terbawa ke SO | ✅ Terbukti di HEAD; in-flight memperbaiki sebagian | Tiga jalur pembuatan SO menyalin data sendiri-sendiri; kolom `notes` tak ada; tempo `0` vs `NULL`; `currency_id` NULL | 🔴 Tinggi | 1 |
| 2 | Angka modal SO 100× | ✅ Terbukti (angka direproduksi persis) | State desimal-titik (`18155.83`) masuk ke field ber-mask Indonesia; presisi IDR dipaksa 0 desimal | 🟠 Sedang | 1 |
| 3 | Ringkasan SO tak ikut update | 🟡 Sebagian diperbaiki di HEAD (observer + enum); UI & guard belum | Status `completed` dipicu DO pertama; `delivered_quantity` punya banyak penulis; UI tak kenal `partially_delivered` | 🔴 Tinggi | 2 |
| 4 | Quotation approved bebas diubah | ✅ Terbukti, belum disentuh | Tak ada kunci di UI/Policy/API; tak ada kedaluwarsa; `created_by` tak terisi di jalur Filament; API bisa set `approve` | 🔴 Tinggi | 3 |
| 5 | Pemilihan SO di DO tak dibatasi | 🟡 Sebagian diperbaiki di HEAD (filter, typo) | Sisa: cek alamat, guard server, qty yang sudah "terpakai" DO terbuka, status masih Inggris/mentah, 2 pintu masuk lain | 🟠 Sedang | 2 |
| 6 | Surat Jalan belum layak cetak | ✅ Terbukti, lebih parah dari laporan | PDF error 500 (`deliveryOrder.customer` tak ada); halaman View menunjuk atribut yang tak ada; edit/hapus tak terkunci | 🔴 Tinggi | 4 |
| 7 | Kontrol penerimaan uang lemah | ✅ Terbukti, lebih dalam dari laporan | Cabang dari customer bukan invoice; COA induk ikut terpilih; kelebihan bayar dipotong senyap & tak dicatat; "Penyesuaian Sisa" hanya UI kosong | 🔴 Tinggi | 5 |
| 8 | Master driver/kendaraan kosong tapi wajib | 🟡 Sebagian (metode Ekspedisi sudah ada) | Default `internal` + wajib + dropdown kosong tanpa penjelasan/tautan | 🟠 Sedang | 4 |
| 9 | Laporan penjualan belum memadai | ✅ Terbukti, belum disentuh | Basis `sale_orders`; filter status hardcode tak sesuai enum; HPP tak tersimpan per baris | 🟠 Sedang | 6 |
| 10 | Nilai di invoice membingungkan | 🟡 Sebagian (PDF sudah punya kolom) | Form/infolist Filament hanya 4 kolom; `price` berbeda makna antar jalur; PPN dibulatkan 0 desimal di invoice vs 2 desimal di SO | 🟠 Sedang | 5 |

---

## 3. Audit rinci per temuan

Setiap isu memakai struktur yang sama: **Gejala UAT → Bukti di kode → Akar masalah → Rencana penyesuaian → Risiko regresi → Kriteria lulus.**

---

### Isu 1 — Data quotation tidak terbawa ke SO

**Gejala UAT:** SO-00005 tanpa tempo (30 hari), mata uang, alamat kirim; form edit menampilkan "-- Mata Uang --". Dampak: jatuh tempo invoice salah.

**Bukti di kode**

1. 📖 **Ada tiga jalur "SO dari quotation", masing-masing menyalin data sendiri** (di `HEAD`):

   | Jalur | Lokasi | Yang disalin | Yang **tidak** |
   |---|---|---|---|
   | Modal di halaman View Quotation | `ViewQuotation.php:114` | customer, cabang, tanggal, tipe kirim, total | **currency, kurs, tempo, alamat**; `notes` & `reference_type` dikirim tapi dibuang diam-diam (bukan `$fillable` / kolom tak ada) |
   | Aksi baris di tabel Quotation | `QuotationResource.php:1517` | + currency, kurs, tempo | **alamat**; `notes` dibuang |
   | Form React "Refer Quotation" | `SaleOrderApiController.php:211` (`getQuotation`) + `SaleOrderApp.tsx` | currency (bisa `null`), tempo `\|\| 0`, alamat customer | `notes` tak pernah sampai ke server |

   Gejala SO-00005 (semuanya kosong) paling cocok dengan jalur pertama. *Ini inferensi — database UAT tidak saya akses.*

2. ✅ **Kolom `notes` tidak ada di `sale_orders`** (skema `database/schema/mysql-schema.sql`) dan `SaleOrder::$fillable` tidak memuat `notes` → catatan hilang tanpa error.

3. ✅ **Tempo `0` vs `NULL` menentukan jatuh tempo invoice.** Due date dihitung `SO.tempo_pembayaran ?? customer.tempo_kredit ?? 30` di `SaleOrderObserver.php:252` dan `DeliveryOrderObserver.php:455`.
   - `SaleOrderApiController.php:373` dan `:577` menyimpan `$headerData['tempo_pembayaran'] ?? 0`, dan `SaleOrderApp.tsx:50` mengawali state dengan `0`. Angka `0` **bukan** `null` → jatuh tempo = hari ini.
   - Bila kolom `NULL` → jatuh ke tempo master customer, **bukan termin yang disepakati** di quotation.

4. ✅ **Data lokal menunjukkan pola yang sama** (query *read-only*): dari 37 SO, **28** punya `currency_id` dan `tempo_pembayaran` `NULL`; 3 tanpa `shipped_to`. Quotation Approved id 21–25 juga `currency_id` dan `tempo_pembayaran` `NULL`.

5. 📖 **`currency_id` `NULL` tidak punya fallback** di `getQuotation` (`HEAD`) maupun `show()` (`SaleOrderApiController.php:426`) → React menampilkan placeholder "-- Mata Uang --" untuk SO lama.

6. 📖 **Tabel `quotations` tidak punya kolom alamat kirim.** "Alamat kirim tersalin dari quotation" saat ini hanya bisa berarti *alamat master customer* (`customers.address`), tanpa snapshot pada saat penawaran disetujui.

**Akar masalah:** (1) tidak ada satu sumber kebenaran untuk pemetaan quotation→SO; (2) skema tidak menampung `notes` (dan alamat pada quotation); (3) `0`/`NULL`/falsy dicampur untuk "tempo"; (4) data legacy tanpa currency.

**Rencana penyesuaian**

| Langkah | Tindakan | Berkas |
|---|---|---|
| 1.1 | **Satu pemetaan tunggal**: `SalesOrderService::headerFromQuotation(Quotation)` mengembalikan `customer, cabang, currency_id, exchange_rate, tempo_pembayaran, shipped_to, notes`. Dipakai oleh modal View, aksi tabel, dan `getQuotation`. | `SalesOrderService`, 3 pemanggil |
| 1.2 | **Satu definisi modal** dipakai bersama oleh `ViewQuotation` dan `QuotationResource` (saat ini ±380 baris duplikat). Ini juga memperbaiki Isu 2 di satu tempat. | `QuotationResource`, `ViewQuotation` |
| 1.3 | Migrasi: `sale_orders.notes` (sudah in-flight) dan — bila Keputusan D1 = ya — `quotations.shipped_to`. | `database/migrations` |
| 1.4 | API: simpan `header.notes` di `store()`/`update()`; kembalikan `notes`, `shipped_to`, dan `currency_id` ber-fallback (IDR) di `show()`. | `SaleOrderApiController` |
| 1.5 | **Aturan tempo**: gunakan `??` (bukan `\|\|`); `0` = tunai (valid). Bila `null` → resolusi `quotation → customer → 30` lalu **simpan sebagai angka**, jangan dibiarkan `null`. | API, `SaleOrderApp.tsx` |
| 1.6 | Alamat tak boleh diisi `'-'`: bila customer tak punya alamat, biarkan `null` dan minta user mengisi sebelum SO disubmit. | modal, API |
| 1.7 | **Backfill data lama** — command `sales:backfill-so-from-quotation --dry-run`: SO ber-`quotation_id` disalin dari quotation; SO tanpa quotation dan tanpa currency → IDR. Keluarkan laporan; **jangan otomatis mengubah due date invoice yang sudah terbit** (hanya dilaporkan). | command baru |
| 1.8 | Rebuild aset React (`npm run build`) — `public/build` ada, perubahan `resources/js` tidak berlaku tanpa build. | deploy |

**Risiko regresi:** perubahan `tempo` mengubah tanggal jatuh tempo invoice **baru**; laporan ageing/AR ikut berubah. Komunikasikan ke Finance. Jangan mengubah invoice lama tanpa persetujuan.

**Kriteria lulus:** SO dari quotation (ketiga jalur) → `currency`, `kurs`, `tempo`, `alamat`, `catatan` identik dengan quotation; quotation tempo `0` menghasilkan SO tempo `0`; form edit SO lama tak lagi menampilkan "-- Mata Uang --"; `sales:backfill… --dry-run` melaporkan 0 SO tanpa currency setelah dijalankan.

---

### Isu 2 — Angka modal "Buat SO dari Quotation" salah 100×

**Gejala UAT:** PPN tampil Rp1.815.583 (seharusnya 18.155,83); subtotal Rp18.320.883 (seharusnya 183.208,83). Data tersimpan benar.

**Bukti & reproduksi**

- ✅ Dengan contoh UAT (20 × 8.687, diskon 5%, PPN 11% eksklusif): DPP = 165.053; PPN = **18.155,83**; subtotal = **183.208,83**.
- ✅ Bila string state desimal-titik `"18155.83"` dan `"183208.83"` dilewatkan ke mask Indonesia (titik = ribuan), hasilnya **`1815583`** dan **`18320883`** — **persis angka yang dilaporkan UAT**. Sedangkan `MoneyHelper::parse` sendiri membaca kedua string itu dengan benar; jadi yang salah bukan parser server.
- 📖 Pada `HEAD`, `ViewQuotation.php` memberi field read-only `subtotal` & `tax_nominal` macro `->indonesianMoney()` (baris 338 dan 343) **dan** mengisinya dengan angka mentah: `$set('subtotal', $subtotal)` (baris 232, 257, 313, 333) serta default `HelperController::hitungTaxNominal(...)` (baris 368). Angka mentah `float` → string `"18155.83"` → mask menganggap titik sebagai pemisah ribuan.
- 📖 Varian di `QuotationResource.php` (baris ±1734) sama: `subtotal` ber-`indonesianMoney()` + `readOnly()`; ditambah `formatCurrencyPreviewState()` (baris 219–227) **memaksa 0 desimal untuk IDR** pada `HEAD` (`$decimals = isIdrCurrency ? 0 : 2`) sehingga PPN 18.155,83 tampil 18.156.

**Akar masalah:** field *preview read-only* diberi mask **input** yang menafsirkan ulang string; state yang dimasukkan berformat berbeda (titik-desimal) dari yang dimengerti mask (koma-desimal); ditambah presisi IDR 0 desimal.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 2.1 | Field preview read-only (`subtotal`, `tax_nominal`, dll.) **tanpa** `indonesianMoney()`; isi selalu lewat **satu helper** `MoneyHelper::format2dp($x)` → string `"183.208,83"`. |
| 2.2 | Semua `$set(...)` di modal memakai helper yang sama (saat ini sebagian mentah, sebagian `number_format`). |
| 2.3 | Presisi IDR = 2 desimal (in-flight sudah mengubah `formatCurrencyPreviewState`); periksa dampaknya ke form quotation lain yang memakai fungsi ini. |
| 2.4 | **Jangan membalik mask global** di `AppServiceProvider` (lihat §0.2a). Bila tetap ingin dievaluasi, uji di browser dulu pada minimal 5 form uang berbeda. |
| 2.5 | Bersamaan dengan langkah 1.2 (satu definisi modal), bug ini hanya perlu diperbaiki sekali. |

**Risiko regresi:** memperbaiki presisi IDR mengubah tampilan semua tabel/infolist quotation (semua jadi 2 desimal). Itu memang diminta ("mengikuti nilai asli"), tetapi cek kolom yang lebarnya sempit.

**Kriteria lulus:** contoh UAT menampilkan `18.155,83` dan `183.208,83` di modal; test otomatis merender state modal dan menegaskan string; mengganti qty/harga/diskon tetap menghasilkan angka benar (tanpa lompat 100×).

---

### Isu 3 — Ringkasan & status SO tidak ikut terupdate

**Gejala UAT:** setelah 12 dari 20 pcs terkirim, SO menulis "Terkirim 0 | Sisa 20" tetapi status `completed`. Seharusnya Terkirim 12, Sisa 8, "Dikirim sebagian".

**Bukti di kode**

1. ✅ **Status `completed` dipicu DO pertama, tanpa cek kuantitas** (baseline `3b2e467`, sebelum `c1c4c72`): `DeliveryOrderObserver::handleCompletedStatus()` mengubah setiap SO terkait menjadi `completed` bila belum (`git show 3b2e467:…:280-290`).
2. 📖 **`HEAD` sudah menambah cabang parsial**: `DeliveryOrderObserver.php:306-329` menetapkan `completed` bila semua item terkirim, selain itu `partially_delivered`. Enum `sale_orders.status` ditambah lewat migrasi `2026_09_19_220000_…` (sudah ter-commit).
3. 📖 **Ringkasan hanya membaca kolom fisik** `delivered_quantity` (`SaleOrderResource.php:2192`, `:2194`). Kolom ini punya **empat penulis** yang berjalan sendiri-sendiri: `DeliveryOrderObserver` (sent & completed & deleted), hook `DeliveryOrderItem::booted()`, aksi di `DeliveryOrderResource.php:1276-1290`, dan importer legacy. Tidak ada satu tempat yang menghitung ulang dari sumber.
4. ✅ **UI tidak mengenal `partially_delivered`** — kata itu hanya muncul di `DeliveryOrderResource.php:110` dan observer. Tabel SO (`SaleOrderResource.php:1497-1530`) tidak punya label → tampil `PARTIALLY_DELIVERED` dengan warna `'-'` (nilai warna tidak valid).
5. 📖 **Daftar status yang mengecualikan `partially_delivered`** (akan memblokir SO parsial):
   - `CreateDeliveryOrder.php:101` — `['approved','confirmed','completed']` → **menolak DO kedua untuk sisa 8**, dan sekaligus **mengizinkan SO `completed`** (lihat Isu 5).
   - `SaleOrderResource.php:1798, 1849, 1881`; `ViewSaleOrder.php:91, 214, 240` — visibilitas aksi (Selesaikan, Tutup, PDF).
   - `ProductionPlanService.php:27`, relation manager Driver & Vehicle (`['approved','confirmed']`).
   - **Penerimaan customer:** `CustomerReceiptResource.php:273` dan `CreateCustomerReceipt.php:110` hanya menampilkan invoice dari SO berstatus `confirmed/received/completed`. Karena invoice dibuat **per DO**, invoice dari SO parsial akan **hilang dari daftar penerimaan** — kas yang masuk tak bisa dicatat. Ini jebakan regresi paling berbahaya.
6. 📖 **"Sisa" tidak menghitung qty yang sudah dialokasikan ke DO terbuka.** `remaining_quantity = quantity − delivered_quantity`, dan `delivered_quantity` hanya menghitung DO berstatus `sent/received/completed`. Guard `DeliveryOrderItem::saving` (`:102-109`) memakai definisi yang sama → dua DO `approved` bisa sama-sama mengklaim 20 pcs sebelum ada yang dikirim.
7. 📖 **Risiko deploy:** bila migrasi enum belum dijalankan di lingkungan, `update(['status' => 'partially_delivered'])` melempar *Data truncated* di MySQL strict — dan itu terjadi **di dalam penyelesaian DO**.
8. ✅ Data lokal: SO `SO-QAZGWZ` dan `SO-W3WVEY` berstatus `completed` dengan `delivered_quantity` 0 — pola yang sama dengan UAT (kemungkinan data seeder).

**Akar masalah:** (a) status SO diturunkan dari *event* DO, bukan dihitung dari kuantitas; (b) tidak ada satu sumber kebenaran untuk "terkirim/sisa"; (c) status baru ditambahkan tanpa menyisir semua konsumen status.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 3.1 | **Definisikan tiga angka** (dan tampilkan di ringkasan): *Terkirim* (DO `sent/received/completed`), *Dalam proses DO* (DO `draft…approved`), *Belum dijadwalkan* = qty − terkirim − dalam proses. |
| 3.2 | **Satu service** `SaleOrderDeliveryProgress` menghitung ketiga angka dengan **satu query ter-grup** per SO. `delivered_quantity` menjadi *cache* yang hanya ditulis oleh service ini. Hapus tiga penulis lain. (Ini menggantikan accessor in-flight yang hanya menghitung saat kolom 0.) |
| 3.3 | **Satu `SaleOrderStatusSynchronizer`** yang dipanggil pada *setiap* perubahan DO (sent, received, completed, reject, closed, deleted, item diubah): `approved` → `partially_delivered` (0 < terkirim < qty) → `completed` (terkirim ≥ qty). Bila DO dibatalkan/dihapus dan terkirim turun, status mundur. |
| 3.4 | **Sisir semua konsumen status SO** (daftar di poin 5) — ganti `in_array($status, [...])` menjadi konstanta `SaleOrder::DELIVERABLE_STATUSES` (`approved, confirmed, partially_delivered`). |
| 3.5 | **Penerimaan customer**: jangan menyaring invoice berdasarkan status SO; saring berdasarkan **AR** (`account_receivables.remaining > 0` milik customer tsb). |
| 3.6 | Tambah label + warna `partially_delivered` = "Dikirim Sebagian" di semua peta status SO (tabel, infolist, filter, widget, laporan). |
| 3.7 | Guard DO (`DeliveryOrderItem::saving`) dan opsi SO di DO memakai *Terkirim + Dalam proses* agar tak terjadi alokasi ganda. |
| 3.8 | Pra-deploy: verifikasi migrasi enum sudah jalan (`SHOW COLUMNS FROM sale_orders LIKE 'status'`). |
| 3.9 | **Backfill**: command `sales:resync-so-status --dry-run` menghitung ulang status & `delivered_quantity` semua SO dari DO; keluarkan daftar SO yang berubah (mis. `completed` padahal terkirim < qty). |

**Risiko regresi:** perubahan status memengaruhi pembuatan invoice otomatis (`SaleOrderObserver::createInvoiceForCompletedSaleOrder` dipicu saat `completed`). Setelah 3.3, SO tak lagi `completed` di DO pertama — pastikan invoice per-DO tetap terbit (`createInvoiceForCompletedDeliveryOrder`). Jalankan `CompleteDeliveryOrderFlowTest`, `DeliveryOrderJournalIntegrationTest`, `CompleteSalesFlowFilamentTest`.

**Kriteria lulus:** SO 20 pcs → DO 12 pcs selesai → ringkasan "Terkirim 12 · Sisa 8", status "Dikirim Sebagian"; DO kedua 8 pcs bisa dibuat; setelah selesai status `completed`; invoice dari DO pertama **tetap muncul** di penerimaan customer.

---

### Isu 4 — Quotation approved masih bebas diubah

**Gejala UAT:** tombol Ubah/Hapus tetap ada setelah approve; quotation Valid Until Mei 2026 masih Approved dan bisa dijadikan SO; "Created By" kosong.

**Bukti di kode**

1. ✅ **Tak ada kunci sama sekali** — `EditAction`/`DeleteAction` tanpa `->visible()` di `QuotationResource.php:1419-1421`, `DeleteBulkAction` di `:1884`, dan header `ViewQuotation.php:44-47`. `QuotationPolicy::update/delete` hanya memeriksa izin, **tidak status**. Halaman `EditQuotation` tak punya guard. Berkas React `edit-quotation.blade.php` memuat form edit untuk record berstatus apa pun.
2. ✅ **API tidak melindungi**: `QuotationApiController::update()` (`:362`) tak mengecek status dan **menerima `header.status = approve`** (`:177`, `:381`). Artinya siapa pun dengan izin *update* bisa menyetujui quotation lewat API tanpa izin *approve*. Hal sama untuk SO: `SaleOrderApiController.php:297` mengizinkan `approved`, dan `update()` (`:513`) tanpa batasan nilai.
3. ✅ **Tak ada logika kedaluwarsa** — `valid_until` hanya muncul sebagai field, kolom tabel, dan infolist. Aksi "Buat Sales Order" hanya cek `status == 'approve'` + izin (`QuotationResource.php:1517`). `SaleOrderApiController::dependencies()` (`:106-124`) memuat semua quotation `approve` **tanpa cek tanggal**, dan `store()` hanya memvalidasi `exists:quotations,id`. Enum `quotations.status` tak punya nilai kedaluwarsa.
4. ✅ **Data lokal:** dari 10 quotation Approved, **5 sudah lewat `valid_until`**.
5. ✅ **`created_by` kosong**: model `Quotation` **tidak punya hook** `creating` untuk `created_by`; hanya `QuotationApiController::store()` (`:228`) yang mengisi. Jalur Filament (`CreateQuotation::mutateFormDataBeforeCreate`), seeder, dan impor legacy tidak. Data lokal: **24 dari 29** quotation `created_by` `NULL`.
6. 📖 **Tak ada mekanisme revisi** — tidak ada kolom `revision_*`; satu-satunya cara "merevisi" adalah mengedit yang sudah disetujui.

**Akar masalah:** kunci diserahkan ke izin (bukan status); kedaluwarsa tak pernah diimplementasikan; `created_by` diisi di satu jalur saja; API mengabaikan alur approval.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 4.1 | **Kunci berbasis status** di empat lapis: Policy (`update/delete` false bila bukan `draft`/`reject`), aksi tabel & header (`->visible`), `EditQuotation::mount()` (redirect + pesan), dan API `update()` (422). |
| 4.2 | **Tutup celah API**: `header.status` hanya boleh `draft` atau `request_approve`; `approve`/`reject` hanya lewat aksi ber-izin. Sama untuk `SaleOrderApiController`. |
| 4.3 | **Revisi lewat versi baru** (aksi "Buat Revisi" pada quotation Approved/Kedaluwarsa): salin header + item ke draft baru, nomor `…-R1`, `revision_of_id` menunjuk versi lama; versi lama diberi tanda "digantikan". Migrasi: `revision_of_id`, `revision_no`, `superseded_at`. |
| 4.4 | **Kedaluwarsa** (Keputusan D2): tambahkan status `expired` + command harian `quotations:expire` (pola sama dengan `invoices:check-overdue` di `Kernel.php`) **dan** guard berbasis tanggal (`valid_until < today`) di modal, `dependencies()`, `getQuotation()` dan `store()` sehingga tetap aman walau job belum jalan. |
| 4.5 | **`created_by`**: hook `creating` di model (`Auth::id()`), plus `mutateFormDataBeforeCreate`. Data lama: command `quotations:backfill-creator --dry-run` mengisi dari `activity_log` (event `created`, `causer_id`) bila ada; sisanya tampil "Legacy" bukan kosong. |
| 4.6 | Backfill status: `quotations:expire --dry-run` melaporkan quotation Approved yang sudah lewat masa berlaku. |

**Risiko regresi:** mengunci edit memutus alur "koreksi cepat" yang mungkin dipakai user; siapkan alur revisi (4.3) **bersamaan** dengan kunci. SO yang sudah dibuat dari quotation lama tidak boleh ikut berubah.

**Kriteria lulus:** quotation Approved tak punya tombol Ubah/Hapus, URL edit langsung ditolak, API `update` → 422; quotation lewat `valid_until` berstatus Kedaluwarsa dan tak muncul di dropdown SO; `Created By` terisi pada semua jalur pembuatan.

---

### Isu 5 — Pemilihan SO di DO tidak dibatasi

**Gejala UAT:** SO Selesai (SO-00003) masih bisa dipilih; satu DO bisa menggabungkan SO customer berbeda; judul "Develiry Order Number"; status tampil mentah (`request_stock`, `approved`, `requested`).

**Status di `HEAD` — sebagian sudah beres**
- ✅ Typo: `DeliveryOrderResource.php:81` sudah `Delivery Order Number`; "Develiry" hanya di `.backup`.
- ✅ Opsi SO sudah difilter: `DeliveryOrderResource.php:110` (`approved, confirmed, partially_delivered`) + ada sisa kirim; aturan "customer harus sama" ada di `:138` dan `afterStateUpdated`.

**Yang masih terbukti**
1. 📖 **Alamat kirim belum dicek** — hanya `customer_id` (`:138`). Permintaan UAT: customer **dan** alamat sama.
2. 📖 **Guard server berbeda dari form**: `CreateDeliveryOrder.php:101` mengizinkan SO `completed` dan menolak `partially_delivered`; pengecekan "customer sama" hanya di rule `Select`, tidak di server. Form basi/pembuatan non-UI bisa lolos.
3. 📖 **Dua pintu masuk lain** membuat DO: `DriverResource/RelationManagers/DeliveryOrderRelationManager.php:61-62` dan `VehicleResource/…:66-67` — hanya `['approved','confirmed']`, **tanpa** cek sisa kirim, **tanpa** cek customer.
4. 📖 **"Sisa" mengabaikan DO terbuka** (lihat Isu 3, poin 6).
5. ✅ **Status masih Inggris/mentah**: kolom tabel DO (`:828-866`) → `REQUEST STOCK`, `APPROVED`, `SENT`; filter (`:931-945`) → "Request Stock", "Sent"; infolist `:655` `->badge()` mentah; `:683` **"SO Status" mentah**; status item DO (`:705-715`) menampilkan `requested/confirmed/sent` (berasal dari `DeliveryOrderService.php:42`). Peta label tersebar di tiap resource; tak ada satu tempat.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 5.1 | **Satu service validasi** `DeliveryOrderSourceValidator::validate(array $soIds)`: status boleh (`SaleOrder::DELIVERABLE_STATUSES`), sisa kirim > 0 (*Terkirim + Dalam proses*), customer sama, alamat sama (normalisasi `trim` + huruf kecil + spasi tunggal; alamat kosong → tolak dengan pesan mengisi alamat). Dipanggil oleh **form, `CreateDeliveryOrder`, dan kedua relation manager**. |
| 5.2 | Konstanta label: `DeliveryOrder::STATUS_LABELS`, `DeliveryOrderItem::STATUS_LABELS`, `SaleOrder::STATUS_LABELS` (pola sudah ada di `Invoice::STATUS_LABELS`). Terapkan di tabel, filter, infolist, `DoBelumSelesaiTable`, `ApprovalLogsRelationManager`, `ViewWarehouseConfirmation`, relation manager Driver/Vehicle. Bahasa: *Draf, Menunggu Stok, Menunggu Persetujuan, Disetujui, Sedang Dikirim, Diterima, Selesai, Ditolak, Pengiriman Gagal*. |
| 5.3 | Hapus berkas `*.backup` dari `app/` (`DeliveryOrderResource.php.backup`, `ViewQuotation.php.backup`, `ViewAgeingReport.php.backup`) agar tak menyesatkan pencarian. |

**Kriteria lulus:** SO `completed` tidak bisa dipilih maupun dikirim manual; menggabung SO beda customer/alamat ditolak di form **dan** di server; tak ada string status Inggris di modul Pengiriman; DO kedua untuk SO parsial bisa dibuat, dan DO ketiga yang melebihi sisa ditolak.

---

### Isu 6 — Surat Jalan belum layak cetak

**Gejala UAT:** tidak ada daftar barang, driver, kendaraan, kolom tanda tangan penerima; kolom "Terbit" kosong; dokumen bebas diubah/dihapus.

**Bukti di kode — dan ini lebih parah dari laporan**

1. ✅ **Endpoint PDF error 500.** `PdfPreviewController.php:100` memuat relasi `deliveryOrder.customer`, tetapi model `DeliveryOrder` **tidak punya relasi `customer`**. Reproduksi: `DeliveryOrder::with('customer')->first()` → `RelationNotFoundException: Call to undefined relationship [customer] on model [App\Models\DeliveryOrder]`. Terjadi untuk setiap SJ yang punya ≥ 1 DO. (Di data lokal tak ada SJ ber-DO, jadi tak pernah terlihat.)
2. ✅ **Halaman View menunjuk atribut yang tidak ada**: `ViewSuratJalan.php:37, 39, 57` memakai `shipping_method_display`, `sender_display`, `deliveryOrder_count` — model hanya punya `shipping_method_label` dan `sender_display_name`; entri jadi kosong. Entri `status` (`:28`) memetakan ke `'draft'/'issued'/'delivered'` padahal kolom `tinyint` 1/0 → tak pernah cocok. Ini kandidat penyebab "Terbit kosong".
3. ✅ **Daftar**: `SuratJalanResource.php:342` — `recordClasses(fn($r) => $r->terbit …)`; atribut `terbit` tidak ada → semua baris abu-abu; legenda "Biru = Terbit" tak pernah tampil.
4. ✅ **Tidak terkunci**: `EditAction` (`:314`), `DeleteAction` (`:317`), `DeleteBulkAction` (`:339`) tanpa syarat; `SuratJalanPolicy` hanya cek izin.
5. 📖 **`status` dipaksa `1` saat dibuat** (`SuratJalanResource.php:134`, `CreateSuratJalan.php:56`) → "Terbit" otomatis sejak awal. Maka "terkunci setelah terbit" berarti terkunci sejak dibuat; koreksi butuh mekanisme **batal**, bukan edit.
6. 📖 **Template `pdf/surat-jalan.blade.php`**:
   - ✔ sudah ada tabel barang dan dua blok tanda tangan (Penerima, "Hormat kami");
   - ✘ **tidak ada driver, kendaraan/plat, ekspedisi/resi, No. DO, No. SO**;
   - ✘ menampilkan **harga, diskon, pajak, subtotal** (dokumen serah-terima umumnya tanpa harga — Keputusan D3);
   - ✘ `Cabang` memakai `->cabang->name` (`:88`) padahal atributnya `nama` → selalu "N/A";
   - ✘ tanggal `->locale('id')->format('D, d M Y')` (`:65`) — `format()` tidak melokalisasi → "Sat, 19 Sep 2026" (perlu `translatedFormat`);
   - ✘ **alamat & telepon perusahaan placeholder** "Jl. Contoh No. 123 … (021) 12345678" (`:54`) pada dokumen resmi;
   - ✘ customer & alamat diulang per SO ("X, X,"); item **digabung per produk lintas DO** (`:120`) sehingga tak bisa dicocokkan per DO.
7. 📖 **Sumber driver/kendaraan ada di `DeliverySchedule`**, bukan di SJ/DO: jadwal menyimpan `driver_id`/`vehicle_id` (internal) atau `driver_name`/`vehicle_info` (ekspedisi) dan terhubung ke SJ lewat `delivery_schedule_surat_jalans`; `SuratJalan::primaryDeliverySchedule()` sudah tersedia. Form DO tidak mengisi `driver_id`/`vehicle_id`. Konsekuensi: SJ dibuat **sebelum** jadwal → saat cetak pertama driver belum diketahui.
8. 📖 **Nomor acak**: `SuratJalanService.php:15` `rand(0, 9999)` — tidak berurutan (masalah serupa dengan nomor `PR-` pada audit sebelumnya).
9. Data lokal: 10 SJ, hanya 1 berstatus `1` (kemungkinan seeder; tak bisa saya jelaskan lebih jauh).

**Akar masalah:** PDF tak pernah diuji dengan SJ ber-DO; halaman View & daftar ditulis terhadap atribut yang tidak ada; siklus hidup SJ tidak dimodelkan (langsung "terbit").

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 6.1 | Perbaiki eager-load PDF: `deliveryOrder.salesOrders.customer`, `deliveryOrder.deliveryOrderItem.product.uom`, `deliveryOrder.cabang`, `deliverySchedules.driver`, `deliverySchedules.vehicle`. Tambah test dengan SJ ber-DO. |
| 6.2 | Rombak template: kop dari data perusahaan/cabang nyata (bukan placeholder), No. SJ, tanggal (`translatedFormat`), No. DO & No. SO, customer (unik) + alamat kirim, **driver + plat** atau **ekspedisi + resi**, tabel **per DO** (No, SKU, Nama, Qty, Satuan, Keterangan), **tanpa harga** (D3), blok tanda tangan: *Yang Menyerahkan (Gudang)*, *Driver*, *Penerima (nama jelas, tanggal, cap)*, opsional *Mengetahui*. |
| 6.3 | Perbaiki halaman View & daftar (atribut/`withCount` yang benar, badge Terbit/Dibatalkan, warna baris berdasarkan `status`). |
| 6.4 | **Siklus & kunci**: `status` 1 = Terbit, 2 = Dibatalkan. Sembunyikan Edit/Hapus bila terbit (Policy + UI); tambah aksi **Batalkan** (alasan wajib, tercatat) lalu terbitkan ulang. Hanya `document_path` (bukti serah-terima bertanda tangan) yang tetap bisa diunggah. Migrasi: `cancelled_at`, `cancelled_by`, `cancel_reason`. |
| 6.5 | Nomor berurutan lewat `SequentialNumberGenerator` (`SJ-YYYYMMDD-0001`). |
| 6.6 | Cetak sebelum dijadwalkan (D3b): tampilkan "Belum dijadwalkan" atau tahan cetak sampai ada jadwal. |

**Kriteria lulus:** PDF terbuka untuk SJ ber-DO tunggal & jamak; memuat semua elemen di 6.2; SJ terbit tak bisa diubah/dihapus; pembatalan tercatat dengan alasan.

---

### Isu 7 — Kontrol penerimaan uang customer lemah

**Gejala UAT:** cabang terisi cabang lain sebelum invoice dipilih; COA menampilkan akun induk & default ke `1110`; kelebihan bayar dipotong senyap; ada "Penyesuaian Sisa" tanpa approval.

**Bukti di kode**

1. ✅ **Cabang dari customer, bukan invoice.** `CustomerReceiptResource.php:176-178` mengisi `cabang_id` dari `customer->cabang_id` begitu customer dipilih. Field cabang disembunyikan untuk user non-`all` (`:203-207`) namun tetap terisi. Setelah invoice dipilih, cabang hanya mengikuti invoice **terakhir** (`:357-362`) — tak ada validasi bila invoice yang dipilih berasal dari cabang berbeda.
2. ✅ **Daftar COA memuat akun induk & non-operasional.** `getCoaQueryByPaymentMethod()` (`:80-116`) menyaring kode `111%` + nama mengandung `kas/tunai` atau `bank/rekening/giro/cek`. Hasilnya termasuk `1110 KAS DAN SETARA KAS`, `1111 Kas Operasional`, `1112 Rekening Bank`, serta akun `… - DEPOSITO` dan `… - INVESTASI`. Default `orderBy('code')->value('id')` (`:122`) jatuh ke **`1110`**. Data lokal mengonfirmasi struktur ini.
   - ⚠️ **Tabel `chart_of_accounts` tidak punya kolom `is_parent`** (kolomnya: `parent_id`, `is_active`, `is_current`, dst.). Filter `where('is_parent', false)` (mis. usulan di dokumen audit lain) akan **error SQL**. Induk/anak harus dikenali lewat `parent_id`, atau — lebih tepat — lewat flag baru.
   - Master `cash_bank_accounts` (punya `coa_id`) ada namun **kosong** di data lokal.
3. ✅ **Kelebihan bayar dipotong senyap dan uangnya tak tercatat.** `CreateCustomerReceipt::validateAndFixDataConsistency()` (±`:222-248`) memotong jumlah ke sisa AR, mengirim notifikasi Inggris generik ("Payment amounts adjusted…"), lalu **menghitung ulang `total_payment` dari jumlah yang sudah dipotong**. Kelebihan uang tidak masuk ke mana pun. JS juga memotong dengan `alert()` (`customer-receipt-invoice-table.blade.php:444`, `customer-receipt-javascript-init.blade.php:623`). Teks bantuan di `CustomerReceiptResource.php:649` menyatakan kelebihan "akan dicatat sebagai customer deposit" — **tidak ada kode yang melakukannya** (posting deposit hanya untuk pembayaran yang *memakai* deposit).
4. ✅ **"Penyesuaian Sisa" hanyalah UI kosong.** Dropdown-nya memuat `ChartOfAccount::all()` (`…invoice-table.blade.php:169`) — semua akun, tanpa filter; JS `autoFillAdjustmentCOA()` (`:481`) mengisi otomatis dengan **COA kas utama**. Namun nilainya **tidak pernah dikirim ke server**: `updateAdjustmentBalance()` dan `updateAdjustmentDescription()` berbadan kosong, `payment_adjustment` tetap `0`, dan tak ada pemrosesan di observer maupun `LedgerPostingService`. Jadi **tidak ada penghapusan piutang yang benar-benar terjadi**; risiko riilnya: user mengira sisa sudah dihapus padahal AR tetap.
5. ✅ **Daftar invoice bocor lintas cabang**: query invoice memakai `withoutGlobalScope(CabangScope)` (`:268`). User cabang A dapat melihat/menerima pembayaran untuk invoice cabang B milik customer yang sama. Query juga dibatasi `sale_orders.status IN ('confirmed','received','completed')` (`:273`; `CreateCustomerReceipt.php:110`) → jebakan regresi `partially_delivered` (Isu 3).

**Akar masalah:** cabang diturunkan dari entitas yang salah; tak ada konsep "akun kas/bank yang boleh menerima uang"; kelebihan bayar tak punya model data; fitur penyesuaian tak pernah selesai tetapi UI-nya tampil.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 7.1 | **Cabang** mengikuti invoice: hapus pengisian dari customer; setelah invoice dipilih, cabang = cabang invoice. Bila invoice terpilih berasal dari lebih dari satu cabang → **tolak** dengan pesan jelas (D9). Untuk user non-`all`, aktifkan kembali `CabangScope` pada daftar invoice. |
| 7.2 | **Akun kas/bank**: migrasi `chart_of_accounts.is_cash_bank` (boolean). Command `coa:flag-cash-bank --dry-run` mengusulkan akun **leaf** (tanpa anak) di bawah `1111*`/`1112*` dikurangi `DEPOSITO`/`INVESTASI` — **wajib ditinjau akuntansi** sebelum dijalankan (D8). Selector memakai flag; default **kosong / wajib dipilih** bila kandidat > 1. Periksa helper serupa di Vendor Payment & Kas/Bank agar konsisten. |
| 7.3 | **Kelebihan bayar** (D4): validasi eksplisit di UI dan server dengan pesan Indonesia — *"Nominal Rp X melebihi sisa tagihan Rp Y (kelebihan Rp Z)."* Opsi **"Catat kelebihan sebagai Deposit Customer"** membuat `Deposit` dalam transaksi yang sama (model, `DepositNumberGenerator`, `LedgerPostingService::postDeposit`, dan COA `config('coa.customer_deposit')` = `2160.04` sudah ada). Tanpa opsi itu → ditolak. **Tidak ada pemotongan senyap.** |
| 7.4 | **Penyesuaian Sisa** (D5): **langkah aman — sembunyikan kolom** sampai ada fitur yang benar. Bila bisnis membutuhkannya: dokumen terpisah "Penyesuaian Piutang" ber-approval memakai `ApprovalControlService` (tier + anti-self-approval), COA dibatasi akun beban/kontra, jurnal Dr Beban / Cr Piutang, alasan wajib, tercatat pada AR. |
| 7.5 | Daftar invoice disaring dari **AR** (`account_receivables.remaining > 0` milik customer) — bukan dari status SO. |
| 7.6 | Koreksi teks bantuan agar sesuai perilaku sebenarnya. |

**Risiko regresi:** memblokir invoice lintas cabang bisa mengganggu customer yang dilayani banyak cabang — konfirmasi alur bisnis (D9). Perubahan COA default memengaruhi penerimaan yang sedang draf.

**Kriteria lulus:** akun `1110/1111/1112` dan `DEPOSITO/INVESTASI` tak muncul; tak ada COA terpilih otomatis bila ambigu; cabang penerimaan selalu = cabang invoice; nominal > sisa memunculkan pesan jelas dan tak pernah menghilang diam-diam; kolom Penyesuaian Sisa tak ada (atau ber-approval); invoice dari SO parsial tetap muncul.

---

### Isu 8 — Master driver & kendaraan kosong tapi wajib

**Gejala UAT:** Jadwal Pengiriman mewajibkan driver & kendaraan padahal master kosong; seluruh alur pengiriman berhenti tanpa penjelasan.

**Bukti di kode**

1. 📖 `DeliveryScheduleResource.php:196-225`: `delivery_method` **default `'internal'`** (`:203`); `driver_id` dan `vehicle_id` `required` untuk `internal`/`kurir_internal`, bersumber dari `->relationship('driver','name')` / `('vehicle','plate')`. Master kosong → dropdown kosong; satu-satunya pesan: "Driver wajib dipilih". Tak ada *empty-state*, tautan, maupun *quick-create*.
2. 📖 Metode **`ekspedisi` sudah ada** (nama driver/ekspedisi wajib, info kendaraan/resi opsional) — tetapi tak terlihat oleh user karena default `internal` dan tak ada petunjuk. Jadi permintaan UAT "izinkan metode Ekspedisi" sebagian sudah terpenuhi secara teknis.
3. ✅ **Alur pengiriman memang bergantung pada jadwal.** DO tidak punya aksi "Mulai kirim"/"Selesai" manual — aksi status DO di UI hanya `request_approve`, `approved`, `reject`, `request_close`, `closed` (`DeliveryOrderResource.php:1062-1150`) dan *Tandai Pengiriman Gagal*; `sent` dan `completed` hanya dipicu `DeliveryScheduleObserver` → `DeliveryScheduleService`. Tanpa jadwal, DO tak bisa maju → stok tak keluar, SO tak berubah.
4. 📖 Terkait: enum jadwal punya `partial_delivered` tetapi opsi form status tidak (`:245-252`); dan `completeRelatedDeliveryOrders()` menyelesaikan **semua** DO terkait sekaligus — tak ada pengiriman parsial per DO (berbenturan dengan Isu 3).
5. Data lokal: 33 driver & 33 kendaraan (seeder); database UAT kosong → indikasi **kesiapan master data go-live** belum ada pemeriksaan.

**Akar masalah:** validasi "wajib" tanpa deteksi kekosongan master; default yang tak sesuai kondisi awal; ketergantungan DO ke jadwal tak dikomunikasikan.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 8.1 | **Empty-state**: bila `Driver`/`Vehicle` kosong → banner "Master driver/kendaraan belum diisi" + tombol ke halaman tambah (hanya bila user punya izin `create driver`/`create vehicle`; jika tidak: "hubungi admin master data"). |
| 8.2 | `createOptionForm` pada select driver & kendaraan (bila berizin) → tambah cepat tanpa meninggalkan form. |
| 8.3 | Default metode = `ekspedisi` saat master kosong; helper text menjelaskan kapan memakai internal vs ekspedisi; pisahkan field **Nomor Resi** dari `vehicle_info`. |
| 8.4 | Validasi di server (bukan hanya `required` UI): internal → driver & kendaraan aktif; ekspedisi → nama ekspedisi. |
| 8.5 | **Checklist kesiapan master** (command/halaman `master:readiness`): driver, kendaraan, gudang, rak, COA kas/bank, mata uang, pajak — dijalankan sebelum UAT/go-live. |
| 8.6 | Selaraskan status parsial jadwal ↔ DO (bersama Isu 3). |

**Kriteria lulus:** membuka Jadwal dengan master kosong menampilkan penjelasan + tautan; pengiriman via Ekspedisi bisa dijadwalkan tanpa driver internal; checklist melaporkan master yang belum siap.

---

### Isu 9 — Laporan penjualan belum memadai

**Gejala UAT:** hanya berbasis SO; filter status (Draft, Dikonfirmasi, Diproses, Selesai, Dibatalkan) tak cocok dengan status sistem; tak ada HPP/margin.

**Bukti di kode**

1. ✅ **Basis SO & tanggal dokumen salah**: `SalesReportService.php:15` `SaleOrder::query()`; filter tanggal memakai `created_at` (`:16-17`), bukan `order_date`.
2. ✅ **Filter status hardcode tak sesuai enum**: `SalesReportPage.php:187-195` menawarkan `draft, confirmed, processing, completed, cancelled`. `processing` **tidak ada** di enum `sale_orders.status`; `cancelled` ≠ `canceled`. Status nyata yang tak terwadahi: `request_approve, approved, partially_delivered, received, closed, reject`. `summary()` (`:60-63`) hanya menghitung lima kunci itu → SO `approved` (mayoritas) tak tampil di kartu status.
3. ✅ **Tak ada HPP/margin/status pembayaran.** Ekspor memuat harga/DPP/PPN; PDF (`reports.sales_report`) hanya `so_number, tanggal, customer, total, status`.
4. 📖 **HPP tidak tersimpan per baris.** `InvoiceObserver::postCostOfSalesEntries()` (`:616-700`) menghitung `qty × products.cost_price` **pada saat posting** dari master saat itu, lalu menjurnal **teragregasi per akun COA**. Tabel `invoice_items` tak punya kolom HPP. Bila `cost_price` berubah kemudian, menghitung ulang dari master **tidak akan cocok** dengan jurnal COGS. Snapshot yang ada: `stock_movements.value` (= `cost_price × qty` saat DO `sent`).
5. ⚠️ **Mata uang**: `SO.total_amount` dijumlahkan langsung; konvensi (IDR vs mata uang asal) tidak seragam antar jalur pembuatan SO (aksi tabel Quotation mengonversi ke IDR; modal View tidak) → jumlah bisa mencampur mata uang. Perlu verifikasi.
6. Sumber status pembayaran ada: `account_receivables.remaining` dan `invoices.status` (`draft/sent/paid/partially_paid/overdue`).

**Akar masalah:** laporan dibangun di atas tabel operasional (SO) dan daftar status yang ditulis tangan; HPP tak dimodelkan sebagai data.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 9.1 | **Tiga mode** (D7): *Penjualan (Invoice)* — default, akrual; *Pengiriman*; *Pesanan (SO)*. Tanggal mengikuti dokumen (`invoice_date` / `delivery_date` / `order_date`). |
| 9.2 | **Snapshot HPP**: migrasi `invoice_items.cost_price` & `cogs_amount`, diisi di `postCostOfSalesEntries` dari perhitungan yang **sama** dengan jurnal (satu perhitungan → dua keluaran). Invoice lama: `invoices:backfill-cogs --dry-run` dari `stock_movements.value`, fallback `cost_price` sekarang **ditandai "estimasi"**. |
| 9.3 | **Kolom**: No. Invoice, tanggal, customer, SO, DO, produk, qty, harga, diskon, **DPP**, PPN, total, **HPP**, **Margin (Rp)**, **Margin (%) = (DPP − HPP) / DPP**, **Status pembayaran** (Belum bayar / Sebagian / Lunas / Jatuh tempo), cabang. |
| 9.4 | Filter status dari konstanta (`SaleOrder::STATUS_LABELS`, `Invoice::STATUS_LABELS`) — tidak ada hardcode. |
| 9.5 | Konversi ke IDR berdasarkan kurs snapshot invoice; subtotal per mata uang bila multi-currency. |
| 9.6 | **Uji rekonsiliasi**: total HPP laporan = total jurnal COGS pada periode yang sama. Ini dasar kepercayaan laporan. |
| 9.7 | Ekspor Excel & PDF (landscape) mengikuti kolom baru. |

**Kriteria lulus:** filter status hanya berisi status nyata; laporan berbasis invoice menampilkan HPP, margin, status pembayaran; total HPP cocok dengan jurnal COGS; ekspor sesuai layar.

---

### Isu 10 — Tampilan nilai di invoice membingungkan

**Gejala UAT:** baris invoice menulis Price Rp8.687 × 20 = Rp183.208,83 (padahal 8.687 × 20 = 173.740); diskon 5% dan PPN tak tampil per baris.

**Bukti di kode**

1. ✅ **Angka totalnya benar; tampilannya yang menyesatkan.** 20 × 8.687 = 173.740 → diskon 5% (8.687) → DPP 165.053 → PPN 11% = 18.155,83 → total **183.208,83**.
2. ✅ **Data lengkap sudah tersimpan tetapi disembunyikan.** `invoice_items` punya `discount, tax_rate, tax_amount, subtotal, total` dan diisi oleh `SaleOrderObserver`. Namun form Filament (`SalesInvoiceResource.php:793-839`) dan infolist (`ViewSalesInvoice.php:139-158`) hanya menampilkan **empat** kolom: produk, qty, *Price*, *Total*.
3. 📖 **`price` bermakna berbeda antar jalur pembuatan.** Jalur observer: harga **gross**. Jalur form Filament (`SalesInvoiceResource.php:320-321, 353-356, 452-453`): harga **net setelah diskon** (`unit × (1 − diskon)`), dan diskon tidak ikut disimpan. Bila memakai jalur kedua, PDF akan menampilkan "Harga Satuan" net **dan** "Discount 5%" — seolah diskon dua kali. ⚠️ Perlu dicek invoice UAT lewat jalur mana.
4. ✅ **Pembulatan PPN tidak seragam — direproduksi.** `TaxService::compute()` (dipakai invoice) membulatkan PPN eksklusif ke **rupiah bulat** (`TaxService.php:71`): PPN **18.156**, total **183.209**. Sedangkan `HelperController::hitungSubtotal` (SO/quotation, `:912`) dan `calculations.ts` (React) memakai **2 desimal** → **183.208,83**. Selisih **Rp0,17 per baris** antara SO dan invoice. PDF invoice memakai `number_format(…, 0)` (menyembunyikan sen), layar Filament menampilkan 2 desimal.
5. 📖 **PDF invoice sudah punya kolom** (`sale-order-invoice.blade.php:227-237`: Harga Satuan, Discount %, Tax %, Tax Amount, Subtotal, Total) — tetapi tanpa *Diskon (Rp)*, tanpa *Jumlah kotor* (qty × harga), dan kolom "Subtotal" sebenarnya **DPP** tanpa label DPP.

**Akar masalah:** definisi "baris invoice" tidak baku (makna `price`, tempat diskon), dan tiga fungsi hitung memakai aturan pembulatan berbeda.

**Rencana penyesuaian**

| Langkah | Tindakan |
|---|---|
| 10.1 | **Kebijakan pembulatan tunggal** (D6). Satu fungsi kanonik `LineAmounts::calculate(qty, price, discPct, taxRate, taxType)` → `gross, discount_amount, dpp, ppn, total`, dipakai oleh `HelperController`, `QuotationResource::calculateCurrencyPreview`, `TaxService::compute`, `SalesOrderService`, pembuatan invoice; `calculations.ts` diselaraskan. Rekomendasi **2 desimal** (selaras standar `decimal(15,2)` di `docs/CONTEXT.md` §9.5). ⚠️ Mengubah PPN yang diposting → perlu persetujuan akuntansi. |
| 10.2 | Migrasi `invoice_items`: `gross_amount`, `discount_amount` (Rp). `price` didefinisikan baku = **harga satuan gross**; **semua** jalur pembuatan invoice disamakan. |
| 10.3 | Tampilan (form, infolist, PDF): **Harga Satuan × Qty = Jumlah** · **Diskon (% dan Rp)** · **DPP** · **PPN (% dan Rp)** · **Total baris** — kolom terpisah; footer: Subtotal DPP, PPN, Biaya lain, Total. |
| 10.4 | Format 2 desimal konsisten di PDF dan layar. |
| 10.5 | Invoice lama: `invoices:backfill-line-breakdown --dry-run` melengkapi `gross_amount`/`discount_amount` dan **melaporkan** invoice yang `price`-nya net. Jangan mengubah nilai invoice yang sudah diposting. |

**Risiko regresi:** mengubah pembulatan berdampak ke jurnal PPN Keluaran **baru**; invoice lama tetap. Jalankan `SalesInvoice*`, `InvoiceService*`, `TaxService*`, `BalanceSheet*` dan tes konsistensi mata uang.

**Kriteria lulus:** setiap baris invoice memperlihatkan Harga × Qty = Jumlah, diskon, DPP, PPN, total — mudah dicocokkan dengan kalkulator; total invoice = total SO untuk contoh UAT (183.208,83); PDF dan layar menampilkan angka identik.

---

## 4. Temuan tambahan di luar 10 poin

Ditemukan selama audit. Tidak diminta UAT, tetapi beririsan dengan perbaikan di atas.

| # | Temuan | Bukti | Tingkat |
|:-:|---|---|:-:|
| T1 | **API melewati alur approval.** `header.status` menerima `approve` (Quotation, `QuotationApiController.php:177,381`) dan `approved` (SO, `SaleOrderApiController.php:297`); `SaleOrderApiController::update()` (`:513`) bahkan tanpa daftar nilai. Anti-self-approval dan batas nominal Rp10 juta di `ApprovalControlService` tidak berlaku pada jalur ini. | 📖 | 🔴 |
| T2 | **Data sampah di `quotations`** (lokal): baris `id = 20` bernilai `quotation_number = 'id'`, status `approve` — tanda impor yang salah membaca header. Perlu audit data legacy sebelum backfill Isu 4. | ✅ | 🟠 |
| T3 | **Daftar invoice penerimaan customer melewati `CabangScope`** (`CustomerReceiptResource.php:268`). Dibahas di Isu 7. | 📖 | 🔴 |
| T4 | **Tiga berkas `.backup` di `app/`**: `DeliveryOrderResource.php.backup`, `ViewQuotation.php.backup`, `ViewAgeingReport.php.backup`. Membuat pencarian kode menyesatkan (typo "Develiry" hanya ada di sini). | ✅ | 🟡 |
| T5 | **Nomor Surat Jalan acak** (`SuratJalanService.php:15`). | 📖 | 🟠 |
| T6 | **Jadwal: `partial_delivered` ada di enum & PDF, tak ada di form**; `completeRelatedDeliveryOrders()` menyelesaikan semua DO terkait sekaligus. | 📖 | 🟠 |
| T7 | **Logika hitung uang ada di ≥ 4 tempat** (`HelperController`, `QuotationResource::calculateCurrencyPreview`, `TaxService`, `calculations.ts`) dan **modal quotation→SO diduplikasi ±380 baris**. Sumber utama drift yang memunculkan Isu 2 dan Isu 10. | ✅ | 🟠 |
| T8 | **Pengaturan `do_approval_required`** (halaman App Settings) menyatakan DO "dapat langsung ditandai Terkirim dari Draft", tetapi tak ada aksi UI yang melakukannya; `sent`/`completed` hanya lewat jadwal. | 📖 | 🟡 |
| T9 | **Deploy React**: `public/build` ada; perubahan `resources/js` (form SO/Quotation/PO/OR) tak berlaku tanpa `npm run build`. | ✅ | 🟡 |

---

## 5. Perbandingan dengan dokumen audit lain (22:03)

Ada dokumen lain untuk 10 bug yang sama (`docs/audit_dan_rencana_penyesuaian_10_bug_sedang_penjualan.md`). Pada banyak titik kami sepakat (kolom `notes`, fallback currency/tempo, relasi PDF Surat Jalan, sinkron cabang penerimaan, opsi deposit untuk kelebihan bayar). Pada titik berikut kesimpulan saya berbeda; bukti ada di §3.

| Topik | Dokumen lain | Temuan saya |
|---|---|---|
| **Akar Isu 2** | Delimiter/separator mask `$money` terbalik; solusi: standarisasi macro di `AppServiceProvider` | Bukan. String desimal-titik masuk ke mask ber-desimal-koma; angka UAT **direproduksi persis** (`1815583`, `18320883`). Membalik mask global berisiko merusak 139 pemakaian. |
| **Filter COA Isu 7** | `where('is_parent', false)` | Kolom `is_parent` **tidak ada** di `chart_of_accounts` → error SQL. Gunakan `parent_id` atau flag baru. |
| **"Penyesuaian Sisa"** | Bisa dipakai siapa saja tanpa otorisasi | Fitur itu **tak berfungsi** (tidak dikirim/diproses); risiko riil = UI menyesatkan. |
| **Isu 6** | `IconColumn` boolean vs nilai integer | `tinyint(1)` boleh untuk boolean. Masalah nyatanya: atribut `terbit` tak ada, entri View menunjuk atribut tak ada, dan PDF error karena relasi. |
| **Isu 5** | Query opsi SO belum memfilter | Sudah difilter di `HEAD` (`c1c4c72`). Sisa: alamat, guard server, DO terbuka, label status, 2 pintu masuk lain. |
| **Isu 8** | User tak tahu opsi Ekspedisi | Opsi sudah ada; masalahnya default `internal` + tanpa *empty-state*. |
| **Isu 10** | PDF perlu kolom rincian | PDF **sudah** punya kolom; celahnya di form/infolist Filament, makna `price` antar jalur, dan pembulatan PPN 0 vs 2 desimal. |
| **Isu 9** | Estimasi HPP dari pergerakan stok | HPP dihitung dari `products.cost_price` saat posting dan tak tersimpan per baris; sarankan snapshot di `invoice_items`. |
| **Isu 3** | Kunci status `partially_delivered` | Setuju arahnya, tetapi belum mencakup label UI, guard `CreateDeliveryOrder`, dan **filter invoice penerimaan** yang akan menyembunyikan invoice SO parsial. |
| **Isu 4** | Sembunyikan tombol + hook `created_by` | Belum mencakup celah API (`status=approve`), guard server, revisi versi, dan penegakan kedaluwarsa di server. |

---

## 6. Tahapan update & penyesuaian (roadmap)

**Prinsip:** perbaiki dari yang paling fondasional; jangan mengubah data lama tanpa `--dry-run` dan persetujuan; satu commit per fase dengan pesan yang deskriptif (bukan "Update"); kerjakan di branch terpisah dari perubahan in-flight sampai ditinjau.

**Ketergantungan antar fase**

- **Fase 0** harus lebih dulu (baseline UAT + keputusan D1–D9).
- **Fase 3** memakai modal bersama dari langkah 1.2 (guard kedaluwarsa diterapkan di satu tempat).
- **Fase 5A** memakai konstanta status dan filter berbasis AR dari **Fase 2**.
- **Fase 6** memakai snapshot HPP/gross dari **Fase 5B** dan konstanta status dari **Fase 2**.
- **Fase 4** relatif mandiri (kecuali langkah 8.6 yang selaras dengan Isu 3).
- Urutan yang saya sarankan: 0 → 1 → 2 → 3 → 4 → 5A → 5B → 6.

Estimasi **kasar** (hari-orang, di luar UAT ulang dan antrean persetujuan akuntansi): total **±21–29 hari**.

### Fase 0 — Baseline & keputusan (±0,5–1 hari)
| Langkah | Tindakan |
|---|---|
| 0.1 | **Tinjau perubahan in-flight** (§0.2 a–d); putuskan pemilik tunggal dan urutan commit. Jangan commit sebelum poin (a) diuji di browser atau dikembalikan. |
| 0.2 | **UAT ulang pada build `HEAD`** untuk menandai isu mana yang benar-benar tersisa (khususnya 3, 5, 8, 10). |
| 0.3 | Pastikan migrasi `partially_delivered` sudah jalan di setiap lingkungan (`SHOW COLUMNS FROM sale_orders LIKE 'status'`). |
| 0.4 | Putuskan D1–D9 (§7). |
| 0.5 | Buat branch `fix/uat-medium-penjualan`; siapkan database uji `duta_tunggal_test` (lihat `TESTING_SAFETY.md`). |

**Gerbang keluar:** keputusan D1–D9 tercatat; baseline UAT tersedia.

### Fase 1 — Quotation→SO & angka (Isu 1, 2) (±2–3 hari)
Langkah: 1.1–1.8 dan 2.1–2.5 (§3). **Migrasi:** `sale_orders.notes`; opsional `quotations.shipped_to`. **Backfill:** `sales:backfill-so-from-quotation --dry-run`. **Tes:** SO dari tiga jalur identik; modal 2 desimal; tempo `0` tetap `0`. **Gerbang keluar:** contoh UAT (20 × 8.687) benar di modal & SO; laporan backfill ditinjau.

### Fase 2 — Status & progress SO + gerbang DO (Isu 3, 5) (±4–5 hari) — *jalur kritis*
Langkah: 3.1–3.9 dan 5.1–5.3. **Migrasi:** tidak ada baru (enum sudah ada). **Backfill:** `sales:resync-so-status --dry-run`. **Tes:** siklus SO 20 → DO 12 → DO 8; DO kedua untuk SO parsial; alokasi ganda ditolak; **invoice DO pertama tetap muncul di penerimaan** (regresi). **Gerbang keluar:** tak ada SO `completed` dengan terkirim < qty; semua label status berbahasa Indonesia.

### Fase 3 — Siklus hidup Quotation (Isu 4) (±3–4 hari)
Langkah: 4.1–4.6. **Migrasi:** `revision_of_id`, `revision_no`, `superseded_at`; nilai status `expired` (D2). **Backfill:** `quotations:expire --dry-run`, `quotations:backfill-creator --dry-run`. **Tes:** kunci di Policy/UI/API; API menolak `status=approve`; kedaluwarsa tak muncul di dropdown; revisi membuat versi baru. **Gerbang keluar:** tak ada quotation Approved yang bisa diedit/dihapus; `Created By` terisi.

### Fase 4 — Dokumen & logistik (Isu 6, 8) (±3–4 hari)
Langkah: 6.1–6.6 dan 8.1–8.6. **Migrasi:** `surat_jalans.cancelled_at/by/reason`. **Tes:** PDF SJ ber-DO tunggal & jamak; SJ terbit terkunci; Jadwal dengan master kosong. **Gerbang keluar:** PDF dapat dicetak dan ditandatangani; checklist kesiapan master tersedia.

### Fase 5A — Kontrol penerimaan kas (Isu 7) (±3 hari) — *butuh D4, D5, D8, D9*
Langkah: 7.1–7.6. **Migrasi:** `chart_of_accounts.is_cash_bank`. **Backfill:** `coa:flag-cash-bank --dry-run` (**ditinjau akuntansi**). **Tes:** cabang = cabang invoice; COA induk tak muncul; kelebihan bayar → pesan/deposit; invoice SO parsial muncul. **Gerbang keluar:** persetujuan akuntansi atas daftar akun kas/bank.

### Fase 5B — Transparansi invoice (Isu 10) (±2–3 hari) — *butuh D6*
Langkah: 10.1–10.5. **Migrasi:** `invoice_items.gross_amount`, `discount_amount`. **Backfill:** `invoices:backfill-line-breakdown --dry-run`. **Tes:** total invoice = total SO untuk contoh UAT; PDF = layar. **Gerbang keluar:** **persetujuan akuntansi atas kebijakan pembulatan PPN** (mengubah jurnal baru).

### Fase 6 — Laporan penjualan (Isu 9) (±4–6 hari)
Langkah: 9.1–9.7. **Migrasi:** `invoice_items.cost_price`, `cogs_amount`. **Backfill:** `invoices:backfill-cogs --dry-run`. **Tes:** uji rekonsiliasi HPP = jurnal COGS. **Gerbang keluar:** angka laporan cocok dengan Laba Rugi periode yang sama.

---

## 7. Keputusan yang saya butuhkan dari Anda

Tanpa keputusan ini, saya akan memakai **rekomendasi** yang tertera. Dampak "jika tidak diputuskan" menjelaskan risiko menunda.

| # | Pertanyaan | Opsi | Rekomendasi saya | Jika tidak diputuskan |
|:-:|---|---|---|---|
| D1 | Alamat kirim pada SO dari quotation | (A) ambil dari master customer · (B) tambah `quotations.shipped_to` agar alamat yang disepakati ikut tersalin | **B**, fallback ke A | Alamat SO ikut berubah bila master customer diubah; tak ada jejak kesepakatan |
| D2 | Kedaluwarsa quotation | (A) hanya dihitung dari tanggal · (B) status tersimpan `expired` + job harian + guard tanggal | **B** | Laporan/filter status tak mencerminkan kedaluwarsa |
| D3 | Isi Surat Jalan | (A) tanpa harga (item, qty, satuan) · (B) dengan harga | **A**; terbit = terkunci, koreksi via **Batalkan + terbitkan ulang** | Harga bocor ke pihak ekspedisi/penerima |
| D3b | Cetak SJ sebelum ada jadwal | (A) cetak dengan "Belum dijadwalkan" · (B) tahan sampai dijadwalkan | **A** | Gudang tak bisa menyiapkan dokumen lebih awal |
| D4 | Kelebihan bayar customer | (A) tolak · (B) opsi eksplisit "catat sebagai Deposit Customer" | **B** (default: tolak) | Uang kelebihan terus hilang dari pencatatan |
| D5 | "Penyesuaian Sisa" | (A) sembunyikan dulu · (B) bangun fitur write-off ber-approval | **A** sekarang; **B** hanya bila bisnis butuh | UI tetap menyesatkan |
| D6 | Pembulatan PPN | (A) 2 desimal di semua tempat · (B) rupiah bulat di semua tempat | **A** (selaras `decimal(15,2)`); wajib disetujui akuntansi | Selisih Rp0,17/baris SO vs invoice berlanjut |
| D7 | Basis default laporan penjualan | Invoice · Pengiriman · SO | **Invoice** (akrual) | Laporan tetap berbasis SO |
| D8 | Penentuan akun kas/bank di penerimaan | (A) filter leaf + nama · (B) flag `is_cash_bank` di master COA | **B** (A sebagai jembatan, ditinjau akuntansi) | Akun deposito/investasi tetap bisa terpilih |
| D9 | Penerimaan untuk invoice beda cabang | (A) blokir · (B) pecah otomatis per cabang | **A** | Jurnal penerimaan salah cabang |

---

## 8. Strategi pengujian, rollout & rollback

### 8.1 Tes baru per isu
Nama usulan (Pest, `tests/Feature/`): `QuotationToSaleOrderMappingTest` (1), `QuotationModalMoneyStateTest` (2), `SaleOrderDeliveryProgressTest` + `SaleOrderStatusSynchronizerTest` (3), `QuotationLockAndExpiryTest` + `QuotationApiApprovalBypassTest` (4), `DeliveryOrderSourceValidatorTest` + `StatusLabelsIndonesianTest` (5), `SuratJalanPdfTest` + `SuratJalanLifecycleTest` (6), `CustomerReceiptControlsTest` (7), `DeliveryScheduleEmptyMasterTest` (8), `SalesReportReconciliationTest` (9), `InvoiceLineBreakdownTest` + `TaxRoundingPolicyTest` (10).

### 8.2 Regresi wajib (dari `PLAN.md` + terkait)
`CompleteDeliveryOrderFlowTest`, `DeliveryScheduleTest`, `StockMovementComprehensiveTest`, `SalesOrderToDeliveryOrderCompleteTest`, `CompleteSalesFlowFilamentTest`, `DeliveryOrderJournalIntegrationTest`, `SalesUatCriticalBugVerificationTest`, `CustomerReceipt*`, `BalanceSheet*`, `Currency*`.

Jalankan **hanya** dengan skrip aman (database uji berakhiran `_test` dijaga otomatis oleh `tests/TestCase.php`):

```bash
composer test:safe -- tests/Feature/SalesUatCriticalBugVerificationTest.php
```

```bash
composer test:safe
```

### 8.3 Skrip UAT manual end-to-end (angka contoh)
1. Quotation: customer berkredit, tempo **30**, mata uang IDR, 1 item 20 × Rp8.687, diskon 5%, PPN 11% eksklusif → total **183.208,83**; ajukan & setujui (pengaju ≠ penyetuju).
2. Coba Ubah/Hapus → **ditolak**. Buat Revisi → versi baru draf.
3. Buat SO dari ketiga jalur → tempo 30, IDR, alamat, catatan **identik**; modal menampilkan `18.155,83` / `183.208,83`.
4. Setujui SO; buat **DO 12** → pilih SO tepat; coba campur customer/alamat berbeda → ditolak.
5. Master driver/kendaraan kosong → Jadwal menampilkan penjelasan; pilih **Ekspedisi** → jadwalkan.
6. Terbitkan SJ → PDF lengkap (item, driver/ekspedisi, TTD); coba Edit/Hapus → ditolak.
7. Mulai & selesaikan jadwal → SO: **Terkirim 12 · Sisa 8**, "Dikirim Sebagian".
8. Invoice DO-1 (12 pcs) → baris memperlihatkan harga × qty, diskon, DPP, PPN, total. Pada kebijakan 2 desimal: 12 × 8.687 = 104.244 → diskon 5% = 5.212,20 → DPP **99.031,80** → PPN 11% = **10.893,50** → total **109.925,30**.
9. Penerimaan customer → cabang = cabang invoice; COA induk tak tersedia; bayar melebihi sisa → pesan/deposit.
10. Buat DO 8 → selesai → SO **Selesai**.
11. Laporan penjualan (mode Invoice) → HPP, margin, status pembayaran; total HPP = jurnal COGS.

### 8.4 Rollout
1. `mysqldump` database sebelum rilis. 2. `php artisan migrate`. 3. Deploy kode **dan** `npm run build`. 4. Jalankan tiap command backfill dengan `--dry-run`, **tinjau laporan**, baru jalankan sungguhan. 5. Verifikasi dengan kueri pasca-rilis di bawah.

### 8.5 Rollback
- Setiap migrasi punya `down()`; migrasi data (backfill) **selalu** menulis CSV *sebelum/sesudah* sehingga bisa dipulihkan.
- Perilaku berisiko (kunci quotation, blokir cabang, pembulatan PPN) dibungkus **flag konfigurasi** agar dapat dimatikan tanpa rollback kode.

### 8.6 Kueri verifikasi pasca-rilis (semuanya harus 0)
- SO `completed` dengan total terkirim < total qty.
- Quotation `approve` dengan `valid_until` < hari ini.
- Quotation tanpa `created_by` yang dibuat setelah rilis.
- Penerimaan customer dengan `cabang_id` ≠ cabang invoice.
- Baris invoice baru dengan `dpp + ppn ≠ total` (toleransi 0,01).
- SO baru dengan `currency_id`/`tempo_pembayaran` `NULL`.

---

## Lampiran — Quick wins berisiko rendah (bila ingin bergerak sebelum semua keputusan)

Ini **tidak memerlukan keputusan D1–D9** dan berdampak kecil:

1. Ganti relasi `deliveryOrder.customer` → `deliveryOrder.salesOrders.customer` di `PdfPreviewController.php:100` (memulihkan PDF Surat Jalan).
2. Sembunyikan kolom "Penyesuaian Sisa" (menghilangkan UI yang menyesatkan).
3. Ganti `|| 30` → `??` di `SaleOrderApp.tsx:98, 226`.
4. Hapus tiga berkas `.backup` dari `app/`.
5. Tambahkan `partially_delivered` ke peta label/warna status SO dan ke `CreateDeliveryOrder.php:101` (sekaligus **keluarkan** `completed`).

*Dokumen ini adalah hasil audit; tidak ada kode aplikasi yang saya ubah.*

---

## Status pelaksanaan — Fase 1 (Isu 1 & 2), 19 September 2026

**Selesai (kode + tes):** langkah 1.1–1.8 dan 2.1–2.5.

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 1.1 Pemetaan tunggal | `SalesOrderService::headerFromQuotation()`, `resolveTempoPembayaran()`, `defaultCurrencyId()` — dipakai modal View, aksi tabel, dan API `getQuotation` | `app/Services/SalesOrderService.php` |
| 1.2 Satu definisi modal | Skema + handler dipindah ke `QuotationResource::saleOrderModalSchema()`, `canCreateSaleOrder()`, `createSaleOrderFromQuotation()` (dalam transaksi). `ViewQuotation` turun 354 baris | `QuotationResource.php`, `ViewQuotation.php` |
| 1.3 Migrasi | `quotations.shipped_to` (D1 = B). `sale_orders.notes` sudah ada (migrasi Anda) | `2026_09_19_230000_add_shipped_to_to_quotations_table.php` |
| 1.4 API | `notes` disimpan di `store()/update()` (update tidak menimpa bila tak dikirim); `show()` mengembalikan `notes`, mata uang ber-fallback IDR, tempo ter-resolusi; Quotation API menyimpan/mengembalikan `shipped_to` | `SaleOrderApiController`, `QuotationApiController` |
| 1.5 Aturan tempo | `??` bukan `\|\|`; `0` = tunai dihormati; `null` di-resolusi lalu disimpan sebagai angka. Infolist tidak lagi menampilkan tempo 0 sebagai "-" | API, `SaleOrderApp.tsx`, infolist SO/Quotation |
| 1.6 Alamat kirim | Field "Alamat Kirim" wajib di modal; tak ada lagi placeholder `'-'`; ditambahkan di form React Quotation (auto dari alamat customer) dan infolist | modal, `QuotationHeaderForm.tsx` |
| 1.7 Backfill | `sales:backfill-so-from-quotation` — **dry-run default**, `--apply` untuk menerapkan; hanya mengisi kolom kosong; tidak menyentuh invoice; menulis CSV sebelum/sesudah | `BackfillSaleOrderFromQuotation.php` |
| 1.8 Aset React | `npm run build` sukses (Catatan di form SO; Alamat Kirim di form Quotation) | `resources/js/components/*` |
| 2.1–2.3 Angka modal | Field `tax_nominal` & `subtotal` read-only **tanpa** `indonesianMoney()`, state = string 2 desimal dari satu fungsi; IDR 2 desimal | modal |
| 2.4 Mask global | **Tidak disentuh** — Anda sedang mengujinya | `AppServiceProvider.php` |

**Penyimpangan dari rencana (disengaja):**
1. Backfill **dry-run secara default** (bukan flag `--dry-run`) — lebih aman; perubahan hanya dengan `--apply`.
2. Kurs SO memakai **snapshot kurs quotation** (fallback kurs saat ini). Sebelumnya aksi tabel memakai kurs saat ini, jalur API memakai snapshot — kini seragam. Tidak berbeda untuk IDR.
3. **Perilaku status SO dipertahankan per jalur** lewat parameter `autoApprove`: aksi tabel → langsung `approved`; halaman View → `draft`. Keduanya berbeda sejak awal (lihat D10 di bawah).
4. Presisi IDR 2 desimal berlaku untuk seluruh UI quotation; **6 assertion** di `QuotationFeatureTest` yang mengunci format lama (`160.000`, `200.000`) diperbarui, dan spec Playwright `quotation-create-so-modal-money.spec.js` dilonggarkan untuk format `…,00`.

**Pengujian:**
- Baru: `tests/Feature/QuotationToSaleOrderPhase1Test.php` — **19 tes lulus** (pemetaan, tempo 0, fallback mata uang & alamat, API store/show/update, state modal 18.155,83 / 183.208,83, SO dari kedua jalur, atomisitas, backfill).
- Regresi: set quotation/SO awal **63 lulus, 6 gagal**; keenamnya **sama dengan baseline sebelum perubahan** (fixture `product_category_id` dan label "Eklusif"). Set alur penjualan lebih luas: 107 lulus, 4 gagal — keempatnya **terbukti sudah gagal** dengan `SalesOrderService` dikembalikan ke `HEAD` (mock `Auth::id()` pada `approve/close`, reservasi stok, status `confirmed`), bukan akibat Fase 1.

**Yang masih harus dilakukan sebelum Fase 1 dianggap selesai di lingkungan Anda:**
1. `php artisan migrate` (menambah `quotations.shipped_to`; tanpa ini menyimpan Quotation via API/React akan error) — di dev, UAT, dan produksi.
2. `npm run build` di setiap lingkungan (`public/build` di-gitignore).
3. `php artisan sales:backfill-so-from-quotation` (dry-run) → tinjau → `--apply`. Data dev: 28 SO akan dilengkapi, 3 invoice terkait tidak diubah.
4. Uji `$money` (Anda) — bisa memakai `npx playwright test tests/playwright/quotation-create-so-modal-money.spec.js`; spec itu mengharapkan mengetik `100000` menjadi `100.000`, jadi akan gagal bila arah mask dibalik.
5. Field `shipped_to` belum ada di form Filament `QuotationResource::form()` (UI aktifnya React); jalur Filament tidak menghapusnya, hanya tidak mengisinya.

**Keputusan baru — D10:** aksi tabel membuat SO langsung `approved` (dengan `approve_by` = pembuat), sedangkan halaman View membuat `draft` menunggu Manajer Sales. Jalur pertama **melewati** persetujuan SO dan anti-self-approval di `ApprovalControlService`. Saran saya: samakan ke `draft` (perlu approval), namun ini mengubah alur kerja sales sehingga saya tidak mengubahnya di Fase 1. Tes lama `SalesWorkflowTest` menulis `request_approve` sebagai harapan, tetapi hanya menyimulasikan logika (tidak memanggil kode aplikasi), jadi bukan acuan.

---

## Status pelaksanaan — Fase 2 (Isu 3 & 5), 19 September 2026

**Selesai (kode + tes):** langkah 3.1–3.7, 3.9, 5.1–5.3. Langkah 3.8 (cek enum) tetap menjadi pemeriksaan pra-deploy (lihat bawah).

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 3.1 Tiga angka | *Terkirim* (DO `sent/received/completed`), *Dalam Proses DO* (DO terbuka), *Sisa Belum Dikirim* (qty − terkirim) dan *Belum Dijadwalkan* (qty − terkirim − dalam proses) tampil di ringkasan SO dan detail item | `SaleOrderResource.php` |
| 3.2 Satu sumber kebenaran | `SaleOrderDeliveryProgress` menghitung dengan satu query ter-grup; `delivered_quantity` menjadi cache yang **hanya** ditulis service ini. Empat penulis lama (observer ×4 titik, hook item DO, aksi checker, accessor sementara) dihapus | `SaleOrderDeliveryProgress.php`, `DeliveryOrderObserver.php`, `DeliveryOrderItem.php`, `DeliveryOrderResource.php` |
| 3.3 Status dari kuantitas | `SaleOrderStatusSynchronizer`: `approved` → `partially_delivered` → `completed`, dan **mundur** bila DO gagal/dihapus/dibatalkan. Dipanggil pada setiap perubahan status DO, hapus, pulihkan, dan perubahan/pelepasan item | `SaleOrderStatusSynchronizer.php` |
| 3.4 Konsumen status | Konstanta `SaleOrder::DELIVERABLE_STATUSES` dipakai form DO, halaman Create/Edit, Production Plan; aksi SO parsial (minta tutup, saldo titip, PDF) ikut mengenali `partially_delivered` | model + resource |
| 3.5 Penerimaan customer | Query invoice diekstrak menjadi `CustomerReceiptResource::invoiceableInvoicesQuery()` dengan `SaleOrder::INVOICEABLE_STATUSES` (termasuk `partially_delivered`) — **jebakan regresi tertutup**. Penyaringan berbasis AR tetap Fase 5A | `CustomerReceiptResource.php`, `CreateCustomerReceipt.php` |
| 3.6 Label | `STATUS_LABELS`/`STATUS_COLORS` + `statusLabel()/statusColor()` di `SaleOrder`, `DeliveryOrder`, `DeliveryOrderItem`; dipakai tabel, filter, infolist, widget, relation manager, log approval | model + resource |
| 3.7 Guard alokasi | Guard item DO menghitung DO terbuka (bukan hanya yang sudah terkirim); dua DO tidak bisa mengklaim kuantitas yang sama | `DeliveryOrderItem.php` |
| 3.9 Backfill | `sales:resync-so-status` — dry-run default, `--apply` menulis CSV; memakai logika target yang **sama** dengan sinkronisasi nyata | `ResyncSaleOrderStatus.php` |
| 5.1 Validator tunggal | `DeliveryOrderSourceValidator` (status layak, sisa kuantitas, customer sama, **alamat kirim sama**) dipakai oleh form DO, Create, Edit, dan relation manager Driver & Vehicle | `DeliveryOrderSourceValidator.php` |
| 5.2 Label Indonesia | Tidak ada lagi `Str::upper($state)` / status Inggris mentah di modul Pengiriman; "SO Status" dan status item (`requested` dst.) berbahasa Indonesia | — |
| 5.3 Berkas usang | Tiga `.backup` dihapus (`DeliveryOrderResource`, `ViewQuotation`, `ViewAgeingReport`); tiga lain milik modul lain dibiarkan | — |

**Keputusan desain (perlu Anda ketahui):**
1. **Hanya `closed` yang melepas kuantitas DO kembali ke SO.** `delivery_failed`, `reject`, `partial`, `supplier` tetap "terikat" karena DO dapat diperbaiki/dijadwalkan ulang (jadwal memang boleh memulai DO `delivery_failed`). Konsekuensi: DO ditolak yang tidak diperbaiki menahan kuantitas sampai dihapus/ditutup.
2. **Status SO hanya disinkronkan untuk SO yang terhubung ke DO lewat pivot `delivery_sales_orders`** — sama seperti perilaku lama (pembuatan invoice per-DO juga bergantung pada pivot). Cache `delivered_quantity` tetap dihitung dari item DO. Di data dev **hanya 5 dari 25 DO punya baris pivot**; DO tanpa pivot tidak menggeser status SO maupun menerbitkan invoice (sudah begitu sebelum Fase 2). Layak diaudit.
3. **Item SO tanpa riwayat DO sama sekali memakai cache `delivered_quantity`** sebagai sumber (data hasil impor legacy mengisinya tanpa DO). Tanpa aturan ini SO legacy akan dianggap belum terkirim dan bisa dikirim ulang seluruhnya. *Batasan lama yang tetap ada:* begitu DO pertama untuk item legacy dikirim, cache dihitung dari DO saja (bagian legacy tertimpa) — sama seperti perilaku sebelum Fase 2.
4. **Accessor sementara `SaleOrderItem::getDeliveredQuantityAttribute()` (perubahan Anda yang belum di-commit) dihapus** dan digantikan service di atas, sesuai temuan §0.2(c) (hanya menghitung saat kolom 0, N+1).

**Bug laten yang ikut terbetulkan:**
- `ViewDeliveryOrder` (aksi checker) menjalankan `increment('remaining_quantity')` pada atribut yang **bukan kolom** → error SQL.
- Relation manager Driver & Vehicle memakai `match` **tanpa `default`** untuk status DO → `UnhandledMatchError` pada status seperti `request_stock`/`sent`.
- Guard lama `DeliveryOrderItem::saving` membandingkan `id != NULL` saat item baru dibuat sehingga **mengabaikan seluruh pengiriman sebelumnya** pada saat create.
- Aksi "Selesaikan"/dropdown SO tidak mengenal SO `Dikirim Sebagian`; halaman Create DO **mengizinkan SO `completed`** dan menolak `partially_delivered`.

**Pengujian:**
- Baru: `tests/Feature/SaleOrderDeliveryProgressPhase2Test.php` — **25 tes lulus** (progres, guard alokasi ganda, closed/gagal/hapus, SO tanpa DO, DO tanpa pivot, ringkasan halaman SO, validator status/sisa/customer/alamat, scope, label, halaman Create DO server-side, regresi penerimaan, backfill, data legacy). Pemeriksaan mutasi: mematikan synchronizer menggagalkan 6 tes; melonggarkan guard menggagalkan tes alokasi ganda.
- Regresi (36 berkas pengiriman/penerimaan/alur penjualan): baseline **18 gagal** → setelah Fase 2 **himpunan gagal identik** (satu tes `CustomerReceiptFeatureTest` sesekali gagal karena `CabangFactory` menghasilkan `kode` acak yang bertabrakan dengan seeder — *flaky*, terbukti dengan pengulangan).
- Set tambahan 36 berkas yang menyentuh resource terkait: dibandingkan dengan salinan **`HEAD` bersih**; satu-satunya selisih berasal dari Fase 1 (`IndonesianMoneyValidationTest` masih memindai `ViewQuotation.php`) dan sudah diperbaiki. Dua kegagalan tersisa di berkas itu sudah gagal di `HEAD`.
- Tes lama yang mengunci label diperbarui: `SaleOrderLivewireTest` (`Qty Delivered` → `Qty Terkirim` + `Dalam Proses DO`).

**Sebelum Fase 2 dianggap selesai di lingkungan Anda:**
1. Pastikan migrasi enum `partially_delivered` sudah berjalan (`SHOW COLUMNS FROM sale_orders LIKE 'status'`) — tanpa itu penyelesaian DO akan gagal. **Fase 2 tidak menambah migrasi baru.**
2. `php artisan sales:resync-so-status` (dry-run) → tinjau → `--apply`. Data dev: 5 SO punya DO, semuanya sudah konsisten.
3. UAT ulang skenario SO 20 pcs → DO 12 → DO 8.

**Belum ditangani (dijadwalkan di fase lain):** `SaleOrderApiController::update()` masih membuat ulang seluruh item SO dan tak dijaga status (T1, Fase 3); widget `SoBelumSelesaiTable` masih menampilkan SO `draft/canceled/closed` sebagai "belum selesai"; daftar invoice penerimaan masih disaring dari status SO, bukan AR (Fase 5A).

---

## Status pelaksanaan — Fase 3 (Isu 4 + T1), 20 September 2026

**Selesai (kode + tes):** langkah 4.1–4.6 dan penutupan celah API (T1) untuk Quotation **dan** Sales Order.

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 4.1 Kunci berbasis status | Hanya `draft` dan `reject` yang boleh diubah/dihapus (`Quotation::EDITABLE_STATUSES`). Dikunci di **lima lapis**: `QuotationPolicy::update/delete`, tombol tabel & header (`->visible`), hapus massal (melewati yang terkunci + pemberitahuan), `EditQuotation::mount()` (pemberitahuan ramah + redirect ke halaman Lihat, bukan 403 kosong), dan API `update()` (422) | `Quotation.php`, `QuotationPolicy.php`, `QuotationResource.php`, `ViewQuotation.php`, `EditQuotation.php`, `QuotationApiController.php` |
| 4.2 / T1 Celah API | `header.status` hanya `draft` atau `request_approve`; simpan **selalu** `draft` lalu pengajuan lewat `QuotationService::requestApprove()` (pencatat pengaju + waktu terisi). `store`/`update` kini memeriksa izin (`create`/`update`/`request-approve`) → 403. Quotation Ditolak yang diperbaiki kembali menjadi Draft. Sama untuk `SaleOrderApiController`: `approved` dari klien ditolak, `update` hanya untuk SO `draft`/`request_approve` (422), izin `create`/`update`/`request` diperiksa | `QuotationApiController.php`, `SaleOrderApiController.php` |
| 4.3 Revisi | Aksi **Buat Revisi** (tabel & halaman Lihat; izin `revise` = quotation Approved/Kedaluwarsa yang belum digantikan + izin *create quotation*). Menyalin header + item menjadi Draft baru `<nomor-dasar>-R{n}` (nomor tidak dipakai ulang walau revisi dihapus), berlaku 30 hari. Versi lama **tetap berlaku sampai revisi disetujui**, lalu ditandai `superseded_at` (hook `updated`) sehingga tak dapat dijadikan SO/direvisi lagi. Satu revisi terbuka per quotation | `QuotationService::createRevision`, `Quotation.php`, `QuotationResource::reviseAction` |
| 4.4 Kedaluwarsa (D2) | Status `expired` (label **Kedaluwarsa**) + job harian `quotations:expire` (00:10) **dan** guard tanggal di server: `Quotation::unusableReasonForSaleOrder()` / scope `usable()` dipakai oleh tombol & handler "Buat Sales Order", `SaleOrderResource` + `SalesRelationManager` (pilihan quotation), `SaleOrderApiController::dependencies/getQuotation/store/update`. `valid_until` **hari ini masih berlaku**. `approve()` menolak quotation yang sudah lewat masa berlaku | `Quotation.php`, `QuotationService.php`, `SalesOrderService::assertQuotationUsable`, `ExpireQuotations.php` |
| 4.5 `created_by` | Hook `creating` di model mengisi `Auth::id()` pada **semua** jalur (Filament, API, seeder, impor); nilai eksplisit tidak ditimpa. Kolom/infolist menampilkan **"Legacy / tidak tercatat"** bila kosong. `quotations:backfill-creator` (dry-run default, `--apply` + CSV) mengisi dari `activity_log` "Quotation dibuat." | `Quotation.php`, `BackfillQuotationCreator.php` |
| 4.6 Backfill kedaluwarsa | `quotations:expire --dry-run` (data dev: 5 quotation Approved sudah lewat masa berlaku — `QT-20260706-7167`, `-8694`, `-7525`, `-5164`, `-7594`) | `ExpireQuotations.php` |
| D10 | SO dari quotation kini **Draft** di kedua jalur (tabel & Lihat), menunggu persetujuan Manajer Sales. Perilaku lama aksi tabel (langsung Approved) tersedia lewat flag `SALES_SO_FROM_QUOTATION_AUTO_APPROVE=true` (`config/sales.php`) | `config/sales.php`, `QuotationResource::runCreateSaleOrderAction` |

**Keputusan desain (perlu Anda ketahui):**
1. **D10 belum Anda putuskan** — bukti di kode saling bertentangan (komentar aksi tabel: sengaja auto-approve; `ViewQuotation`: butuh persetujuan; `SalesWorkflowTest` mensimulasikan `request_approve`). Saya memakai rekomendasi (Draft) di balik flag; ubah `.env` bila bisnis memang ingin auto-approve.
2. **Persetujuan quotation tidak melewati `ApprovalControlService`** (tingkat nominal, larangan menyetujui milik sendiri) — tidak ada sebelumnya, tidak ditambahkan. Ini menjadi celah kontrol tersendiri bila diperlukan (fase terpisah).
3. **Status `request_approve` juga terkunci** (bukan hanya `approve`): quotation yang sedang direview tidak boleh berubah diam-diam di bawah mata penyetuju.
4. **Job kedaluwarsa didaftarkan di `routes/console.php`, bukan `app/Console/Kernel.php`.** Rencana awal (§4.4) menyebut pola `Kernel.php`, tetapi di Laravel 12 ini `bootstrap/app.php` tidak memakai `Kernel::schedule()` — `php artisan schedule:list` hanya menampilkan `asset:depreciate`. Lihat temuan di bawah.
5. Hapus massal memakai aksi kustom (Filament 3.3 tidak punya `authorizeIndividualRecords`); quotation terkunci dilewati dengan pemberitahuan, bukan gagal seluruhnya.

**⚠ Temuan penting di luar lingkup (belum diubah):** `invoices:check-overdue` didaftarkan di `app/Console/Kernel.php:33`, tetapi **Kernel itu tidak aktif** — `schedule:list` tidak memuatnya. Artinya invoice yang lewat jatuh tempo **tidak otomatis menjadi Overdue** kecuali command dijalankan manual/cron eksternal. Perbaikannya satu baris di `routes/console.php`; saya tidak menyentuhnya karena berada di luar Isu 4 — mohon konfirmasi bila ingin ikut dipindahkan.

**Bug laten yang ikut terbetulkan:**
- API quotation menerima `header.status = approve` dari siapa pun yang berizin *update* — persetujuan tanpa izin *approve* (T1).
- `SaleOrderApiController::update()` membuat ulang seluruh item SO pada SO berstatus apa pun (termasuk sudah dikirim) — kini dikunci ke `draft`/`request_approve`.
- Dropdown quotation SO memuat quotation kedaluwarsa dan `store()` hanya memvalidasi `exists`.
- React `SaleOrderApp` menelan galat saat memilih quotation; kini menampilkan pesan API dan mengosongkan pilihan.

**Pengujian:**
- Baru: `tests/Feature/QuotationLifecyclePhase3Test.php` — **40 tes lulus** (policy per status, halaman Edit/Lihat, tombol tabel, hapus massal, batas kedaluwarsa hari-ini/kemarin, scope `usable`, `quotations:expire` dry-run/apply + terdaftar di scheduler, guard SO di tombol/handler/API, T1 quotation & SO, revisi -R1/-R2/revisi terbuka/penggantian, `created_by` + backfill). Pemeriksaan mutasi: `isEditable()=true` menggagalkan 10 tes; menghapus guard kedaluwarsa 3 tes; menghapus guard API SO 1 tes; menghapus hook penggantian 1 tes.
- Fase 1 diperbarui: `QuotationToSaleOrderPhase1Test` — SO tabel kini Draft (+ 1 tes flag auto-approve), izin `update sales order` untuk tes API. **20 tes lulus.** `SaleOrderDeliveryProgressPhase2Test` tetap lulus (25).
- Regresi (56 berkas terkait): **37 gagal, seluruhnya ada di baseline** (dibandingkan per nama tes dengan baseline Fase 2 dan Fase 3; himpunan gagal Fase 3 = 21 identik). Tidak ada kegagalan baru. Catatan: beberapa tes API lama (`QuotationApiTest`/`SaleOrderApiTest` create/update) sudah gagal di baseline karena kesalahan fixture, sehingga perubahan API dijaga oleh berkas tes baru, bukan oleh tes lama.

**Sebelum Fase 3 dianggap selesai di lingkungan Anda:**
1. `php artisan migrate` — menambah enum `expired` (MySQL) serta kolom `revision_of_id`, `revision_no`, `superseded_at`, `expired_at` (migrasi `2026_09_20_100000_add_lifecycle_columns_to_quotations_table.php`, dapat di-rollback).
2. `npm run build` (React `SaleOrderApp` berubah; sudah dibangun di mesin ini).
3. `php artisan quotations:expire --dry-run` → tinjau → `php artisan quotations:expire`.
4. `php artisan quotations:backfill-creator` (dry-run) → tinjau → `--apply`. Di dev: 24 dari 29 quotation `created_by` NULL; yang tidak ada di `activity_log` tetap kosong dan tampil "Legacy / tidak tercatat".
5. Pastikan penjadwal berjalan di server (`* * * * * php artisan schedule:run`), atau job kedaluwarsa tidak akan berjalan (guard tanggal tetap melindungi).
6. UAT ulang: quotation Approved → tombol Ubah/Hapus hilang, URL `/edit` langsung dialihkan; Buat Revisi → `-R1` Draft → ajukan → setujui → versi lama tak lagi bisa dijadikan SO.

**Belum ditangani (dijadwalkan di fase lain):** widget `SoBelumSelesaiTable` masih menampilkan SO `draft/canceled/closed` sebagai "belum selesai" (Fase 6); daftar invoice penerimaan masih disaring dari status SO, bukan AR (Fase 5A); scheduler `invoices:check-overdue` tidak aktif (temuan di atas).

---

## Status pelaksanaan — Fase 4 (Isu 6 & 8), 20 September 2026

**Selesai (kode + tes):** langkah 6.1–6.6 dan 8.1–8.6.

### Isu 6 — Surat Jalan layak cetak

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 6.1 PDF tidak lagi 500 | Relasi yang salah (`deliveryOrder.customer`) diganti daftar relasi tunggal `SuratJalanDocumentBuilder::RELATIONS`. Diuji dengan SJ ber-DO tunggal **dan** jamak lewat rute PDF sungguhan (sebelumnya tak pernah diuji karena data lokal tak punya SJ ber-DO) | `PdfPreviewController.php`, `SuratJalanDocumentBuilder.php` |
| 6.2 Template baru | Kop dari **data cabang penerbit** (alamat/telepon kosong → dikosongkan, bukan placeholder "Jl. Contoh"); No. SJ, tanggal berbahasa Indonesia (`translatedFormat`), No. DO & No. SO, customer unik + alamat kirim, **driver + plat** atau **ekspedisi + resi + No. Jadwal**, barang **per DO** (No, SKU, Nama, Qty, Satuan, Keterangan; tidak digabung lintas DO), **tanpa harga/diskon/pajak/subtotal** (D3), empat blok tanda tangan (Yang Menyerahkan/Gudang, Driver, Penerima + nama jelas/tanggal/cap, Mengetahui), banner **DIBATALKAN**/**DRAFT**. Data disusun sekali di builder → template hanya menampilkan | `resources/views/pdf/surat-jalan.blade.php`, `SuratJalanDocumentBuilder.php` |
| 6.3 Halaman Lihat & daftar | Halaman Lihat ditulis ulang (status badge, pengiriman dari jadwal, daftar DO + SO + customer, bagian Pembatalan); daftar memakai badge status, warna baris menurut status (abu/biru/merah — sebelumnya memakai atribut `terbit` yang tidak ada), kolom Driver/Kendaraan dari **jadwal** (sebelumnya dari DO yang tak pernah terisi), eager-load tanpa N+1, filter status yang benar untuk Draft (`0`) | `SuratJalanResource.php`, `ViewSuratJalan.php` |
| 6.4 Siklus & kunci | `status` 0 = Draft, 1 = **Terbit**, 2 = **Dibatalkan**. Terbit terkunci di Policy, tombol tabel, halaman Lihat, hapus massal, dan halaman Edit (pemberitahuan + redirect). Aksi baru: **Terbitkan** (Draft), **Batalkan** (alasan wajib ≥ 5 karakter; tercatat oleh/waktu/alasan), **Terbitkan Ulang**, **Unggah Dokumen Bertanda Tangan** (satu-satunya perubahan pada SJ terbit). Migrasi `cancelled_at/by`, `cancel_reason` | `SuratJalan.php`, `SuratJalanPolicy.php`, `SuratJalanService.php`, migrasi `2026_09_20_130000_*` |
| 6.5 Nomor berurutan | `SJ-YYYYMMDD-0001` lewat `SequentialNumberGenerator` (melanjutkan dari nomor terbesar, sehingga nomor acak lama tidak bertabrakan); nomor otomatis terisi di form buat | `SuratJalanService::generateCode` |
| 6.6 Belum dijadwalkan (D3b-A) | Cetak boleh sebelum ada jadwal; driver/kendaraan tertulis **"Belum dijadwalkan"** (PDF, daftar, halaman Lihat) | builder + resource |

**Keputusan desain (perlu Anda ketahui):**
1. **Satu DO tidak boleh tercantum di dua Surat Jalan yang masih berlaku (Draft/Terbit).** Tanpa aturan ini "Batalkan lalu terbitkan ulang" tidak berarti (SJ ganda untuk DO yang sama tetap bisa dibuat). Berlaku di form Buat (pilihan DO), Terbitkan, dan Terbitkan Ulang. DO yang SJ-nya dibatalkan otomatis bebas lagi.
2. **Batalkan ditolak selama SJ masih dipakai Jadwal Pengiriman** berstatus menunggu/berjalan/sebagian/selesai; pesan menyebut nomor jadwalnya. Keluarkan dari jadwal (atau batalkan/gagalkan jadwal) lebih dulu.
3. **Tidak menambah izin baru:** Batalkan/Terbitkan/Unggah memakai izin `update surat jalan`; Terbitkan Ulang memakai `create surat jalan`. Bila ingin izin `cancel surat jalan` terpisah, perlu penambahan di seeder izin + penugasan peran.
4. **Data lama:** 9 dari 10 SJ di data dev berstatus `0` (bukan terbit). Sekarang mereka tampil **Draft** (abu-abu), dapat diubah/dihapus/diterbitkan; 1 SJ terbit terkunci. Tak ada satu pun SJ yang tertaut ke DO di data dev.
5. Kop memakai nama perusahaan tetap "PT DUTA TUNGGAL" + alamat/telepon **cabang**; email dari `AppSetting company_email` (fallback `admin@dutatunggal.co.id`, sama seperti template lain).
6. Pratinjau PDF tersedia untuk semua status (Draft/Dibatalkan diberi banner) — sebelumnya hanya `status == 1`.

### Isu 8 — Jadwal Pengiriman dengan master kosong

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 8.1 Empty-state | Banner di form Jadwal bila master driver dan/atau kendaraan kosong (menurut cakupan cabang pengguna): menjelaskan penyebab, menyebut alternatif Ekspedisi, dan bahwa **DO baru berstatus Dikirim/Selesai setelah dijadwalkan**. Tautan tambah hanya bila punya izin `create driver`/`create vehicle`; bila tidak: "hubungi admin master data" | `DeliveryScheduleResource::fleetMasterNotice` |
| 8.2 Tambah cepat | `createOptionForm` pada pilihan Driver dan Kendaraan (hanya bila berizin) | `DeliveryScheduleResource` |
| 8.3 Default & resi | Default metode = **Ekspedisi** bila master armada kosong, selain itu Internal; helper text menjelaskan kapan memakai yang mana; kolom baru **Nomor Resi** dipisah dari `vehicle_info` (`delivery_schedules.tracking_number`), tampil di form, halaman Lihat, dan PDF Surat Jalan. Kolom Driver/Kendaraan di tabel jadwal kini menampilkan nama ekspedisi/info kendaraan (sebelumnya "-") dan dapat dicari termasuk resi | `DeliveryScheduleService::defaultDeliveryMethod`, migrasi `2026_09_20_130100_*` |
| 8.4 Validasi server | `DeliveryScheduleService::validateSender`: internal → driver **dan** kendaraan harus ada di master dan belum dihapus; ekspedisi → nama ekspedisi wajib. Dipanggil di halaman Buat dan Ubah (tidak hanya `required` di UI); aturan `exists` (tanpa soft-delete) pada pilihan | `CreateDeliverySchedule.php`, `EditDeliverySchedule.php` |
| 8.5 Checklist kesiapan | `php artisan master:readiness [--cabang=ID] [--json]` (hanya membaca): gudang, rak, driver, kendaraan, akun kas/bank, IDR, PPN aktif. Status **siap / sebagian** (ada, tetapi cabang tertentu belum — dirinci) **/ kosong**; exit code 1 hanya bila master **kritis** (gudang, akun kas/bank, IDR, PPN) kosong total | `MasterDataReadiness.php`, `MasterReadinessCommand.php` |
| 8.6 Status parsial | `partial_delivered` (sudah ada di enum tetapi tak bisa dipilih) kini tersedia di form, filter, tabel, halaman Lihat dengan label **Sebagian Terkirim**; DO ikut `sent` (barang sudah keluar) dan **tidak** `completed`. Seluruh peta label status jadwal dipusatkan di `DeliverySchedule::STATUS_LABELS/COLORS` (sebelumnya 5 salinan yang tidak konsisten) | `DeliverySchedule.php`, `DeliveryScheduleObserver.php` |

**Temuan penting dari data dev:** master driver dan kendaraan **memang kosong** — ada 33 driver + 33 kendaraan tetapi **semuanya soft-deleted** (`deleted_at` terisi), itulah kondisi "master kosong tapi wajib" pada UAT. Saya tidak memulihkannya (keputusan Anda). Hasil `master:readiness` di dev: gudang *sebagian* (3 cabang belum punya), driver/kendaraan *kosong*, akun kas/bank (24), IDR, PPN siap.

**Yang sengaja tidak diubah:** `DeliveryScheduleService::completeRelatedDeliveryOrders()` masih menyelesaikan **semua** DO terkait sekaligus saat jadwal `delivered` (tidak ada penyelesaian parsial per DO); PDF **Delivery Order** (`pdf/delivery-order.blade.php`) masih memuat alamat placeholder "Jl. Contoh No. 123" seperti SJ lama — di luar Isu 6, namun layak dirapikan dengan pola yang sama.

**Pengujian:**
- Baru: `tests/Feature/SuratJalanScheduleReadinessPhase4Test.php` — **38 tes lulus** (PDF tunggal & jamak lewat rute sungguhan, isi data cetak, tanpa harga, ekspedisi/resi, belum dijadwalkan, banner batal, policy per status, redirect halaman Edit, tombol tabel & halaman Lihat, hapus massal hanya Draft, Batalkan (alasan/dua kali/jadwal aktif), Terbitkan & Terbitkan Ulang, satu DO satu SJ berlaku, nomor berurutan, halaman Lihat & daftar, rekap, validasi pengirim server, empty-state & izin tautan, jadwal ekspedisi tanpa driver, status parsial, `master:readiness` + JSON + status sebagian). Pemeriksaan mutasi: `isEditable()=true` menggagalkan 5 tes; menonaktifkan pemeriksaan jadwal aktif 1; DO ganda 3; validasi pengirim 1; alasan tak wajib 1.
- Diperbarui: `SalesWorkflowAuditTest` "surat jalan PDF includes all columns" — sebelumnya **mengunci** kolom Harga/Diskon/Pajak/Subtotal pada SJ; kini mengunci SKU/Nama/Qty/Satuan/Keterangan dan **memastikan tak ada kolom harga**.
- Regresi (68 berkas terkait + 4 berkas fase): dibandingkan per nama tes dengan baseline sebelum Fase 4 (9 gagal di berkas terkait) — **tak ada kegagalan baru**. Satu tes (`DataMasterCrudTest › it can create multiple currencies`) gagal hanya bila berkas `tests/Feature/Api/*` (tanpa `RefreshDatabase`) berjalan lebih dulu dalam satu proses: **kebocoran data antar tes yang sudah ada**, bukan dari Fase 4 (lulus sendiri 4/4).
- Render PDF diperiksa visual (SJ ber-2 DO dengan driver internal; SJ dibatalkan tanpa jadwal).

**Sebelum Fase 4 dianggap selesai di lingkungan Anda:**
1. `php artisan migrate` (dua migrasi baru: kolom pembatalan SJ dan `tracking_number`). Tanpa ini halaman Surat Jalan/Jadwal akan error karena kolom belum ada.
2. `php artisan master:readiness` → lengkapi/pulihkan master driver & kendaraan atau gunakan Ekspedisi.
3. UAT: buat SJ dari DO approved → cetak PDF (cek driver/plat setelah dijadwalkan, tanpa harga, tanda tangan) → coba Ubah/Hapus (tersembunyi) → Batalkan (alasan) → Terbitkan Ulang; buka Jadwal dengan master kosong (banner + default Ekspedisi + nomor resi).

---

## Status pelaksanaan — Fase 5A (Isu 7: kontrol penerimaan uang customer), 20 September 2026

**Selesai (kode + tes):** langkah 7.1–7.6, memakai rekomendasi **D4 = B** (tolak; opsi eksplisit → Deposit Customer), **D5 = A** (kolom Penyesuaian Sisa disembunyikan), **D8 = B** (flag `is_cash_bank`, kandidat sebagai jembatan), **D9 = A** (blokir invoice lintas cabang).

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 7.1 Cabang = cabang invoice | Pengisian cabang dari customer dihapus. Kolom Cabang (untuk user "all") kini **otomatis dan tidak dapat diubah manual**: mengikuti cabang invoice terpilih; bila invoice berasal dari beberapa cabang → **ditolak** (pesan menyebut nomor invoice per cabang). User non-"all" hanya dapat menerima invoice cabangnya sendiri; `CabangScope` pada daftar invoice **diaktifkan kembali** (dahulu dilepas sehingga invoice cabang lain ikut muncul) | `CustomerReceiptResource.php`, `CustomerReceiptAllocator.php` |
| 7.2 Akun kas/bank | Migrasi `chart_of_accounts.is_cash_bank`; satu definisi akun penerima `CustomerReceiptAccounts` (form **dan** validasi server): akun induk `1110/1111/1112`, `DEPOSITO`, `INVESTASI` tidak pernah muncul. Bila sudah ada akun bertanda → **hanya** yang bertanda; bila belum ada satu pun → kandidat sementara agar penerimaan tidak terblokir. **Default hanya bila kandidat tepat satu** (dahulu selalu jatuh ke `1110`); selain itu dikosongkan dan wajib dipilih. Server menolak akun di luar daftar. Command `coa:flag-cash-bank` (dry-run default, `--apply`, `--codes=`, `--reset`, CSV sebelum/sesudah) | `ChartOfAccount.php`, `CustomerReceiptAccounts.php`, `FlagCashBankAccounts.php`, migrasi `2026_09_20_150000_*` |
| 7.3 Kelebihan bayar | **Tidak ada lagi pemotongan senyap** (notifikasi Inggris generik dan `alert()` JS dihapus). Nominal > sisa tagihan **ditolak** pada field Total Pembayaran: *"Nominal Rp 130.000,00 melebihi sisa tagihan Rp 100.000,00 untuk invoice … (kelebihan Rp 30.000,00)"*. Opsi baru **"Catat kelebihan sebagai Deposit Customer"**: nominal teralokasi = sisa tagihan, kelebihan menjadi `Deposit` aktif dengan jurnal **Dr Kas/Bank (akun penerimaan) / Cr Deposit Pelanggan (2160.04)** di **cabang invoice**, dalam transaksi yang sama — bila gagal, seluruh penerimaan dibatalkan (tidak ada uang tanpa jurnal). Kolom `customer_receipts.overpayment_amount` dan `deposit_id` menautkannya; halaman Lihat menampilkannya. JS kini hanya menampilkan catatan merah di bawah kolom | `CustomerReceiptAllocator.php`, `CreateCustomerReceipt.php`, `LedgerPostingService::postDeposit` (parameter cabang), blade + JS |
| 7.4 Penyesuaian Sisa | Kolom **Penyesuaian Sisa** dan **Keterangan Penyesuaian** dihapus dari tabel invoice; pemuatan jQuery/Select2 dari CDN (hanya untuk kolom itu) ikut dihapus | `customer-receipt-invoice-table.blade.php` |
| 7.5 Daftar invoice dari AR | Invoice muncul bila `account_receivables.remaining > 0` (bukan dari status SO); invoice lunas hilang; pada mode ubah invoice yang sudah dipilih tetap tampil. SO `partially_delivered` tetap tercakup (regresi Fase 2 dijaga) | `CustomerReceiptResource::invoiceableInvoicesQuery` |
| 7.6 Teks bantuan | Teks panduan/helper diselaraskan dengan perilaku nyata (kelebihan ditolak atau menjadi deposit) | resource |

**Tambahan yang saya putuskan (perlu Anda ketahui):**
1. **"Auto-pilih invoice pertama" dihapus.** Bila tidak ada invoice terpilih tetapi total > 0, kode lama diam-diam mengalokasikan uang ke invoice pertama customer. Kini ditolak: *"Pilih minimal satu invoice…"*.
2. **Mode Ubah memakai aturan yang sama** (akun sah, cabang, nominal tidak melebihi sisa + nominal penerimaan itu sendiri). Kelebihan bayar **tidak dapat dicatat lewat Ubah** (buat penerimaan baru). Penerimaan lama yang memakai akun induk harus memilih akun sah saat diubah.
3. **Deposit dari kelebihan hanya untuk mata uang IDR** (deposit tidak menyimpan mata uang) dan tidak untuk metode Deposit; alasan penolakan ditampilkan.
4. **Kandidat akun dikenali dari kode, bukan hanya `parent_id`:** di data dev `1112.01 "Bank BCA - Operasional"` punya anak `1112.01.01` tetapi `parent_id` keduanya sama-sama `1112`. Akun detail = tanpa anak menurut `parent_id` **dan** tanpa akun berkode `<kode>.*`.

**⚠ Perlu tinjauan akuntansi sebelum `--apply`** (`php artisan coa:flag-cash-bank` di data dev mengusulkan 8 akun):
`1111.01 Kas Besar Kantor`, `1111.02 KAS KECIL`, `1111.03 KAS PENJUALAN`, `1112.01.01 BANK BCA - OPERASIONAL`, `1112.02.01 BANK MANDIRI - OPERASIONAL`, `1112.03.01 BANK PANIN (IDR) - 2197 - OPERASIONAL`, `1112.04.01 BANK PANIN (IDR) - 2297 - OPERASIONAL`, `1112.05.01 BANK PANIN (USD) - OPERASIONAL`.
Dikecualikan otomatis: `1110`, `1111`, `1112`, `1112.xx` (induk berkode), semua `DEPOSITO`/`INVESTASI`, dan `1113`. Pertanyaan untuk akuntansi: apakah akun **USD** (`1112.05.01`) boleh menerima pembayaran customer, dan apakah rekening yang hanya ber-`1112.xx` tanpa anak harus ikut. Gunakan `--codes=…` untuk daftar yang disetujui.

**Temuan di luar lingkup (belum diubah):** pemilihan akun kas/bank di modul lain masih memakai heuristik lemah yang sama — `VendorPaymentResource` (nama mengandung kas/bank), `BankReconciliationResource` (`111%`), `CashBankTransactionResource`/`CashBankTransferResource` (hanya prefix), dan `DepositResource` (fallback `where code LIKE '111%' ->first()` dapat memilih **akun induk 1110**). Semuanya dapat memakai `ChartOfAccount::cashBank()` agar konsisten — disarankan sebagai langkah lanjutan.

**Bug laten yang ikut terbetulkan:** kelebihan uang tidak masuk ke mana pun dan `total_payment` dihitung ulang dari nominal yang sudah dipotong (uang hilang dari pencatatan); pembagian proporsional multi-invoice yang membuang sisa; penerimaan dapat dibuat untuk invoice customer lain / invoice lunas; cabang penerimaan mengikuti invoice *terakhir* saja; JS memotong nominal dengan `alert()` sehingga user tidak sempat mengoreksi.

**Pengujian:**
- Baru: `tests/Feature/CustomerReceiptControlsPhase5ATest.php` — **30 tes lulus** (daftar akun tanpa induk/deposito/investasi, default hanya bila satu, flag menjadi filter, command dry-run/apply/codes/reset/CSV, daftar invoice dari AR + mode ubah, cakupan cabang non-"all", allocator [pesan kelebihan, opsi deposit, customer lain, lunas, tanpa pilihan, metode Deposit, satu cabang, non-"all", mode ubah, akun sah], halaman Buat [penerimaan pas → cabang invoice, kelebihan ditolak, kelebihan → deposit + jurnal + cabang, akun Deposit Pelanggan hilang, dua cabang, akun induk/deposito/investasi, tanpa invoice, field cabang tak dapat diubah, opsi deposit tampil/tersembunyi], halaman Ubah, tabel invoice tanpa Penyesuaian/Select2/`alert()`, halaman Lihat, checklist kesiapan). Pemeriksaan mutasi: kelebihan tak ditolak → 3 tes gagal; daftar bukan AR>0 → 1; cabang dari customer → 3; DEPOSITO tak dikecualikan → 3; lintas cabang diizinkan → 2.
- Diperbarui (mengunci perilaku lama yang keliru): `CustomerReceiptResourceTest` (cabang tidak lagi disalin dari customer) dan `SaleOrderDeliveryProgressPhase2Test` (daftar invoice kini dari AR: invoice lunas hilang, SO parsial tetap muncul).
- Regresi (84 berkas terkait + 5 berkas fase): dibandingkan per nama tes dengan seluruh baseline sebelumnya — **tak ada kegagalan baru** kecuali `CustomerReceiptFeatureTest` (tes yang gagal berganti-ganti antar-jalan; penyebabnya `CabangFactory` menghasilkan `kode` acak yang bertabrakan — *flaky* yang sama dengan yang dicatat pada Fase 2, terbukti dari pesan `Duplicate entry 'CBG-749'`).
- Belum diuji di browser: dev DB belum dimigrasi; tampilan form (banner kelebihan, kolom cabang otomatis) perlu UAT visual.

**Sebelum Fase 5A dianggap selesai di lingkungan Anda:**
1. `php artisan migrate` (sekarang tiga migrasi menunggu: dua dari Fase 4 dan satu dari Fase 5A).
2. `php artisan coa:flag-cash-bank` (dry-run) → **serahkan daftar ke akuntansi** → `--apply` atau `--codes=…`. Sampai itu dilakukan penerimaan tetap berjalan memakai kandidat sementara; `master:readiness` menandainya *sebagian*.
3. UAT: pilih customer (cabang tidak berubah) → pilih invoice (cabang otomatis mengikuti invoice) → COA tidak menampilkan `1110/1111/1112`/Deposito/Investasi dan tidak terpilih otomatis bila ada lebih dari satu → isi nominal melebihi sisa (catatan merah, tanpa alert) → simpan: ditolak; nyalakan opsi deposit → simpan: penerimaan + Deposit + jurnal; campur invoice dua cabang → ditolak; kolom Penyesuaian Sisa tidak ada.

---

## Status pelaksanaan — Fase 5B (Isu 10: transparansi invoice), 20 September 2026

**Selesai (kode + tes):** langkah 10.1–10.5, memakai rekomendasi **D6 = A (2 desimal di semua tempat)**.

> ⚠ **Gerbang keluar Fase 5B: persetujuan akuntansi atas kebijakan pembulatan PPN.** Perubahan ini memengaruhi PPN yang diposting pada invoice **baru** (invoice yang sudah terbit tidak dihitung ulang). Kebijakan disimpan di satu tempat dan dapat dikembalikan **tanpa mengubah kode**: `SALES_LINE_ROUNDING_DECIMALS=0` di `.env` = rupiah bulat (perilaku lama `TaxService`).

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 10.1 Satu perhitungan | `LineAmounts::calculate(qty, harga, diskon%, tarif, tipe)` → `gross, discount_amount, dpp, ppn, total`. DPP dibulatkan **lebih dulu**, PPN dihitung dari DPP → `DPP + PPN = Total` tepat dan jurnal selalu seimbang. Dipakai oleh `HelperController::hitungSubtotal` (SO/Quotation), pratinjau Quotation, invoice otomatis dari SO **dan** dari DO, form invoice Filament, PDF SO/Quotation/Invoice; React (`calculations.ts` SO & Quotation) diselaraskan dan aset dibangun ulang. Contoh UAT = **Jumlah 173.740 · Diskon 8.687 · DPP 165.053 · PPN 18.155,83 · Total 183.208,83** di semua jalur | `app/Support/LineAmounts.php`, `config/sales.php` |
| 10.2 `price` baku = gross | Migrasi `invoice_items.gross_amount`, `discount_amount`. Semua jalur pembuatan invoice penjualan menyimpan **harga satuan gross**, diskon %, jumlah kotor, diskon Rp, DPP (`subtotal`), PPN (`tax_rate`/`tax_amount`), total — lewat `SalesInvoiceLineBuilder`. Jalur Delivery Order yang dulu menyimpan `price` **net** kini gross | `SalesInvoiceLineBuilder.php`, `SaleOrderObserver.php`, `DeliveryOrderObserver.php`, `CreateSalesInvoice`/`EditSalesInvoice` |
| 10.3 Tampilan | **Halaman Lihat**: setiap baris menampilkan Harga Satuan · Qty · Jumlah (Harga × Qty) · Diskon (% = Rp) · DPP · PPN (% = Rp) · Total Baris. **PDF invoice**: 12 kolom (No, SKU, Produk, Qty, Harga Satuan, Jumlah, Diskon %, Diskon Rp, DPP, PPN %, PPN Rp, Total) dan footer Jumlah · Diskon · Subtotal DPP · PPN · Biaya Lain · TOTAL (kertas **landscape**). Form Ubah menampilkan rincian baris (baca-saja) | `ViewSalesInvoice.php`, `sale-order-invoice.blade.php`, `SalesInvoiceResource.php` |
| 10.4 2 desimal konsisten | Format baku `LineAmounts::money()` ("Rp 183.208,83") dipakai layar invoice dan PDF invoice, Sales Order, Quotation — sebelumnya PDF `number_format(…, 0)` menyembunyikan sen dan layar IDR 0 desimal, sehingga total SO 183.208,83 tampil 183.209 | blade PDF + infolist |
| 10.5 Backfill | `invoices:backfill-line-breakdown` (dry-run default, `--apply`, CSV): mengisi **hanya** `gross_amount`/`discount_amount` yang masih NULL. Basis `price` lama ditebak dengan mencocokkan nilai baris (gross / **net** / tidak cocok); yang **net** dan yang tidak cocok **dilaporkan** (tidak cocok tidak diisi). `price`, `discount`, `subtotal`, `tax_amount`, `total` **tidak pernah diubah** | `BackfillInvoiceLineBreakdown.php` |

**Keputusan desain (perlu Anda ketahui):**
1. **Invoice lama tetap benar di layar tanpa backfill:** `InvoiceItem::breakdown()` menurunkan rincian dari `price/diskon/DPP/total` dengan tebakan basis yang sama, sehingga PDF invoice lama yang `price`-nya net tidak lagi tampak "diskon dua kali". Data dev (6 baris seeder, `subtotal` kosong) terdeteksi gross dan DPP diturunkan dari total − PPN.
2. **Invoice dari Delivery Order kini menghitung pajak per baris dari item SO-nya** (sebelumnya seluruh baris diperlakukan Eksklusif dengan tarif baris pertama). Akibatnya **SO Inklusif tidak lagi ditambah PPN kedua kali** pada invoice DO (total = nilai setelah diskon, DPP diekstrak).
3. **Form invoice manual (Filament):** tipe & tarif pajak mengikuti *header invoice* (form memang mengizinkan user mengubahnya dari nilai SO); item SO hanya memberi harga gross & diskon. Bila **semua** baris berpasangan dengan item SO, **header dihitung ulang dari baris** (Σ baris = header, tanpa selisih sen antara footer/PDF dan total). Baris tanpa pasangan (produk di luar SO) tetap memakai harga form dan tidak menghitung ulang header.
4. **Retur customer** memakai `InvoiceItem::net_unit_price` (harga setelah diskon) — sebelumnya `price`, yang bagi invoice jalur SO adalah harga **gross** sehingga retur invoice berdiskon dikreditkan terlalu besar. Data yang nilainya tak cocok memakai `price` apa adanya (perilaku lama).
5. **Sengaja tidak diubah:** `TaxService::compute()` (masih rupiah bulat) tetap dipakai modul **pembelian** (PO/Purchase Invoice) — kebijakan D6 hanya untuk penjualan; **PDF Delivery Order** dan **`SalesReportService`** masih memakai `TaxService::compute` (rupiah bulat) sehingga angkanya bisa berbeda beberapa sen dari invoice — laporan penjualan ditata ulang di **Fase 6**. Nominal PPN *tampilan* untuk tipe Inklusif pada form SO/Quotation tetap dihitung dari nilai setelah diskon (perilaku lama; totalnya benar).

**Perbedaan kecil yang perlu diketahui:** karena DPP dibulatkan lebih dulu, total SO/Quotation untuk harga ber-desimal panjang dapat berbeda ±Rp0,01 dari perhitungan lama (yang membulatkan PPN dari nilai belum bulat). SO/invoice yang sudah ada tidak dihitung ulang.

**Pengujian:**
- Baru: `tests/Feature/InvoiceLineBreakdownPhase5BTest.php` — **21 tes lulus** (contoh UAT pada `LineAmounts`, inklusif/non pajak, rollback pembulatan lewat config, `hitungSubtotal` = `LineAmounts`, format uang; invoice **jalur SO** dan **jalur DO** identik dan `total invoice = total SO = 183.208,83`, DO parsial, SO inklusif pada jalur DO, builder form (header pajak berubah, baris tak berpasangan), `breakdown()` untuk gross/net/tak cocok/DPP kosong, `net_unit_price`, backfill dry-run/apply/idempoten/tidak menyentuh nilai terposting, PDF invoice/SO/Quotation, halaman Lihat, PDF invoice lama, endpoint PDF, halaman Ubah: rincian tampil + simpan ulang tetap gross dan header = Σ baris). Halaman **Buat** invoice manual belum punya tes end-to-end (logika penyimpanannya sama dengan Ubah dan diuji pada builder). Pemeriksaan mutasi: PPN dibulatkan rupiah → 9 tes gagal; jalur DO kembali net → 2; basis net tak terdeteksi → 3; `gross_amount` tidak disimpan → 3; backfill mengubah `price` → 1.
- Diperbarui (mengunci format lama): `SalesWorkflowAuditTest` "invoice PDF includes all columns" — kini mengunci 12 kolom rincian baru dan format 2 desimal.
- Regresi (107 berkas terkait + 6 berkas fase): dibandingkan per nama tes dengan seluruh baseline — **tak ada kegagalan baru**. Kegagalan yang tampak "baru" pada `OrderRequestResourceTest` (18 tes, sisi pembelian) sudah ada di baseline dan hanya berbeda karena pemotongan nama.
- Belum diuji di browser; tampilan PDF landscape perlu UAT visual.

**Sebelum Fase 5B dianggap selesai di lingkungan Anda:**
1. **Persetujuan akuntansi atas pembulatan 2 desimal** (atau set `SALES_LINE_ROUNDING_DECIMALS=0` sementara).
2. `php artisan migrate` (migrasi `2026_09_20_170000_add_line_breakdown_to_invoice_items_table`; kode aman bila belum dijalankan — kolom baru dilewati).
3. `php artisan invoices:backfill-line-breakdown` (dry-run) → tinjau baris **net**/tak cocok → `--apply`.
4. UAT: SO 20 × 8.687, diskon 5%, PPN 11% → invoice (via DO dan via SO) → layar dan PDF memperlihatkan Jumlah 173.740,00 · Diskon 8.687,00 · DPP 165.053,00 · PPN 18.155,83 · Total 183.208,83; total invoice = total SO.

---

## Status pelaksanaan — Fase 6 (Isu 9: laporan penjualan), 20 September 2026

**Selesai (kode + tes):** langkah 9.1–9.7 dan widget "SO Belum Selesai", memakai rekomendasi **D7 = Invoice** sebagai basis default.

| Langkah | Hasil | Berkas utama |
|---|---|---|
| 9.1 Tiga mode | **Penjualan (Invoice)** — default, akrual, tanggal = `invoice_date`, hanya invoice bukan Draft; **Pengiriman** — tanggal = `delivery_date`, tanpa filter status hanya DO yang sudah keluar gudang (Dikirim/Diterima/Selesai); **Pesanan (SO)** — tanggal = `order_date` (sebelumnya `created_at`). Mengganti mode mereset filter status | `SalesReportService.php`, `SalesReportPage.php` |
| 9.2 Snapshot HPP | Migrasi `invoice_items.cost_price`, `cogs_amount`, `cogs_source`. `InvoiceObserver::postCostOfSalesEntries` menulis snapshot per baris dari **angka yang sama dengan jurnal** (satu perhitungan → dua keluaran; tanpa observer/log). Snapshot tidak ditulis bila jurnal berasal dari fallback item DO. Laporan **tidak lagi bergantung pada cost_price master yang berubah kemudian** | `InvoiceObserver.php`, `InvoiceItem.php` |
| 9.3 Kolom | Mode Invoice: No. Invoice, tanggal, customer, No. SO, DO, DPP, PPN, total, **HPP** (tanda `*` bila estimasi), **Margin (Rp)**, **Margin % = (DPP − HPP)/DPP**, **Status Pembayaran** (Belum Bayar / Dibayar Sebagian / Lunas / **Jatuh Tempo**, dari Account Receivable), cabang. Ringkasan periode di atas tabel (total DPP/PPN/HPP/margin, piutang belum dibayar, hitungan per status pembayaran) | page + service |
| 9.4 Status dari konstanta | Opsi status = `SaleOrder::STATUS_LABELS` / `DeliveryOrder::STATUS_LABELS` / `PAYMENT_STATUS_LABELS` — tidak ada daftar tulis-tangan. "Diproses" dan "Dibatalkan(cancelled)" yang fiktif hilang; "Disetujui", "Dikirim Sebagian", "Menunggu Persetujuan", "Ditutup", "Ditolak" kini dapat difilter. Kartu ringkasan status SO menghitung status nyata (SO **Disetujui** — mayoritas — tidak lagi hilang); kunci lama `cancelled` dipertahankan sebagai alias | service + `sales_report.blade.php` |
| 9.5 Mata uang | Kolom Mata Uang + rincian per mata uang (IDR dan asal = total ÷ kurs invoice) pada ringkasan; nilai tersimpan pada invoice diperlakukan sebagai IDR (sama seperti pembentukan AR). Lihat temuan di bawah | service |
| 9.6 Rekonsiliasi | `cogsReconciliation(dari, sampai)` membandingkan Σ HPP laporan dengan Σ jurnal HPP pada periode; ditampilkan sebagai "Rekonsiliasi HPP dengan Jurnal: Sesuai / SELISIH …" di layar dan PDF (tidak dihitung bila filter customer/nomor/status aktif). Invoice lama tanpa snapshot dihitung estimasi dan terlihat sebagai selisih | service |
| 9.7 Ekspor | **Excel** mode Invoice: satu baris per baris invoice (datar, angka numerik, mudah difilter/pivot) dengan kolom sesuai rencana + baris TOTAL; mode Pengiriman serupa; mode Pesanan tetap berbentuk lama (kini `order_date` dan status berbahasa Indonesia). **PDF** landscape untuk Invoice/Pengiriman (kolom + ringkasan + catatan estimasi); PDF Pesanan diperbarui ke status nyata | `SalesReportExport.php`, `sales_report_modes.blade.php` |
| — Widget | "SO Belum Selesai" kini hanya SO **berjalan** (`SaleOrder::OUTSTANDING_STATUSES`: Disetujui, Dikonfirmasi, Dikonfirmasi Sebagian, Dikirim Sebagian, Minta Ditutup). Sebelumnya `status != completed` sehingga draft, menunggu persetujuan, ditutup, dibatalkan, dan ditolak ikut terhitung | `SoBelumSelesaiTable.php`, `SaleOrder.php` |
| Backfill | `invoices:backfill-cogs` (dry-run default, `--apply`, CSV): mengisi snapshot untuk invoice lama dengan prioritas **jurnal** (invoice satu baris: angka jurnal persis) → **stok** (nilai pergerakan stok DO untuk produk & kuantitas yang sama) → **estimasi** (cost_price master saat ini). Melaporkan selisih Σ baris vs jurnal per invoice | `BackfillInvoiceCogs.php` |

**Keputusan desain / temuan (perlu Anda ketahui):**
1. **Default laporan berubah dari SO ke Invoice** (D7): tampilan awal kini berisi invoice periode berjalan, bukan SO. Mode Pesanan tetap tersedia untuk yang memerlukannya.
2. **Status invoice:** enum `invoices.status` hanya `draft/sent/paid/partially_paid/overdue/unpaid` — **tidak ada status batal**; pemeriksaan `status != 'canceled'` di beberapa kode (mis. pembuatan invoice per DO) tidak pernah berlaku. Laporan hanya mengecualikan Draft. Status "unpaid" (dipakai jalur DO) dan "overdue" (jarang terisi otomatis karena scheduler `invoices:check-overdue` tidak aktif) tidak diandalkan: status pembayaran laporan dihitung dari **Account Receivable dan tanggal jatuh tempo**.
3. **Temuan mata uang (belum diperbaiki):** invoice dari jalur **Delivery Order** menyimpan nilai baris dalam mata uang SO (tanpa konversi) sedangkan header membawa `currency_id`/`exchange_rate` SO, sementara jalur **Sales Order** mengonversi ke IDR dan `AccountReceivable` memperlakukan `invoice.total` sebagai IDR. Untuk SO berbahasa mata uang asing angka DO-path bisa tidak konsisten. Laporan memakai nilai tersimpan apa adanya; perbaikan konvensi mata uang invoice perlu keputusan tersendiri.
4. **HPP mode Pengiriman** diambil dari `stock_movements.value` (snapshot stok saat DO `sent`), sedangkan **mode Invoice** dari snapshot jurnal HPP; keduanya seharusnya sama untuk DO yang ditagih penuh, namun dapat berbeda untuk invoice per-DO parsial atau bila jurnal HPP dibentuk dari cost_price berbeda dari nilai stok.
5. **Invoice lama tanpa snapshot** ditandai **estimasi** (`*`) dan dihitung dari cost_price master saat ini: dapat berbeda dari jurnal HPP yang sudah diposting. Setelah `invoices:backfill-cogs --apply`, invoice satu baris memakai angka jurnal persis; sisanya memakai nilai stok atau tetap estimasi.

**Pengujian:**
- Baru: `tests/Feature/SalesReportPhase6Test.php` — **19 tes lulus** (snapshot = jurnal; HPP tetap walau cost_price berubah; rekonsiliasi; invoice lama = estimasi → selisih terlihat → hilang setelah backfill; backfill dry-run/apply/idempoten dan tidak menyentuh kolom lain; default Invoice + tanggal dokumen + draft dikecualikan; mode Pesanan `order_date`; mode Pengiriman; opsi status dari konstanta; ringkasan status nyata; status pembayaran + filternya; kolom baris invoice; ringkasan & per mata uang; ekspor Excel (invoice/pengiriman/pesanan, `.xlsx` benar-benar dibuat); PDF + rekonsiliasi + catatan estimasi; halaman laporan; reset status saat ganti mode; widget). Pemeriksaan mutasi: snapshot tidak disimpan → 6 gagal; HPP selalu dari master → 5; tanggal invoice memakai `created_at` → 1; widget lama → 1; rekonsiliasi bergeser → 3.
- Diperbarui (mengunci perilaku lama): `SalesReportPageTest` dan `SalesReportServiceTest` — kini memakai mode Pesanan secara eksplisit dan `order_date`.
- Regresi (66 berkas terkait + 8 berkas fase, 1.331 lulus): dibandingkan per nama tes dengan seluruh baseline sebelumnya — **tak ada kegagalan baru** (kegagalan yang tersisa sudah ada di baseline: fixture lama, tes pembelian, dan `CabangFactory` yang flaky).

**Sebelum Fase 6 dianggap selesai di lingkungan Anda:**
1. `php artisan migrate` (migrasi `2026_09_20_190000_add_cogs_snapshot_to_invoice_items_table`; kode aman sebelum dijalankan — snapshot dilewati).
2. `php artisan invoices:backfill-cogs` (dry-run) → tinjau **selisih terhadap jurnal HPP** → `--apply`.
3. UAT: buka Laporan Penjualan (default Invoice) → periksa HPP, margin, status pembayaran dan baris "Rekonsiliasi HPP dengan Jurnal" → **cocokkan total HPP dan penjualan dengan Laba Rugi periode yang sama** (gerbang keluar Fase 6) → ekspor Excel/PDF.

## Audit ulang Fase 1–6 pasca-revert `8eedb05`, 20 September 2026

**Pertanyaan:** apakah `git revert 8eedb05` (yang mengembalikan mask uang di `AppServiceProvider`) membatalkan pekerjaan Fase 1–6? **Tidak.** Revert itu hanya menyentuh **satu baris** (`AppServiceProvider.php:156`); seluruh berkas Fase 1–6 identik dengan commit `4dde7aa`. Mask Indonesia `$money($input, ',', '.', 2)` justru **dibutuhkan** Fase 1 (state form berformat `8.687,00`); mask `('.', ',')` akan kembali memunculkan bug 100× (`8687.00` → `868.700`).

**Yang dilakukan:** 200 tes fase + 1 tes end-to-end (`SalesFlowEndToEndFase1to6Test`, 77 assertion, mutasi terverifikasi) dan suite penuh dibandingkan per nama tes dengan baseline `c1c4c72` (DB uji terpisah, dijalankan per potongan 30 berkas — suite tunggal crash memori karena `IncreaseMemoryLimit`/API controller memaksa `memory_limit=512M`, juga terjadi pada baseline).

**Temuan & perbaikan:**
1. `CustomerReceiptObserver::$arUpdatedInCreate` (static, sudah ada sebelum Fase 5A) menyimpan ID penerimaan yang AR-nya sudah diperbarui dan tidak pernah dibersihkan → ID baru yang sama (rollback/proses panjang) melewati pembaruan AR. Kini dibersihkan pada `created()`.
2. Tes Fase 5A men-*drop* kolom `is_cash_bank` (DDL = implicit commit, bertahan lintas tes) tanpa memulihkannya → kini dipulihkan di `finally`.
3. `QuotationService::expireOverdue` memakai `withoutGlobalScopes()` (ikut melepas SoftDeletes → quotation terhapus bisa dikedaluwarsakan) → kini scope default dipakai.
4. `DeliveryOrderSourceValidator` memakai `withoutGlobalScopes()` (SO terhapus lolos validasi) → kini hanya `CabangScope` yang dilepas.
5. `SalesReportPage` membuat `SalesReportService` baru tiap sel sehingga cache baris tidak terpakai → di-memoize per request.
6. `tests/Feature/Api/QuotationApiTest` & `SaleOrderApiTest` tidak memberi izin ke pengguna uji; sejak Fase 3 endpoint tulis memeriksa izin (403) → tes kini memberi izin eksplisit dan memakai factory produk.

**Hasil pembanding suite penuh:** kegagalan di tree ini ⊆ kegagalan baseline, kecuali satu tes yang bergantung urutan/ID keras (`UATUIUXAuditVerificationTest::po item refer item label…`, memakai `cabang_id => 1`; gagal/lolos tergantung posisinya dalam proses, identik pada kedua tree bila dijalankan terisolasi). ~210 kegagalan lain sudah ada di baseline dan tidak terkait Fase 1–6.
