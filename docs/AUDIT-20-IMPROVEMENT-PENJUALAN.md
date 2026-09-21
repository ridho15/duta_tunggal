# Audit & Rencana Bertahap — 20 Usulan Improvement Penjualan

> Dibuat 20 September 2026 · Cakupan: alur Quotation → SO → DO/Jadwal/Surat Jalan → Invoice → Penerimaan → Retur, plus kontrol internal, dokumen, UI/UX, dan master data.
> Dokumen ini **tidak mengubah kode**. Isinya: (1) hasil audit tiap usulan terhadap kode saat ini, (2) temuan tambahan yang tidak ada di daftar, (3) keputusan yang dibutuhkan, (4) roadmap tahap demi tahap, (5) matriks keterlacakan agar tidak ada yang terlewat.
> **Rencana pelaksanaan rinci T0 & T1: [`docs/PLAN-T0-T1-PENJUALAN.md`](PLAN-T0-T1-PENJUALAN.md)** (memuat koreksi hasil pemeriksaan lanjutan).
> Kelanjutan dari audit 10 bug UAT (`docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md`, Fase 1–6 sudah selesai). Untuk membedakan, tahap di dokumen ini diberi nama **T0–T8**.

## Daftar Isi
0. [Ringkasan eksekutif](#0-ringkasan-eksekutif)
1. [Metode & batasan audit](#1-metode--batasan-audit)
2. [Bukti perilaku nyata (probe)](#2-bukti-perilaku-nyata-probe)
3. [Matriks 20 usulan](#3-matriks-20-usulan)
4. [Audit rinci per usulan](#4-audit-rinci-per-usulan)
5. [Temuan tambahan (di luar 20 usulan)](#5-temuan-tambahan-di-luar-20-usulan)
6. [Keputusan yang saya butuhkan](#6-keputusan-yang-saya-butuhkan)
7. [Roadmap bertahap T0–T8](#7-roadmap-bertahap-t0t8)
8. [Matriks keterlacakan (checklist anti-terlewat)](#8-matriks-keterlacakan-checklist-anti-terlewat)
9. [Strategi pengujian, rollout & rollback](#9-strategi-pengujian-rollout--rollback)
10. [Risiko & asumsi](#10-risiko--asumsi)

---

## 0. Ringkasan eksekutif

**Kesimpulan utama.** Dari 20 usulan, **tidak ada yang sepenuhnya sudah selesai**, tetapi **16 di antaranya sudah punya "mesin" separuh jalan** di kode (sering ada tetapi tidak tersambung, atau tersambung hanya di satu pintu). Artinya sebagian besar pekerjaan adalah *menyambungkan dan mengunci* — bukan membangun dari nol. Hanya 4 yang benar-benar baru: **jalur uang pada retur (4)**, **Nota Kredit/pembatalan invoice (5)**, **referensi & bukti transfer (10)**, dan **alat audit/gabung customer ganda (20)**.

**Tiga temuan yang menurut saya paling penting** (semuanya diukur, bukan asumsi — lihat §2):

1. **Reservasi stok bocor permanen (temuan baru, bukan dari daftar).** Setelah DO dikirim/selesai, `qty_reserved` **tidak pernah turun**. Contoh terukur: stok 30, kirim 12 → `qty_available` 18 (benar) tetapi `qty_reserved` tetap 12, sehingga stok bebas dilaporkan 6 padahal seharusnya 18. Tiap pengiriman mengunci stok selamanya. Ini harus diperbaiki **sebelum** usulan 1–3, karena reservasi di SO akan memperbesar dampaknya.
2. **Stok tidak dicek saat SO disetujui bila item tidak punya alokasi gudang.** SO qty 35 dengan stok 0/30 **lolos approve** (terukur). Indikator "STOK KURANG" hanya pewarnaan baris di daftar; tidak memblokir dan tidak memperingatkan saat input/approve. Fungsi `SalesOrderService::confirm()` yang sebenarnya memvalidasi + mereservasi stok **tidak pernah dipanggil** (kode mati).
3. **Jalur jadwal pengiriman melewati pintu status DO.** Aksi jadwal "Tandai Selesai" dari status *pending* langsung memaksa DO `approved → sent → completed` dalam satu klik (126 query), tanpa memperbarui status item DO (tetap `requested`), tanpa cek stok, dengan konfirmasi generik. Ini menjelaskan keluhan #1, #3, dan #16 sekaligus.

**Rencana ringkas** (estimasi hari kerja satu pengembang; tahap bertanda ∥ bisa paralel):

| Tahap | Isi | Usulan | Estimasi |
|---|---|---|---|
| **T0** | Baseline, keputusan, perbaikan tooling uji | — | 0,5–1 |
| **T1** | Quick wins tanpa keputusan besar | 10, 13, 15a, 18a, 19a, 20a | 3–4 |
| **T2** | **Stok & pengiriman** (jalur kritis) | 1, 2, 3 (+temuan X1–X5) | 8–10 |
| **T3** | Kontrol internal & Setting Akuntansi | 6, 7, 8, 9 | 7–9 |
| **T4** ∥ | Penomoran terpusat & bersih-bersih customer | 12, 20b–d | 5–7 |
| **T5** | Koreksi finansial: Nota Kredit & retur uang | 4, 5 | 8–10 |
| **T6** | Dokumen cetak | 11 | 4–5 |
| **T7** | UX konsisten & performa | 14, 15b, 16, 17, 18b, 19b | 9–12 |
| **T8** | Penutupan: E2E lintas tahap, skrip UAT, pemeriksa integritas | semua | 2–3 |
| | **Total** | | **≈ 47–61 hari** |

Urutan didasarkan pada dependensi: stok yang benar (T2) harus ada sebelum kontrol (T3) dan koreksi keuangan (T5) — Nota Kredit dan retur memindahkan stok dan jurnal; penomoran (T4) harus ada sebelum Nota Kredit punya nomor; pesan konfirmasi berdampak (16) baru bisa akurat setelah logika stok/jurnal dipusatkan.

---

## 1. Metode & batasan audit

- Membaca kode terkait tiap usulan (service, observer, policy, resource Filament, komponen React, migrasi, template PDF) dan mencatat *apa yang sudah ada*, *apa yang tersambung*, dan *apa yang belum*.
- **Probe pengukuran**: satu tes sementara (dihapus setelah dipakai) menjalankan skenario nyata di database uji — SO 35 vs stok 30, DO disetujui, jadwal "Tandai Selesai" — dengan penghitung query dan pengukur waktu. Hasil di §2.
- Membaca dokumen sebelumnya (`PEMAHAMAN-SISTEM.md`, audit 10 bug) agar tidak mengulang pekerjaan Fase 1–6 (mis. `CustomerReceiptAllocator`, `LineAmounts`, `SuratJalanDocumentBuilder`, kolom `is_cash_bank`, kunci Quotation/Surat Jalan).

**Batasan yang jujur:**
- Data UAT Anda (mis. "DAYA TEKNIK MEDIKA 4 versi", kode customer berisi NIK, limit Rp999.999.999.999, respons 8–15 detik) **tidak ada di database dev saya** (26 customer, tanpa duplikat). Usulan 19 dan 20 karenanya saya audit dari *penyebab di kode*, dan angka pastinya harus diukur di lingkungan UAT (perintah disediakan di T1/T4).
- Usulan 14 (pola tombol) saya verifikasi dari struktur kode, bukan dari tangkapan layar.
- Tidak ada perubahan pada data atau kode aplikasi selama audit ini.

---

## 2. Bukti perilaku nyata (probe)

Skenario: 1 produk, stok gudang 30, SO Kirim Langsung qty 35 (tanpa alokasi gudang), customer tipe Bebas.

| Langkah | Hasil terukur | Arti |
|---|---|---|
| SO qty 35, stok 30 → `SalesOrderService::approve()` | **Lolos**, status `approved` | Tidak ada cek stok bila item tanpa `warehouseAllocations` (usulan 2) |
| Reservasi setelah SO approved | **0** baris | Belum ada reservasi sejak SO disetujui (usulan 1) |
| DO `approved` (22 query, 5 ms) | Reservasi DO = 12, `qty_available` 30, `qty_reserved` 12 | Reservasi baru terbentuk di tahap DO, bukan SO |
| Status item DO setelah DO approved | `requested` | Tidak ikut berubah (usulan 3) |
| Jadwal `pending → delivered` ("Tandai Selesai") | **126 query, ~150–190 ms** (skala uji), DO → `completed`, 1 invoice, 6 jurnal | Melompati "Dalam Perjalanan"; semua efek dalam satu klik |
| Status item DO setelah `completed` | **masih `requested`** | Item tidak pernah diperbarui oleh jalur jadwal (usulan 3) |
| Stok setelah dikirim 12 | `qty_available` 30 → **18**, `qty_reserved` **tetap 12** | **Reservasi yatim**: stok bebas dilaporkan 6, seharusnya 18 (temuan X1) |
| Status SO setelah DO selesai | `partially_delivered` | Benar (Fase 2 bekerja) |

Catatan: angka waktu di skala uji kecil; pada UAT/produksi, faktor di §4 (usulan 19) yang mendominasi.

---

## 3. Matriks 20 usulan

Legenda kondisi kode: 🟡 **Sebagian** (sudah ada sebagian/ada tetapi tidak tersambung) · 🔴 **Baru** (belum ada). Ukuran: S ≤ 1 hari · M 2–3 hari · L 4+ hari.

| # | Usulan | Kondisi | Temuan inti (bukti) | Ukuran | Tahap |
|---|---|---|---|---|---|
| 1 | Reservasi stok sejak SO approved | 🟡 | Reservasi hanya dibuat saat **DO** `approved` (`DeliveryOrderObserver::handleApprovedStatus`); `SalesOrderService::confirm()` (validasi+reservasi) **tidak pernah dipanggil**; reservasi DO **tidak pernah dilepas** setelah kirim | L | T2 |
| 2 | Peringatan stok kurang di SO + backorder | 🟡 | `SalesOrderService::approve()` cek stok **hanya bila ada alokasi gudang**; `hasInsufficientStock()` hanya mewarnai baris daftar (dan N+1); form React menampilkan "Stok: N" tanpa peringatan; API tidak cek | M | T2 |
| 3 | Tahapan DO yang jelas | 🟡 | Status lengkap sudah ada (`request_stock…completed`), tetapi jadwal mengubah status DO langsung (`DeliveryScheduleService`), melewati `DeliveryOrderService::updateStatus` → status item tidak ikut; tidak ada aksi "Kirim"/"Diterima" pada DO | L | T2 |
| 4 | Retur penjualan dengan jalur uang | 🔴 | Keputusan retur hanya `repair/replace/reject`; jurnal retur hanya Persediaan/WIP ↔ HPP, **tanpa membalik pendapatan/PPN/piutang** | L | T5 |
| 5 | Nota kredit / pembatalan invoice | 🔴 | Invoice hanya bisa diubah/dihapus saat `draft` (`InvoicePolicy`); enum status **tidak punya** `canceled` padahal dipakai di kueri; `reverseInvoiceJournalEntries` tersedia di ledger tetapi tak terpakai UI | L | T5 |
| 6 | Pemisahan pembuat–penyetuju + batas nominal | 🟡 | SO sudah (SoD + tier Rp10 jt di `ApprovalControlService`, **konstanta hard-code**); **Quotation belum**; penegakan di policy/UI, bukan di service | M | T3 |
| 7 | Limit kredit berfungsi + "Info Customer" | 🟡 | Mesin ada (`CreditValidationService`, lock), tetapi hanya tipe **Kredit**, `kredit_limit <= 0` = tak terbatas, paparan SO belum ditagih tak dihitung; kotak Info Customer hanya menampilkan limit bila Kredit | M | T3 |
| 8 | Kunci dokumen yang sudah diproses | 🟡 | Quotation, Surat Jalan, SO, Invoice sudah terkunci di policy; **DO** (aksi Edit di tabel tanpa cek status), **Penerimaan** (edit/hapus tanpa batas status), **Jadwal** (setelah selesai) belum | M | T3 |
| 9 | Akun COA di Setting Akuntansi | 🟡 | Fase 5A sudah membatasi akun kas/bank (bukan induk); pemetaan akun lain via `config/coa.php` + ±117 pencarian kode hard-code; **tidak ada layar pengaturan**; masih ada pilihan COA per transaksi (Deposit, Penyesuaian Deposit, Penjualan Lain, item SO) | L | T3 |
| 10 | Referensi transfer + bukti unggah | 🔴 | Penerimaan hanya punya `ntpn`; tidak ada referensi/bukti. Pola `proof_file` sudah ada di `VendorPaymentResource` | S–M | T1 |
| 11 | Format cetak rapi | 🟡 | Surat Jalan, SO, Quotation sudah berlogo+TTD; **invoice** tanpa logo/kop/NPWP/TTD/rekening; DO minim; kolom identitas (NPWP, rekening) belum ada | M | T6 |
| 12 | Penomoran konsisten & berurutan | 🟡 | ≥ 6 generator terpisah dengan format berbeda (`SO-00005`, `DO-YYYYMMDD-0001`, `INV-…`, `SCH-…`, `SJ-…`, `QO-…`); `SequentialNumberGenerator` ada tetapi tidak dipakai semua; kolom `Cabang.kode_invoice_pajak/non_pajak` **ada tetapi diabaikan**; tanpa kunci/urutan atomik | M | T4 |
| 13 | Nomor faktur pajak di invoice penjualan | 🟡 | Kolom `invoices.tax_invoice_number` **sudah ada** (dipakai pembelian) tetapi tidak muncul di form/daftar/PDF penjualan | S | T1 |
| 14 | Samakan pola aksi antar modul | 🟡 | Di halaman **View**, SO & Quotation membungkus aksi dalam `ActionGroup` ("Action"), sedangkan DO, Invoice, dan seluruh modul Pembelian menampilkan tombol langsung — inkonsistensi juga **di dalam** Penjualan | M | T7 |
| 15 | Bahasa Indonesia penuh | 🟡 | Akar `validation.required`: **tidak ada `lang/id/validation.php`** (hanya `lang/vendor`); ±10 kolom status penjualan/keuangan menampilkan nilai mentah | S + M | T1, T7 |
| 16 | Konfirmasi menjelaskan dampak | 🟡 | `requiresConfirmation` ada di sebagian aksi tetapi **generik** ("Tandai jadwal … selesai?"); Invoice & Penerimaan **tanpa** konfirmasi | L | T7 |
| 17 | Pencarian dropdown & batas 50 | 🟡 | Filament `Select` default `optionsLimit=50`; form Invoice memuat `Customer::all()` + `preload()`; form React memuat **semua** customer & produk lalu memfilter di klien, menampilkan maks 100, dropdown quotation `limit(50)` | L | T7 |
| 18 | Info penting di layar | 🟡 | "Sisa Qty Belum Dikirim" sudah ada di View SO (Fase 2), belum di daftar; label DO di pilihan invoice memakai `DeliveryOrder::getTotalAttribute` (`harga − diskon + pajak`, mencampur % dan Rp); label pilihan SO hanya nomor SO | M | T1, T7 |
| 19 | Percepat respons aksi | 🟡 | Perlu ukur di UAT; penyebab terverifikasi di kode: 126 query/aksi, N+1 daftar SO, "muat semua" di API, 196 `Log::info` di observer/service dengan `LOG_LEVEL=debug`, sesi/cache/antrean di DB, opcache dev nonaktif, tanpa penjaga klik ganda | L | T1, T7 |
| 20 | Bersihkan master data customer | 🔴 (alat gabung) | `customers.code` unik, tetapi **nama tidak dicek duplikat**; impor legacy memakai kode legacy apa adanya (bisa NIK); tidak ada alat audit/gabung; form "buat customer" di SO/Quotation punya validasi terpisah | L | T1, T4 |

**Hitungan:** 🟡 16 · 🔴 4 (4, 5, 10, alat gabung customer di 20).

---

## 4. Audit rinci per usulan

Format tiap butir: **Kondisi kode** (terverifikasi) · **Celah** · **Rancangan** · **Selesai bila** (kriteria yang dapat diuji) · **Butuh keputusan**.

### A. Alur dan kontrol stok

#### Usulan 1 — Reservasi stok sejak SO approved

**Kondisi kode**
- Model `StockReservation` + `StockReservationObserver` menaikkan `qty_reserved` saat dibuat, menurunkan saat dihapus (tanpa menyentuh `qty_available`). Rumus stok bebas: `qty_available − qty_reserved` (`InventoryStock::freeQtyFor`).
- Reservasi untuk penjualan **hanya** dibuat di `DeliveryOrderObserver::handleApprovedStatus` (saat DO `approved`). `SalesOrderService::confirm()` (baris 131) — yang memvalidasi stok dengan `lockForUpdate` lalu membuat reservasi per item/alokasi — **tidak dipanggil dari mana pun**; status `confirmed` praktis tak terpakai.
- Reservasi DO **tidak dilepas** saat `sent`/`completed`. Pelepasan hanya ada di `DeliveryOrderService::releaseStockReservations` (baris 481) yang hanya dipanggil oleh `postDeliveryOrder` (baris 284) — itu sendiri **kode mati** (pemanggilnya dikomentari di `ViewDeliveryOrder`). Satu-satunya pelepasan hidup: DO dihapus (`deleted`) dan SO `canceled`.
- Efek: barang yang sama bisa dijual ganda sebelum DO disetujui (keluhan Anda) **dan** setiap DO yang sudah terkirim menahan stok selamanya (temuan X1).

**Rancangan (T2)**
1. *Perbaiki dulu kebocoran* (X1): saat DO `sent`, reservasi DO **dikonsumsi** (dilepas bersamaan dengan `StockMovement` keluar); saat `closed/reject/delivery_failed/canceled` dilepas. Perintah `stock:reconcile-reservations` (dry-run/`--apply`) menghitung ulang `qty_reserved` = Σ reservasi aktif dan membuat laporan selisih (data lama pasti sudah bocor).
2. *Reservasi di SO approve*: satu mekanisme reservasi bertingkat — `sale_order_id` (reservasi SO per item×gudang, memakai alokasi gudang bila ada) → ketika DO disetujui, kuantitas DO **dipindahkan** dari reservasi SO ke reservasi DO (bukan menambah) → dikonsumsi saat `sent`. Ini menghindari hitung ganda (SO 20 + DO 12 tidak boleh menahan 32).
3. Rilis otomatis: SO `canceled`/`closed`/`reject` melepas sisa reservasi; opsi (default mati) kedaluwarsa reservasi SO yang tak bergerak N hari.
4. Stok bebas di semua tampilan memakai satu `StockAvailability` (lihat usulan 2).

**Selesai bila:** (a) Σ`qty_reserved` per baris stok = Σ reservasi aktif (uji invarian + perintah rekonsiliasi = 0 selisih); (b) SO approved 20 dengan stok 30 → stok bebas 10 dan SO kedua 15 ditolak/di-backorder; (c) DO 12 disetujui → total tertahan tetap 20, setelah `sent` tertahan 8 dan stok fisik −12; (d) cancel/close melepas.
**Butuh keputusan:** D1 (reservasi saat approve), D3 (stok lintas cabang), D15 (stok negatif).

#### Usulan 2 — Peringatan stok kurang di SO, opsi backorder

**Kondisi kode**
- Bukti probe: SO qty 35 **lolos approve** tanpa alokasi gudang. `approve()` (baris 256) hanya memeriksa `warehouseAllocations`.
- `SaleOrder::hasInsufficientStock()`/`getInsufficientStockItems()` sudah ada dan benar secara logika, tetapi hanya dipakai untuk **pewarnaan baris + badge "STOK KURANG"** di daftar; per baris memanggil `freeQtyFor` per item (N+1, tanpa eager-load `warehouseAllocations`).
- Form React: `SaleOrderItemTable` menampilkan "Stok: N" pada opsi produk, tanpa peringatan bila qty > stok. `SaleOrderApiController::store/update` tidak memeriksa stok.
- Stok bebas dihitung lintas **semua** gudang/cabang (`freeQtyFor` tanpa filter cabang) — tidak menghormati `Cabang.lihat_stok_cabang_lain`.

**Rancangan (T2)**
1. `StockAvailability` (satu kelas, satu kueri batch per SO): mengembalikan per item `{diminta, bebas, kurang, gudang}` dengan kebijakan cabang (D3). Dipakai oleh form (peringatan langsung), API (mengembalikan `warnings[]`, **tidak memblokir simpan draft**), aksi Approve (memblokir), badge daftar (batch, menghapus N+1).
2. **Approve** stok kurang → diblokir kecuali **backorder** dengan alasan wajib + persetujuan peran tinggi (Sales Manager+); tersimpan `is_backorder`, `backorder_reason`, `backorder_approved_by/at`. SO backorder: reservasi sebagian (sebanyak stok bebas) atau nol (D2), dan kuantitas kurang ditandai "menunggu barang".
3. Pesan jelas: "Produk X: diminta 35, stok bebas 30 (kurang 5) di Gudang K01".

**Selesai bila:** SO 35/stok 30 tidak dapat di-approve tanpa alasan backorder; peringatan tampil saat input (form Filament & React) dan pada respons API; daftar SO tanpa N+1 (uji batas query); backorder tercatat pelaku/alasan/waktu.
**Butuh keputusan:** D2, D3.

#### Usulan 3 — Tahapan DO yang jelas

**Kondisi kode**
- Status DO: `draft, request_stock, request_approve, approved, sent, received, completed` (+`partial, closed, reject, delivery_failed, supplier`) dengan label Indonesia di `DeliveryOrder::STATUS_LABELS` ("Menunggu Konfirmasi Stok", "Disetujui", "Sedang Dikirim", "Diterima", "Selesai").
- Stok fisik keluar saat DO `sent` (`createStockMovementsForShippingStart`) — sesuai usulan Anda ("stok keluar saat Dikirim").
- **Tetapi** satu-satunya cara mencapai `sent`/`completed` adalah lewat jadwal pengiriman: `DeliveryScheduleObserver` → `DeliveryScheduleService::startRelatedDeliveryOrders/completeRelatedDeliveryOrders` memanggil `$deliveryOrder->update(['status'=>…])` langsung. `DeliveryOrderService::updateStatus` (yang menyinkronkan `deliveryOrderItem.status` dan mencatat `DeliveryOrderLog`) **dilewati** ⇒ item tetap `requested` (probe) dan log tidak tercatat. Aksi "Tandai Selesai" di jadwal boleh dari `pending` (`DeliveryScheduleResource` baris 773–781) sehingga stok keluar *dan* invoice terbit dalam satu klik; DO sendiri tidak punya aksi "Kirim"/"Diterima" (aksi `completed` dikomentari di `ViewDeliveryOrder`).
- Tidak ada cek stok saat `sent`; `StockMovementObserver` menjumlah delta tanpa batas (stok bisa negatif).

**Rancangan (T2)**
1. **Satu pintu status**: `DeliveryOrderTransitions::to($do, $status, $reason)` — memvalidasi transisi yang sah (matriks), mengunci baris (`lockForUpdate`), memeriksa stok pada `sent` (D15), menyinkronkan status item + `DeliveryOrderLog`, lalu memicu efek (stok, jurnal, invoice, progres SO). Observer jadwal, aksi DO, dan aksi tabel semuanya memanggil ini.
2. Tahapan yang tampil ke pengguna (D4: tanpa migrasi data): **Draft → Menunggu Stok (`request_stock`) → Siap Kirim (`approved`, label baru) → Dikirim (`sent`) → Diterima (`received`) → Selesai (`completed`)**. Tambah aksi DO "Kirim" dan "Konfirmasi Diterima" (izin & konfirmasi berdampak).
3. Jadwal: "Tandai Selesai" hanya dari `on_the_way` (D5); "Mulai Pengiriman" tetap satu-satunya pemicu `sent`.
4. Perintah `delivery-orders:resync-item-status` (dry-run/apply) memperbaiki status item lama dari status DO.

**Selesai bila:** semua jalur (aksi DO, aksi jadwal, observer) menghasilkan status item & log yang sama; transisi ilegal ditolak dengan pesan; `sent` tanpa stok cukup ditolak; tes matriks transisi (status × aksi) lolos.
**Butuh keputusan:** D4, D5, D15.

#### Usulan 4 — Retur penjualan dengan jalur uang

**Kondisi kode**
- `CustomerReturnItem::DECISION_*` = `repair`, `replace`, `reject` (label "Perbaikan", "Penggantian", "Klaim Ditolak"). Jurnal (`CustomerReturnService::createJournalEntries`): Dr Persediaan / Dr WIP-perbaikan, **Cr HPP** — hanya membalik biaya; **tidak ada pembalikan penjualan, PPN keluaran, ataupun piutang**. Retur punya `invoice_id`.
- Master produk sudah menyiapkan akun `sales_return_coa_id` dan `sales_discount_coa_id` (`config/coa.php` `4120.10`/`4110.10`) yang **belum dipakai** oleh alur ini. Fase 5B menyediakan `InvoiceItem::net_unit_price` "dipakai retur".

**Rancangan (T5, bergantung pada Nota Kredit — usulan 5)**
1. Tambah keputusan **"Refund / Nota Kredit"**: membuat Nota Kredit (tipe *retur*) terhubung ke retur & invoice, kuantitas dibatasi ≤ (qty invoice − qty yang sudah diretur), harga bersih dari `net_unit_price`.
2. Jurnal: **Dr Retur Penjualan (akun produk) + Dr PPN Keluaran, Cr Piutang** (atau Cr Deposit/Kas bila sudah dibayar — usulan 5); plus pembalikan HPP & stok yang **sudah ada** (jangan digandakan).
3. Refund tunai lewat pengajuan pembayaran dengan approval (usulan 6).

**Selesai bila:** retur 3 dari 12 pcs pada invoice Rp X mengurangi piutang tepat 3/12 (DPP+PPN), stok kembali, HPP dibalik, laporan penjualan & rekonsiliasi HPP (Fase 6) tetap seimbang; retur > sisa qty invoice ditolak.
**Butuh keputusan:** D10 (perlakuan pajak & refund).

#### Usulan 5 — Nota kredit / pembatalan invoice

**Kondisi kode**
- `InvoicePolicy::update/delete` hanya untuk `draft`. Enum status invoice: `draft, sent, paid, partially_paid, overdue` — **tidak ada `canceled`**, padahal kueri di `SalesInvoiceResource`/`CreateSalesInvoice` memakai `status != 'canceled'` (selalu benar).
- `LedgerPostingService::reverseInvoiceJournalEntries` (baris 1127) membuat jurnal balik; `InvoiceObserver::deleted` malah **menghapus** jurnal (bukan membalik) untuk invoice yang dihapus (hanya draft yang boleh, jadi aman saat ini).
- Tidak ada model/tabel nota kredit.

**Rancangan (T5)**
1. Tabel `credit_notes` + `credit_note_items` (nomor via T4, tanggal, alasan wajib, tipe: `pembatalan` | `retur` | `koreksi`, status `draft → terbit`, tautan invoice/retur, `approved_by`, `journal` link).
2. **Pembatalan** = Nota Kredit penuh: invoice → `canceled` (ditambahkan ke enum + label), AR di-nol-kan, jurnal dibalik (bukan dihapus), DO tetap "terkirim" (stok/HPP tidak berubah kecuali ada retur fisik). Invoice yang sudah ada penerimaan: selisih dialihkan ke **Deposit Customer** atau pengajuan refund (D10) — tidak boleh membuat AR negatif.
3. **Koreksi sebagian** (harga/diskon/qty): Nota Kredit + (opsional) invoice pengganti.
4. Semua butuh alasan, approval bertingkat (usulan 6), jejak audit (`activity_log` sudah ada), dan menutup jalan "hapus untuk memperbaiki".

**Selesai bila:** invoice terbit yang salah dapat dibatalkan/dikoreksi tanpa dihapus; Σ(invoice − nota kredit − penerimaan) = piutang; jurnal seimbang; invoice `canceled` tidak muncul di pilihan invoice/penerimaan dan tidak dihitung di laporan (Fase 6 sudah mengecualikan hanya `draft` → tambahkan `canceled`).
**Butuh keputusan:** D10.

### B. Kontrol internal

#### Usulan 6 — Pemisahan pembuat–penyetuju & batas nominal

**Kondisi kode**
- `ApprovalControlService::isSelfApproval` + `canApproveSaleOrder`: pembuat ≠ penyetuju (pengecualian Super Admin/Owner), ambang `TIER_1_MAX_AMOUNT = Rp10.000.000` → di atasnya hanya peran puncak. Dipanggil dari `SaleOrderPolicy::approve` dan aksi Filament.
- **Quotation belum** melewati kontrol ini (`QuotationPolicy::approve` hanya cek izin). `created_by` Quotation sudah selalu terisi sejak Fase 3, jadi datanya siap.
- Ambang & peran **hard-code**; penegakan di lapisan UI/policy — `SalesOrderService::approve()` sendiri tidak memeriksa (bergantung pintu masuk yang benar).
- Override Super Admin/Owner tidak meminta alasan dan tidak ditandai khusus di audit.

**Rancangan (T3)**
1. Generalisasi ke `ApprovalControlService::canApprove($user, $document)` untuk Quotation, SO, (dan Nota Kredit/refund T5, Pengajuan Pembayaran yang sudah ada).
2. Ambang & peran pindah ke tabel `approval_rules` (dokumen, batas min/maks, peran) yang dapat diubah Super Admin/Finance, dengan nilai awal = perilaku sekarang (tanpa perubahan mendadak).
3. Penegakan **di service** (dilempar `ValidationException`) sehingga API/aksi lain tidak bisa menghindarinya.
4. Override (Owner/Super Admin) wajib alasan + tercatat sebagai "override" di audit trail.

**Selesai bila:** pembuat Quotation tidak dapat menyetujui sendiri (kecuali override beralasan); SO/Quotation > ambang hanya oleh peran tinggi; ubah ambang di layar → berlaku tanpa deploy; tes: matriks (peran × nominal × pembuat).
**Butuh keputusan:** D6.

#### Usulan 7 — Limit kredit berfungsi + Info Customer

**Kondisi kode**
- `CreditValidationService::canCustomerMakePurchase`: aktif **hanya `tipe_pembayaran = 'Kredit'`**; limit dicek dalam `DB::transaction` + `lockForUpdate` pada baris customer (aman dari balapan); **`kredit_limit <= 0` = tak terbatas** (baris 50); pemakaian = Σ `account_receivables.remaining` — **SO yang disetujui tapi belum ditagih tidak dihitung**; tagihan jatuh tempo memblokir; 80–99% peringatan.
- Limit Rp999.999.999.999 adalah **data master** (hasil impor/entri), bukan default di kode — jadi tidak ada yang "memblokir" karena angkanya efektif tak terbatas.
- "Info Customer" (React: `SaleOrderHeaderForm.tsx` ~296–340) menampilkan tipe pembayaran, tipe, dan deposit; limit/pemakaian/sisa **hanya bila Kredit**. Customer Bebas/COD tidak menampilkan piutang berjalan sama sekali. Versi Filament: teks bantu di pilihan customer (`SaleOrderResource` baris 599–620), juga hanya untuk Kredit.

**Rancangan (T3)**
1. Kebijakan eksplisit per customer (D7): Kredit = blokir bila melebihi; COD = tanpa kredit; Bebas = hanya informasi. `kredit_limit = 0` pada tipe Kredit berarti **tidak diizinkan kredit** (bukan tak terbatas).
2. **Paparan (exposure)** = piutang berjalan + SO approved belum ditagih + SO yang sedang diajukan. Peringatan saat simpan, blokir saat approve (dengan pengecualian beralasan oleh peran tinggi).
3. Info Customer untuk **semua** tipe: limit, terpakai, sisa, piutang jatuh tempo, umur tertua, SO terbuka, deposit — dari satu endpoint ringkas (dipakai React & Filament).
4. Perintah `customers:audit-credit-limit` (read-only): daftar customer Kredit dengan limit 0 atau > ambang wajar untuk ditinjau bisnis; koreksi angka adalah keputusan bisnis, bukan otomatis.

**Selesai bila:** SO yang membuat paparan melebihi limit tidak dapat disetujui (kecuali pengecualian dicatat); Info Customer menampilkan piutang berjalan untuk semua tipe; uji balapan dua SO bersamaan tetap aman.
**Butuh keputusan:** D7.

#### Usulan 8 — Kunci dokumen yang sudah diproses

**Kondisi kode (per dokumen)**

| Dokumen | Kunci saat ini | Celah |
|---|---|---|
| Quotation | Terkunci sejak Approved (Fase 3) | — |
| Surat Jalan | Terkunci setelah Terbit (Fase 4) | — |
| SO | `update` hanya `draft`/`request_approve`; `delete` hanya `draft` (`SaleOrderPolicy`) | Perlu diuji terhadap API & relation manager item |
| DO | Halaman View: Edit hanya pada draft/request_*; **tabel: `EditAction` tanpa `visible`** (`DeliveryOrderResource` ~1036); `DeliveryOrderPolicy::update` tak melihat status | DO `sent/completed` dapat dibuka dari tabel |
| Invoice | `update/delete` hanya `draft` | Tidak ada jalur koreksi (usulan 5) |
| Penerimaan | `update/delete` hanya cek izin — halaman Edit membuka ulang alokasi, jurnal dihapus & dibuat ulang (`CustomerReceiptObserver::updated`) | Penerimaan yang sudah berjurnal bebas diubah/dihapus |
| Jadwal | Tidak ada kunci setelah `delivered` | Ubah jadwal yang sudah menerbitkan invoice |
| Retur | Hanya cek izin | Belum dikunci setelah `completed` |

**Rancangan (T3):** satu `DocumentLock::isLocked($model)` (+ alasan) sebagai sumber tunggal untuk **policy, visibilitas aksi Filament, dan API**; matriks status×aksi terdokumentasi; koreksi dokumen terkunci melalui jalur resmi (Nota Kredit, pembatalan penerimaan dengan jurnal balik, revisi) — bukan "buka kunci". Satu tes berbasis data mengiterasi semua dokumen × status dan menegaskan `update/delete` sesuai matriks (mencegah dokumen baru lupa dikunci).
**Selesai bila:** tiap sel matriks lolos; penerimaan berjurnal tidak bisa diedit/dihapus langsung tetapi dapat *dibatalkan* (jurnal balik, AR dikembalikan).
**Butuh keputusan:** D8.

#### Usulan 9 — Akun COA di Setting Akuntansi

**Kondisi kode**
- Fase 5A: `ChartOfAccount::scopeCashBank` (bertanda `is_cash_bank`, hanya akun detail), `coa:flag-cash-bank`, `master:readiness` — hanya untuk **penerimaan customer**.
- Pemetaan akun lain: `config/coa.php` (kode) + kode hard-code di ±117 titik (`InvoiceObserver`, `DeliveryOrderObserver`, `CustomerReturnService`, `LedgerPostingService`, `SalesInvoiceResource` ~767 dst.) dengan fallback bertingkat (mis. `['1140.10','1140.01']`). Beberapa inkonsisten: `config('coa.inventory')='1140.10'` tetapi `StockOpname` memakai default `'1140.01'`.
- `AppSettingsPage` (hanya Super Admin) baru berisi satu toggle (`do_approval_required`) di tabel `AppSetting` (key–value) — titik ekstensi yang siap dipakai.
- Pilihan COA per transaksi masih ada di: Deposit (`coa_id`, `payment_coa_id`), Penyesuaian Deposit, Penjualan Lain, item SO (`SaleOrderResource` ~1888), item invoice (`invoice_items.coa_id`).

**Rancangan (T3)**
1. Halaman **Pengaturan Akuntansi** (Finance Manager + Super Admin; perubahan tercatat): peta kunci → akun (Piutang, Pendapatan, Diskon, Retur Penjualan, PPN Keluaran, Deposit Customer, Barang Terkirim, HPP, Persediaan, Kas/Bank default, Biaya Kirim).
2. `AccountingSettings::coa('accounts_receivable')` memvalidasi: akun ada, aktif, **akun detail (bukan induk)**, tipe sesuai; fallback ke `config/coa.php` bila belum diatur (tanpa mengubah perilaku saat ini). Migrasi bertahap: alur penjualan dulu (Invoice, DO, Retur, Penerimaan, Deposit), lalu sisanya.
3. Hapus pilihan COA bebas untuk akun internal; **yang tetap dipilih pengguna hanya akun kas/bank penerima** (dari daftar sah Fase 5A), dengan default dari pengaturan.
4. `master:readiness` diperluas: setiap kunci wajib terisi & valid.

**Selesai bila:** mengganti akun di layar mengubah akun pada jurnal transaksi berikutnya; akun induk ditolak di semua titik; tidak ada lagi kode akun hard-code di alur penjualan (uji pemindai); readiness "siap" hanya bila semua kunci terisi.
**Butuh keputusan:** D9.

#### Usulan 10 — Referensi transfer & bukti unggah pada penerimaan non-tunai

**Kondisi kode:** `customer_receipts` punya `ntpn` (nomor penerimaan negara — bukan referensi bank), tanpa kolom referensi/bukti. `VendorPaymentResource` sudah punya `FileUpload::make('proof_file')` (pola siap ditiru); `CashBankTransaction/Transfer` punya `attachment_path`.
**Rancangan (T1):** kolom `payment_reference` (wajib untuk metode non-tunai), `proof_path` (pdf/jpg/png ≤ 2 MB, disk privat, unduh lewat rute berizin), `bank_name` opsional; peringatan bila kombinasi referensi+akun+nominal sudah pernah dicatat (duplikat); tampil di View dan daftar (kolom/filter "tanpa bukti"); ikut terkunci bersama dokumen (usulan 8). *(Koreksi: kwitansi belum tersambung ke penerimaan, jadi tampil di kwitansi masuk T6; disk bukti dibuat **privat**, tidak meniru `VendorPaymentResource` yang memakai disk publik.)*
**Selesai bila:** transfer tanpa referensi ditolak; bukti dapat diunggah/dilihat oleh yang berizin; duplikat memunculkan peringatan.

### C. Dokumen dan penomoran

#### Usulan 11 — Format cetak rapi

**Kondisi kode (isi tiap templat, `resources/views/pdf/`)**

| Dokumen | Logo/kop | TTD | PPN | Tempo/termin | Rekening | Catatan |
|---|---|---|---|---|---|---|
| Surat Jalan | ✅ | ✅ | — | — | — | Sudah layak cetak (Fase 4), tanpa harga (benar) |
| SO / Quotation | ✅ | ✅ | ✅ | ✅ | — | Baik |
| **Invoice** (`sale-order-invoice`) | ❌ | ❌ | ✅ | sebagian | hanya teks "transfer bank" | Perlu kop, NPWP, TTD, rekening; landscape 12 kolom (Fase 5B) |
| **DO** (`delivery-order`) | ✅ | minim | ✅ | — | — | Perlu blok tanda tangan (pengirim/penerima/gudang) |
| Kwitansi / Retur | ❌ | ✅ | — | — | — | Belum berkop |

Data identitas untuk kop belum lengkap: `Cabang` punya `alamat`, `telepon`, `logo_invoice_non_pajak`, `nama_kwitansi`, tetapi **tidak ada NPWP, nama badan hukum, atau rekening bank**; logo Invoice memakai berkas statis `public/logo_duta_tunggal.png`.

**Rancangan (T6):** partial bersama `company-header` (logo, nama legal, alamat, telepon, NPWP — dari Cabang bila terisi, kalau tidak dari pengaturan global; D12), `doc-meta`, `signature-block` (peran & nama), `bank-accounts`; `DocumentPrintBuilder` seperti `SuratJalanDocumentBuilder` (data dirakit di PHP, Blade hanya tampilan); watermark "DRAFT"/"DIBATALKAN"; tes: teks PDF memuat tiap elemen wajib.
**Selesai bila:** Invoice, DO, Kwitansi, Nota Kredit memuat kop, item, qty, harga, PPN, termin/jatuh tempo, rekening, tanda tangan; tampil benar pada A4 portrait & landscape; tes isi PDF lolos.
**Butuh keputusan:** D12.

#### Usulan 12 — Penomoran konsisten & berurutan

**Kondisi kode:** generator tersebar dan berbeda-beda —
`SalesOrderService::generateSoNumber` (`SO-00005`, tanpa tanggal, 5 digit), `DeliveryOrderService::generateStaticDoNumber` (`DO-YYYYMMDD-0001`), `InvoiceService::generateInvoiceNumber` (`INV-YYYYMMDD-0001`), `DeliveryScheduleService` (`SCH-…`), `SuratJalanService` (`SJ-…` via `SequentialNumberGenerator`), `QuotationService::generateCode` (`QO-YYYYMMDD-…`), `CustomerReturn` (`return_number`), Deposit (`DepositNumberGenerator`). Semua memakai pola "ambil MAX lalu cek eksis" **tanpa kunci** (bergantung indeks unik untuk menolak tabrakan). `Cabang.kode_invoice_pajak / kode_invoice_non_pajak / kode_invoice_pajak_walkin` dan `label_invoice_*` **sudah ada di skema tetapi tak dipakai** oleh generator invoice.

**Rancangan (T4):** `DocumentNumberService::next(type, cabang, date)` + tabel `document_sequences` (`type, cabang_id, period, last_number`) dengan `lockForUpdate` (atomik, tanpa celah akibat balapan); format dari konfigurasi per jenis (mis. `{PREFIX}/{KODECABANG}/{YYMM}/{SEQ4}`); invoice memakai kode pajak/non-pajak dari Cabang; benih urutan dari MAX data lama (nomor lama **tidak diubah**, hanya nomor baru yang mengikuti format); tes konkurensi & unik.
**Selesai bila:** semua dokumen penjualan memakai satu format & satu layanan; dua proses bersamaan tidak menghasilkan nomor sama; nomor lama tetap valid; perintah `documents:check-numbering` melaporkan celah/duplikat (informasi, bukan koreksi).
**Butuh keputusan:** D11.

#### Usulan 13 — Nomor faktur pajak di invoice penjualan

**Kondisi kode:** `invoices.tax_invoice_number` **sudah ada** (migrasi 2026-09-19, dipakai oleh Invoice Pembelian dengan label "No. Faktur Pajak"); Invoice Penjualan tidak menampilkannya di form, daftar, maupun PDF. Customer punya `tipe` (PKP/PRI) dan invoice punya tipe pajak.
**Rancangan (T1):** field "No. Faktur Pajak" pada form/Lihat/PDF invoice penjualan **plus aksi khusus "Isi No. Faktur Pajak"** (invoice otomatis dari DO bertatus `unpaid` sehingga tidak bisa diedit lewat form; kolom ini non-keuangan sehingga tidak memicu re-posting jurnal), (format `000-000.00-00000000` divalidasi longgar, boleh kosong saat draft), wajib/tidaknya saat invoice PKP ber-PPN diterbitkan ditentukan pada keputusan D16, kolom & filter "Belum ada faktur pajak" untuk rekonsiliasi PPN Keluaran; ekspor daftar PPN Keluaran (nomor faktur, DPP, PPN) memakai angka Fase 5B.
**Selesai bila:** nomor tersimpan & tercetak; filter "belum ada faktur" akurat; nomor ganda per invoice diperingatkan.
**Butuh keputusan:** D16.

### D. UI/UX

#### Usulan 14 — Samakan pola aksi

**Kondisi kode (terverifikasi):** tabel keduanya modul memakai `ActionGroup … ->button()` (menu "Action"). Di halaman **View**: `ViewSaleOrder` dan `ViewQuotation` membungkus aksi dalam `ActionGroup`; `ViewDeliveryOrder`, `ViewSalesInvoice`, dan **semua** View Pembelian (PO, Invoice, OR) menampilkan tombol langsung. Jadi inkonsistensi ada di Penjualan sendiri.
**Rancangan (T7):** standar (D13, rekomendasi): **maksimal 2–3 aksi utama terlihat sesuai status (mis. Setujui, Kirim), aksi sekunder (Cetak, Ubah, Hapus, Revisi) di menu "Lainnya"**, diterapkan lewat helper `DocumentActions::primary()/secondary()` agar tidak dibuat ulang per halaman; urutan & warna seragam (utama = primary, destruktif = danger di menu).
**Selesai bila:** semua View & tabel dokumen penjualan/pembelian mengikuti standar; tes memastikan aksi utama tiap status terlihat (tanpa membuka menu).

#### Usulan 15 — Bahasa Indonesia penuh

**Kondisi kode**
- Locale `id` aktif; terjemahan Filament (`lang/vendor/filament*`) ada. **Akar `validation.required`**: `lang/` hanya berisi `vendor/` — **tidak ada `lang/id/validation.php`** (juga `auth`, `pagination`, `passwords`), sehingga seluruh pesan validasi Laravel tampil sebagai kunci.
- Kolom status mentah (tanpa pemetaan label) di: `CustomerReceiptResource` (576, 849), `ViewSalesInvoice` (55 — mis. `unpaid`), `WarehouseConfirmationResource` (328) & View, `ArApManagementPage` (316), `AgeingReportResource` (107), dan beberapa modul non-penjualan (Aset, Deposit, QC, Bank, dll.). Pemindai saya menemukan 25 kolom seperti itu di seluruh panel.
**Rancangan:** **T1** — tambah `lang/id/{validation,auth,pagination,passwords}.php` (+atribut Indonesia untuk field utama). **T7** — `StatusLabels` terpusat (semua konstanta `STATUS_LABELS` disatukan), perbaiki 25 kolom, pesan notifikasi/exception, isi PDF; **tes pemindai** gagal bila ada `TextColumn/TextEntry::make('*status*')` tanpa pemetaan label atau string status mentah pada Blade.
**Selesai bila:** tidak ada kunci `validation.*` atau nilai status Inggris di layar penjualan/keuangan; pemindai lolos.

#### Usulan 16 — Konfirmasi menjelaskan dampak

**Kondisi kode:** `requiresConfirmation` ada di SO (6), Quotation (5), DO (6), Jadwal (4), Surat Jalan (2), Retur (5); **Invoice dan Penerimaan: 0**. Isi umumnya generik ("Tandai jadwal pengiriman ini sebagai selesai/terkirim?"). Perhitungan stok/jurnal ada di observer/service sehingga tidak tersedia untuk ditampilkan sebelum eksekusi.
**Rancangan (T7):** pola **preview = eksekusi**: perhitungan dampak (gerakan stok per gudang, jurnal & nilai HPP/pendapatan, invoice yang akan terbit, reservasi) dipindah ke fungsi murni yang **dipakai bersama** oleh eksekutor dan oleh `ImpactPreview` (dry-run) — sehingga pesan tidak pernah berbeda dari hasil nyata. Modal: "Stok 12 pcs akan keluar dari Gudang K01.2, jurnal HPP Rp68.004 dibuat, invoice INV-… terbit." Diterapkan pada: SO Approve, DO Kirim/Selesai, Jadwal Mulai/Selesai, Terbitkan Invoice, Simpan Penerimaan, Nota Kredit, Retur.
**Selesai bila:** tiap aksi berefek di atas menampilkan dampak yang angkanya = hasil sebenarnya (tes membandingkan preview vs hasil).

#### Usulan 17 — Pencarian dropdown & batas 50

**Kondisi kode:** Filament `Select` `optionsLimit` default **50** (vendor); 368 `Select::make` di panel; form Invoice: `Customer::all()->mapWithKeys()` + `preload()` (memuat seluruh customer di setiap render); pencarian `->relationship()` hanya pada kolom judul (label tampil memakai `getDisplayName()` sehingga mencari kode/perusahaan/NIK tidak menemukan). Form React: `SaleOrderApiController::dependencies` mengembalikan **semua** customer + **semua** produk (+ agregat stok) dan `SearchableSelect` menyaring di klien, menampilkan maks 100; dropdown quotation `limit(50)` (quotation ke-51 dan seterusnya tak bisa dipilih).
**Rancangan (T7):** satu pola **pencarian sisi-server** — endpoint ringan `/api/v1/search/{customers|products|sale-orders|quotations|invoices}?q=` (kolom: nama, kode, perusahaan, NIK/NPWP, nomor; hasil dibatasi 50 + petunjuk "ketik untuk mempersempit"), `getOptionLabelUsing` agar nilai terpilih selalu tampil; macro Filament `Select::remoteSearch()` dan `SearchableSelect` React versi remote (debounce). Prioritas: customer, produk, SO, DO, invoice, quotation di modul penjualan/keuangan; sisa modul menyusul bertahap. Pemindai kode menandai pola `::all()`+`preload()` dan `->limit(50)` baru.
**Selesai bila:** customer/produk/SO ke-500 dapat ditemukan lewat pencarian kode/nama/nomor; halaman tidak lagi memuat seluruh tabel (uji batas query & ukuran respons `dependencies`).

#### Usulan 18 — Info penting di layar

**Kondisi kode & rancangan**
- **Sisa qty belum dikirim**: sudah ada di View SO (Fase 2: `SaleOrderDeliveryProgress`); belum ada di **daftar SO** dan pilihan DO → tambah kolom/tooltip (**T1**, data sudah siap, cukup batch).
- **Nilai DO di daftar/pilihan invoice**: label memakai `DeliveryOrder::getTotalAttribute` = Σ `(unit_price − discount + tax) × qty` — mencampur **persen dengan rupiah** (diskon 5% dikurangkan sebagai Rp5, PPN 11% ditambahkan sebagai Rp11) dan menghasilkan 0 bila item DO tak tertaut ke item SO. Ini sisa dari sebelum Fase 5B; ganti dengan `LineAmounts` (sumber tunggal) lewat `DeliveryOrder::valueBreakdown()` (**T1**, dengan tes: nilai DO = Σ baris invoice-nya). *Angka "Rp0" persis di UAT Anda perlu direproduksi pada datanya; penyebab yang saya lihat di kode adalah item DO tanpa tautan/harga.*
- **Nama customer di pilihan SO**: opsi hanya `so_number` (form Invoice baris ~141–149) → label `SO-00004 · PT X · 12 Sep · Rp…` (**T1**); sama untuk pilihan DO/Quotation.
- Sisanya (info stok, limit, status) mengikuti usulan 2, 7, 17.

#### Usulan 19 — Percepat respons aksi

**Kondisi kode (penyebab yang terverifikasi, urut perkiraan dampak)**
1. **Rantai observer sinkron**: jadwal "Tandai Selesai" = **126 query** (probe) — meliputi perpindahan DO dua tahap, gerakan stok, jurnal, invoice + AR, sinkron progres SO; banyak `refresh()`/`load()` berulang.
2. **N+1 di daftar**: `SaleOrderResource` `recordClasses` memanggil `hasInsufficientStock()` per baris (tanpa eager-load alokasi); badge STOK KURANG idem.
3. **"Muat semua"**: `dependencies` API (semua customer+produk+stok), `Customer::all()` di form Invoice, `->get()` seluruh invoice hanya untuk mencari DO yang sudah ditagih.
4. **Log**: `LOG_LEVEL=debug` (default `config/logging.php`), 196 `Log::info/debug` di Observer/Service/Model (443 pemanggilan `Log::` di seluruh `app/`); `LogsGlobalActivity` menulis `activity_log` (3.152 baris di dev) untuk setiap perubahan.
5. **Infrastruktur dev**: opcache **dimatikan** di `docker/dev/php.ini`; `SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=database` (setiap request membaca/menulis sesi & cache di MySQL); `APP_DEBUG=true`.
6. **Klik ganda**: tidak ada penjaga; user menekan berulang karena tak ada indikasi. Beberapa transisi sudah idempoten (cek status/`exists`), tetapi belum semuanya (lihat X5).
**Rancangan:** **T1** — ukur dulu di UAT, **profiler request** (default mati; dicatat ke `storage/logs/perf.log`) + `perf:report`, panduan `.env` UAT (`LOG_LEVEL=warning`; catatan: `info` tidak menurunkan `Log::info`), opcache dev aktif. Penurunan `Log::info` di observer **ditunda ke T7** dan hanya untuk titik yang terbukti mahal. **T7** — anggaran query per aksi (tes gagal bila melampaui, mis. Tandai Selesai ≤ 60), hapus N+1 (batch stok, eager-load), hapus "muat semua" (usulan 17), pindahkan pekerjaan berat non-kritis (PDF, agregasi laporan) ke antrean dengan status "sedang diproses", **penjaga klik ganda** (tombol disabled + spinner, dan kunci server `lockForUpdate` + cek status ulang), pertimbangkan cache/sesi ke Redis/file di UAT/produksi.
**Selesai bila:** aksi kunci ≤ target (mis. 3 detik pada data UAT), anggaran query lolos di CI, klik ganda tidak menimbulkan efek ganda (uji dua panggilan berturut-turut = satu efek).

#### Usulan 20 — Bersihkan master data customer

**Kondisi kode:** `customers.code` punya indeks unik, tetapi **nama tidak diperiksa duplikat**; form "buat customer" di dalam SO/Quotation adalah form terpisah dari `CustomerResource` (validasi bisa berbeda); impor legacy (`LegacyInventoryMigrationService`) memakai kode legacy apa adanya (yang di sistem lama sering berisi NIK) dan menyimpan `legacy_id`; **tidak ada** perintah audit/gabung. Tabel yang mereferensikan customer: `quotations`, `sale_orders`, `customer_receipts`, `customer_returns`, `account_receivables`, `other_sales`, plus `deposits` (polimorfik) dan snapshot nama pada invoice.
**Rancangan**
- **T1 (read-only):** `customers:audit-duplicates` — normalisasi nama (huruf kecil, hapus PT/CV/tanda baca/spasi ganda), NPWP/NIK, telepon, alamat → CSV grup duplikat + jumlah transaksi tiap kandidat + saran survivor (yang paling banyak transaksi). Jalankan di UAT dan serahkan ke bisnis untuk memilih survivor.
- **T4:** `customers:merge {survivor} {dupIds…}` (dry-run default; transaksi DB; memindahkan semua referensi di atas; saldo deposit dijumlahkan; catatan asal di `keterangan`; duplikat di-soft-delete; CSV cadangan sebelum ubah; jendela pemeliharaan). Konvensi kode baru `CUST-00001` (via T4), kode lama disimpan di kolom `legacy_code`; kode berpola NIK diganti dan NIK dipindahkan ke `nik_npwp` bila kosong (**NIK sebagai kode = data pribadi tercetak di dokumen** — rekomendasi hapus dari kode). Pencegahan: satu `CustomerService::create` dipakai semua form (dedup berdasarkan nama ternormalisasi + NPWP; blokir yang identik, peringatkan yang mirip).
**Selesai bila:** audit menunjukkan 0 grup duplikat yang belum diputuskan; semua transaksi grup gabungan menunjuk survivor; saldo AR/deposit per customer tidak berubah total (uji sebelum/sesudah); pembuatan customer duplikat ditolak dari semua form.
**Butuh keputusan:** D14.

---

## 5. Temuan tambahan (di luar 20 usulan)

Ditemukan saat audit; semuanya dimasukkan ke roadmap agar tidak terlewat.

| ID | Temuan | Bukti | Dampak | Tahap |
|---|---|---|---|---|
| **X1** | **Reservasi stok yatim**: `qty_reserved` tidak turun setelah DO `sent/completed` | Probe: stok 30 → kirim 12 → `qty_available` 18, `qty_reserved` 12 (stok bebas 6, seharusnya 18); pelepasan hanya di kode mati & saat DO dihapus | Stok bebas terus menyusut → "stok tidak cukup" palsu; usulan 1 memperburuknya bila tidak diperbaiki lebih dulu | T2 (pertama) |
| **X2** | **Kode mati yang menyesatkan**: `SalesOrderService::confirm()`, `DeliveryOrderService::postDeliveryOrder/releaseStockReservations/validateStockAvailability`, status SO `confirmed` | Tidak ada pemanggil (kecuali komentar) | Pembaca kode mengira ada validasi/pelepasan; sumber X1 dan usulan 2 | T2 (pakai ulang yang berguna, hapus sisanya) |
| **X3** | **Stok bebas lintas cabang** tidak memakai kebijakan cabang | `InventoryStock::freeQtyFor` tanpa filter cabang; `Cabang.lihat_stok_cabang_lain` tidak dibaca | SO cabang A "mengandalkan" stok cabang B | T2 (D3) |
| **X4** | **Stok fisik bisa negatif** | `StockMovementObserver` menjumlah delta tanpa batas bawah; cek stok hanya ada di kode mati | Pengiriman tanpa stok nyata tercatat | T2 (D15) |
| **X5** | **Idempotensi klik ganda belum terjamin di semua aksi** | Usulan 19; transisi via `update()` langsung tanpa kunci baris | Dokumen/gerakan stok ganda pada klik berulang | T2 (transisi DO) + T7 (aksi lain) |
| **X6** | **Status `canceled` dipakai kueri tetapi tidak ada di enum invoice** | `where('status','!=','canceled')` di `SalesInvoiceResource`, `CreateSalesInvoice` | Filter tak berguna; menutupi ketiadaan pembatalan | T5 |
| **X7** | **Form Invoice manual dari SO hanya memuat SO `completed`** | `SalesInvoiceResource` ~148 | Pengiriman parsial (SO `partially_delivered`) tidak dapat ditagih manual dari form ini — hanya via invoice otomatis per DO; membingungkan | T1 (teks bantu) / T7 |
| **X8** | **Policy DO memakai peran "Super Sales" yang tidak ada di `RoleSeeder`** | `DeliveryOrderPolicy` (baris 16, 34, 63, …) | Cabang kondisi untuk peran itu tak pernah tercapai; peran Sales dibatasi hanya pada SO buatannya | T3 (rapikan bersama usulan 8) |
| **X9** | **Tooling uji tidak dapat menjalankan suite penuh dalam satu proses** | `IncreaseMemoryLimit` middleware & 4 API controller memaksa `ini_set('memory_limit','512M')` → menimpa `-d memory_limit=-1`; ada tes yang bergantung urutan/ID keras (`UATUIUXAuditVerificationTest` memakai `cabang_id => 1`) | Regresi sulit dideteksi; hasil suite bergantung urutan | T0 |
| **X10** | **Form "buat customer" di SO/Quotation punya aturan sendiri** | `SaleOrderResource` `createOptionForm` (baris ~640) | Sumber duplikat customer (usulan 20) | T4 |

---

## 6. Keputusan yang saya butuhkan

Bila tidak dijawab, saya memakai **rekomendasi** (kolom kanan) — sama seperti pola audit sebelumnya. Yang bertanda 🧾 perlu akuntan/pajak.

| ID | Keputusan | Pilihan | Rekomendasi |
|---|---|---|---|
| D1 | Kapan stok mulai ditahan? | SO Approved / DO Approved (sekarang) | **SO Approved**, dipindahkan ke DO saat DO disetujui |
| D2 | SO stok kurang | Blokir + jalur backorder beralasan / hanya peringatan | **Blokir approve kecuali backorder beralasan (Sales Manager+)**; draft tetap boleh disimpan dengan peringatan |
| D3 | Stok lintas cabang | Hanya cabang SO / semua / ikut `lihat_stok_cabang_lain` | **Ikut `lihat_stok_cabang_lain`** (default: hanya cabang SO) |
| D4 | Tahapan DO | Pertahankan kunci status + ganti label / tambah status baru | **Pertahankan `approved` berlabel "Siap Kirim"** (tanpa migrasi data) + aksi Kirim & Diterima |
| D5 | Jadwal "Tandai Selesai" dari *pending* | Boleh / harus Mulai dulu | **Harus Mulai Pengiriman dulu** |
| D6 | Ambang & peran approval | Tetap Rp10 jt / ubah; berlaku Quotation? | **Awal = perilaku sekarang, berlaku juga untuk Quotation, dapat diatur di layar** |
| D7 | Kebijakan limit kredit | Kredit saja / semua tipe; limit 0 | **Kredit = blokir; COD/Bebas = informasi; limit 0 pada Kredit = tidak diizinkan**; angka 999.999.999.999 ditinjau bisnis |
| D8 | Buka kunci dokumen | Ada "buka kunci" / hanya jalur koreksi | **Hanya jalur koreksi** (Nota Kredit, pembatalan penerimaan, revisi); tanpa buka kunci untuk dokumen berjurnal |
| D9 | Akun yang diatur di Setting Akuntansi & siapa yang mengubah | daftar kunci; peran | **11 kunci (lihat usulan 9); Finance Manager + Super Admin, tercatat** 🧾 |
| D10 | Nota Kredit: pajak & refund | PPN Keluaran dibalik + nota retur pajak; invoice terbayar → deposit / refund tunai | **Balik PPN di jurnal; sisa terbayar → Deposit Customer; refund tunai lewat pengajuan berapproval** 🧾 |
| D11 | Format nomor | mis. `{PREFIX}/{KODECABANG}/{YYMM}/{SEQ}`; reset bulanan/tahunan; per cabang/global | **`{PREFIX}-{KODECABANG}-{YYMM}-{SEQ4}`, reset bulanan, per cabang**; nomor lama tetap |
| D12 | Sumber identitas kop (nama legal, NPWP, alamat, rekening) | Per Cabang / global | **Per Cabang, fallback global** 🧾 |
| D13 | Standar aksi UI | Tombol langsung / menu | **2–3 aksi utama langsung + menu "Lainnya"** |
| D14 | Gabung customer ganda | Siapa pilih survivor; jendela waktu | **Bisnis memilih dari CSV audit; dijalankan di luar jam kerja dengan cadangan** |
| D15 | Stok negatif saat Dikirim | Blokir / izinkan+peringatan | **Blokir** (kecuali izin khusus) |
| D16 | No. Faktur Pajak wajib? | Wajib untuk PKP ber-PPN / peringatan saja | **Peringatan** sampai proses pajak siap 🧾 |

### Keputusan (final) — dicatat 20 September 2026

Pemilik menjawab **"lanjut"** atas rencana T0/T1 (`docs/PLAN-T0-T1-PENJUALAN.md` §9) sehingga rekomendasi berikut **berlaku**:

| Kode | Keputusan | Status |
|---|---|---|
| A-1 | Commit revert mask Indonesia + perbaikan audit + dokumen (3 commit) lalu cabang `feat/penjualan-t1-quick-wins` | ✅ dilaksanakan |
| A-2 | Tes usang `RekonsiliasiBankPage*` dibiarkan di baseline | ✅ |
| K-A = D16 | No. Faktur Pajak: **peringatan saja** (boleh kosong; format & duplikat divalidasi bila diisi) | ✅ |
| K-B | Referensi wajib untuk Transfer/Giro/Cheque; bukti opsional (daftar menandai "tanpa bukti") | ✅ |
| K-C | Aksi "Isi No. Faktur Pajak" memakai izin `update invoice` (izin khusus di T3) | ✅ |
| K-D | Ekspor PPN Keluaran **ditunda** (T5/T6) | ✅ |
| K-E | `LOG_LEVEL` UAT = `warning` (produksi `error`) | ✅ |
| K-F | Ambang limit kredit mencurigakan ≥ Rp1.000.000.000 | ✅ |
| **D1–D5** | Rekomendasi **disetujui** (21 September 2026: "setuju D1-D5") — reservasi saat SO Approved; blokir approve stok kurang kecuali backorder beralasan; stok lintas cabang ikut `lihat_stok_cabang_lain`; DO "Siap Kirim" + aksi Kirim/Diterima; jadwal wajib Mulai dulu | ✅ |
| **D15** | Stok negatif saat Dikirim: **blokir** (pengecualian beralasan Owner/Super Admin) — disetujui 21 September 2026 ("setuju") | ✅ |
| **D17–D23** | Keputusan turunan T2 (`docs/PLAN-T2-STOK-PENGIRIMAN.md` §2.2): seluruh rekomendasi **disetujui** 21 September 2026 ("setuju") | ✅ |
| **D6–D14** | Rekomendasi belum dikonfirmasi; wajib dijawab sebelum T3/T4 | ⏳ menunggu |

---

## 7. Roadmap bertahap T0–T8

Prinsip yang sama dengan Fase 1–6: **migrasi bersifat aditif**, tiap perubahan data lama lewat perintah **dry-run → `--apply`** dengan CSV cadangan, perilaku baru di belakang **flag konfigurasi** (`config/sales.php`) bila berisiko, tes baru + regresi terkait, dan bagian "Status pelaksanaan" pada dokumen ini setelah tiap tahap.

### T0 — Baseline, keputusan & tooling uji (0,5–1 hari)
- Jawab D1–D16 (atau setujui rekomendasi).
- **X9**: `IncreaseMemoryLimit`/API controller hanya *menaikkan* batas bila lebih rendah dari 512M (tidak menurunkan `-1`); skrip `tests/run-chunked.sh` (potongan 30 berkas) untuk suite penuh; perbaiki tes bergantung urutan/ID keras.
- Tetapkan baseline "gagal-yang-sudah-ada" (±210 tes; daftar per nama) untuk perbandingan tiap tahap.
- Kumpulkan diagnostik lingkungan UAT (T0.6); profiler & `customers:audit-duplicates` (T1) dijalankan di UAT setelah selesai agar keputusan D14 & T7 berbasis data.
- **Selesai bila:** suite penuh dapat dijalankan tuntas; daftar baseline tersimpan; keputusan tercatat.

### T1 — Quick wins (3–4 hari) — usulan 10, 13, 15a, 18a, 19a, 20a, X7
1. **15a** `lang/id/{validation,auth,pagination,passwords}.php` (+ nama atribut field utama).
2. **13** No. Faktur Pajak di form/View/daftar/PDF invoice penjualan + filter "belum ada" (D16).
3. **10** Kolom `payment_reference`, `proof_path`, `bank_name` pada penerimaan; wajib untuk non-tunai; peringatan duplikat; tampil di View/kwitansi.
4. **18a** Label pilihan SO/DO/Quotation memuat customer+tanggal+nilai; nilai DO via `LineAmounts` (`DeliveryOrder::valueBreakdown()`); kolom "Sisa Qty" di daftar SO (batch).
5. **19a** profiler request + `perf:report` (default mati); panduan `.env` UAT (`LOG_LEVEL=warning`); opcache dev aktif. (Penurunan `Log::info` → T7 berbasis data ukur.)
6. **20a** `customers:audit-duplicates` (read-only) + `customers:audit-credit-limit` (read-only).
7. **X7** Teks bantu pada form Invoice: kapan invoice manual vs otomatis per DO.
- **Migrasi:** `customer_receipts` (3 kolom). **Tes baru:** ±25. **Risiko:** rendah.
- **Selesai bila:** semua butir di atas lolos tesnya; tidak ada regresi baru terhadap baseline.

### T2 — Stok & pengiriman (8–10 hari) — usulan 1, 2, 3 + X1–X5 — *jalur kritis*
Urutan di dalam tahap **wajib** (tiap langkah membuat langkah berikutnya aman):
1. **X1 perbaikan + rekonsiliasi**: konsumsi reservasi saat `sent`, lepas saat `closed/reject/failed/canceled`; `stock:reconcile-reservations` (dry-run/apply, CSV); invarian "Σ qty_reserved = Σ reservasi aktif" sebagai tes dan bagian `sales:integrity-check`.
2. **X2** pakai ulang bagian berguna `confirm()` dalam `StockAvailability`/`StockReservations`; hapus kode mati (dengan tes yang mengunci perilaku).
3. **X3/X4 + D3/D15**: `StockAvailability` (batch, kebijakan cabang) + larangan stok negatif pada `sent`.
4. **Usulan 2**: peringatan di form Filament & React, `warnings[]` di API, blokir approve, jalur backorder (kolom `is_backorder`, `backorder_reason`, `backorder_approved_by/at`), badge daftar tanpa N+1.
5. **Usulan 1**: reservasi saat SO approved (per item×gudang), pemindahan ke DO saat DO disetujui, pelepasan otomatis pada cancel/close/reject.
6. **Usulan 3**: `DeliveryOrderTransitions` (satu pintu, matriks transisi, kunci baris, sinkron item+log), aksi DO "Kirim" & "Konfirmasi Diterima", jadwal mengikuti (D5), label "Siap Kirim", `delivery-orders:resync-item-status`.
7. **X5** uji idempotensi (dua panggilan = satu efek) pada semua transisi DO/jadwal.
- **Migrasi:** kolom backorder pada `sale_orders`; kolom penanda pada `stock_reservations` (`consumed_at`/`released_at` bila diperlukan agar audit stok dapat dilacak). **Risiko: tinggi** (menyentuh stok & jurnal) → flag `sales.reserve_on_so_approve` (default mati sampai UAT), dan wajib menjalankan rekonsiliasi (langkah 1) sebelum flag dihidupkan.
- **Tes baru:** ±60, termasuk skenario probe §2 sebagai regresi permanen dan uji konkurensi dua SO memperebutkan stok terakhir.
- **Selesai bila:** skenario §2 menghasilkan: SO 35/stok 30 ditolak/backorder; setelah kirim 12 → `qty_reserved` sesuai; item DO `sent/received`; tak ada selisih pada `sales:integrity-check`.

### T3 — Kontrol internal & Setting Akuntansi (7–9 hari) — usulan 6, 7, 8, 9, X8
1. **6** `approval_rules` + `canApprove()` generik (Quotation, SO, dan siap untuk Nota Kredit/refund); penegakan di service; override beralasan.
2. **7** kebijakan kredit (D7), paparan = AR + SO belum ditagih, Info Customer semua tipe (endpoint ringkas), `customers:audit-credit-limit` (dari T1) dipakai bisnis untuk membenahi angka.
3. **8** `DocumentLock` + matriks; DO tabel, Penerimaan (pembatalan dengan jurnal balik), Jadwal, Retur; rapikan peran fiktif (X8); tes berbasis data (dokumen × status).
4. **9** Halaman Pengaturan Akuntansi + `AccountingSettings` (validasi akun detail/tipe), migrasi bertahap titik hard-code di alur penjualan; hapus pilihan COA bebas untuk akun internal; perluas `master:readiness`.
- **Migrasi:** `approval_rules`, `accounting_settings` (atau key AppSetting). **Risiko:** sedang (mengubah siapa boleh apa) → nilai awal meniru perilaku sekarang.
- **Selesai bila:** matriks approval & kunci lolos; mengganti akun di layar mengubah jurnal berikutnya; tidak ada kode akun hard-code di alur penjualan (pemindai).

### T4 — Penomoran terpusat & master customer (5–7 hari, bisa paralel dengan T3) — usulan 12, 20b–d, X10
1. **12** `DocumentNumberService` + `document_sequences` (atomik), format D11, invoice memakai kode pajak/non-pajak Cabang; migrasikan SO, Quotation, DO, Jadwal, SJ, Invoice, Retur (+ Nota Kredit T5, Deposit); benih dari MAX; `documents:check-numbering`.
2. **20b** `customers:merge` (dry-run default) + cadangan CSV + uji sebelum/sesudah (Σ AR, deposit, jumlah dokumen).
3. **20c** kode `CUST-00001`, `legacy_code`, pemindahan NIK; **20d** `CustomerService::create` tunggal (dedup) dipakai semua form (X10).
- **Migrasi:** `document_sequences`, `customers.legacy_code`. **Risiko:** sedang-tinggi (data master hidup) → hanya dijalankan setelah CSV audit disetujui bisnis, di luar jam kerja, dengan cadangan.
- **Selesai bila:** dua proses paralel tidak menghasilkan nomor kembar; nomor lama tak berubah; audit duplikat = 0 grup tak terputuskan; total AR/deposit per customer identik sebelum–sesudah gabung.

### T5 — Koreksi finansial: Nota Kredit & retur uang (8–10 hari) — usulan 5, 4, X6
1. Tabel & model `credit_notes/_items`, nomor (T4), layanan penerbitan + jurnal balik, status `canceled`, alasan wajib, approval (T3), audit.
2. Pembatalan penuh & koreksi sebagian; penanganan invoice yang sudah dibayar (deposit/refund; D10); AR tidak pernah negatif.
3. Retur: keputusan "Refund / Nota Kredit" (kuantitas ≤ sisa, harga bersih), jurnal Dr Retur+PPN / Cr Piutang, pembalikan stok/HPP yang sudah ada tidak digandakan.
4. Laporan (Fase 6) dan `credit`/AR mengenali Nota Kredit; laporan rekonsiliasi PPN memasukkan nota.
- **Migrasi:** 2 tabel baru + kolom di retur; **Risiko: tinggi** (akuntansi & pajak) 🧾 → butuh peninjauan akuntan atas jurnal contoh sebelum dihidupkan; flag `sales.credit_notes_enabled`.
- **Selesai bila:** skenario "invoice salah → nota kredit → invoice pengganti" menyeimbangkan jurnal/AR/laporan; retur parsial mengurangi piutang proporsional; tes E2E lolos.

### T6 — Dokumen cetak (4–5 hari) — usulan 11
Partial kop/TTD/rekening/meta, `DocumentPrintBuilder`, template Invoice, DO, Kwitansi, Nota Kredit, Retur; watermark status; tes isi PDF; pemeriksaan visual A4 (portrait & landscape) pada contoh data nyata; data NPWP/rekening (D12).
- **Selesai bila:** semua elemen wajib ada pada tiap dokumen (tes teks PDF) dan disetujui secara visual oleh Anda.

### T7 — UX konsisten & performa (9–12 hari) — usulan 14, 15b, 16, 17, 18b, 19b, X5
1. **17** endpoint & macro pencarian sisi-server; ganti `Customer::all()+preload` dan "muat semua" API; `SearchableSelect` remote; pemindai pola.
2. **19b** anggaran query per aksi (tes), hapus N+1, penjaga klik ganda (UI + server), pekerjaan berat ke antrean; keputusan sesi/cache di UAT.
3. **16** `ImpactPreview` (preview = eksekusi) untuk aksi berefek; modal berdampak; konfirmasi untuk Invoice/Penerimaan/Nota Kredit.
4. **14** helper `DocumentActions` + penerapan ke View & tabel dokumen (D13).
5. **15b** `StatusLabels` terpusat, perbaikan 25 kolom, pemindai bahasa.
6. **18b** info tambahan (stok, limit, sisa) di tempat yang relevan.
- **Risiko:** sedang (banyak file kecil) → dikerjakan per modul dengan tes pemindai agar tidak tumbuh lagi.
- **Selesai bila:** anggaran query lolos di CI; pemindai bahasa & pola lolos; aksi kunci memenuhi target waktu pada UAT.

### T8 — Penutupan (2–3 hari)
- Tes E2E lintas T1–T7 (perluas `SalesFlowEndToEndFase1to6Test`): Quotation → SO (reservasi) → DO (Kirim/Diterima) → Invoice (faktur pajak) → Penerimaan (referensi+bukti) → Retur/Nota Kredit → Laporan, semua angka konsisten.
- `sales:integrity-check` (terjadwal di `routes/console.php`): invarian stok/reservasi, AR = Σ(invoice − nota kredit − penerimaan), jurnal seimbang, nomor unik, invoice terbit tanpa jurnal, DO `sent` tanpa gerakan stok — semuanya harus 0.
- Skrip UAT manual (angka contoh), dokumen status pelaksanaan, catatan rollout/rollback, ringkasan untuk PR.

---

## 8. Matriks keterlacakan (checklist anti-terlewat)

Setiap butir usulan dipecah menjadi deliverable yang dapat dicentang. **Tidak ada usulan yang dianggap selesai sebelum semua deliverable-nya tercentang beserta tesnya.**

| # | Deliverable | Tahap | ☐ |
|---|---|---|---|
| 1 | X1: konsumsi/lepas reservasi + `stock:reconcile-reservations` | T2 | ☐ |
| 1 | Reservasi per item×gudang saat SO approved | T2 | ☐ |
| 1 | Pemindahan reservasi SO→DO (tanpa hitung ganda) | T2 | ☐ |
| 1 | Pelepasan saat cancel/close/reject (+ opsi kedaluwarsa) | T2 | ☐ |
| 2 | `StockAvailability` (batch, kebijakan cabang) | T2 | ☐ |
| 2 | Peringatan di form Filament & React, `warnings[]` API | T2 | ☐ |
| 2 | Blokir approve + backorder beralasan (kolom, izin, audit) | T2 | ☐ |
| 2 | Badge daftar SO tanpa N+1 | T2 | ☐ |
| 3 | `DeliveryOrderTransitions` (matriks, kunci, log, item sync) | T2 | ☐ |
| 3 | Aksi DO "Kirim" & "Konfirmasi Diterima"; label "Siap Kirim" | T2 | ☐ |
| 3 | Jadwal mengikuti (Mulai dulu; selesai hanya dari on_the_way) | T2 | ☐ |
| 3 | `delivery-orders:resync-item-status` | T2 | ☐ |
| 3 | Larangan stok negatif saat `sent` | T2 | ☐ |
| 4 | Keputusan retur "Refund / Nota Kredit" + batas qty | T5 | ☐ |
| 4 | Jurnal retur Dr Retur+PPN / Cr Piutang (tanpa gandakan HPP/stok) | T5 | ☐ |
| 4 | Refund tunai lewat pengajuan berapproval | T5 | ☐ |
| 5 | Tabel/model/nomor Nota Kredit; status `canceled` | T5 | ☐ |
| 5 | Pembatalan penuh + koreksi sebagian + jurnal balik | T5 | ☐ |
| 5 | Invoice terbayar → deposit/refund; AR tak negatif | T5 | ☐ |
| 5 | Laporan & filter mengenali `canceled`/nota kredit | T5 | ☐ |
| 6 | `approval_rules` + `canApprove()` generik untuk Quotation & SO | T3 | ☐ |
| 6 | Penegakan di service + override beralasan tercatat | T3 | ☐ |
| 7 | Kebijakan kredit per tipe + limit 0 ≠ tak terbatas | T3 | ☐ |
| 7 | Paparan (AR + SO belum ditagih) di blokir & peringatan | T3 | ☐ |
| 7 | Info Customer semua tipe (piutang, umur, SO terbuka) | T3 | ☐ |
| 7 | `customers:audit-credit-limit` | T1 | ☑ |
| 8 | `DocumentLock` + matriks; tes dokumen × status | T3 | ☐ |
| 8 | DO tabel, Penerimaan (pembatalan), Jadwal, Retur terkunci | T3 | ☐ |
| 8 | X8: rapikan peran "Super Sales" | T3 | ☐ |
| 9 | Halaman Pengaturan Akuntansi + `AccountingSettings` | T3 | ☐ |
| 9 | Migrasi titik hard-code alur penjualan (pemindai) | T3 | ☐ |
| 9 | Hapus COA bebas (Deposit, Penyesuaian, Penjualan Lain, item SO/invoice) | T3 | ☐ |
| 9 | `master:readiness` diperluas | T3 | ☐ |
| 10 | Referensi + bukti + bank + peringatan duplikat | T1 | ☑ |
| 11 | Partial kop/TTD/rekening + `DocumentPrintBuilder` | T6 | ☐ |
| 11 | Templat Invoice, DO, Kwitansi, Nota Kredit, Retur | T6 | ☐ |
| 11 | Watermark DRAFT/DIBATALKAN + tes isi PDF | T6 | ☐ |
| 12 | `DocumentNumberService` + `document_sequences` (atomik) | T4 | ☐ |
| 12 | Migrasi semua generator + kode invoice pajak/non-pajak Cabang | T4 | ☐ |
| 12 | `documents:check-numbering` | T4 | ☐ |
| 13 | Faktur pajak: form/View/daftar/PDF/filter/ekspor | T1 | ☑ *(ekspor ditunda — K-D)* |
| 14 | `DocumentActions` + penerapan View & tabel | T7 | ☐ |
| 15 | `lang/id/*` (validation dkk.) | T1 | ☑ |
| 15 | `StatusLabels` terpusat + 25 kolom + pemindai bahasa | T7 | ☐ |
| 16 | `ImpactPreview` (preview = eksekusi) + modal berdampak | T7 | ☐ |
| 16 | Konfirmasi untuk Invoice/Penerimaan/Nota Kredit/Retur | T7 | ☐ |
| 17 | Endpoint pencarian sisi-server + macro Filament + React remote | T7 | ☐ |
| 17 | Hapus `Customer::all()+preload`, "muat semua" API, `limit(50)` quotation | T7 | ☐ |
| 18 | Label SO/DO/Quotation informatif; nilai DO via `LineAmounts`; kolom Sisa Qty | T1 | ☑ |
| 18 | Info stok/limit/sisa di tempat relevan | T7 | ☐ |
| 19 | Profiler request + `perf:report` + panduan env/opcache | T1 | ☑ |
| 19 | Anggaran query, hapus N+1, antrean, penjaga klik ganda | T7 | ☐ |
| 20 | `customers:audit-duplicates` (read-only) | T1 | ☑ |
| 20 | `customers:merge` + cadangan + uji sebelum/sesudah | T4 | ☐ |
| 20 | Kode `CUST-…`, `legacy_code`, NIK keluar dari kode | T4 | ☐ |
| 20 | `CustomerService::create` tunggal (X10) | T4 | ☐ |
| X1–X10 | Lihat §5 (tiap ID terpetakan ke tahap) | T0–T7 | ☐ |
| — | `sales:integrity-check` terjadwal + E2E lintas tahap + skrip UAT | T8 | ☐ |

**Cek silang:** 58 baris deliverable; setiap usulan (1–20) muncul minimal sekali, dan setiap temuan tambahan X1–X10 terpetakan ke tahap.

---

## 9. Strategi pengujian, rollout & rollback

**Pengujian (memperluas praktik Fase 1–6):**
- Tes fitur per deliverable + **uji invarian** yang dijalankan juga oleh `sales:integrity-check`: (I1) `qty_reserved` = Σ reservasi aktif; (I2) `qty_available` = Σ gerakan stok; (I3) AR = Σ invoice − Σ nota kredit − Σ penerimaan; (I4) semua jurnal seimbang; (I5) nomor dokumen unik; (I6) tiap DO `sent`+ punya gerakan stok, tiap invoice terbit punya jurnal.
- **Uji mutasi** untuk penjaga kritis (rusakkan penjaga → tes harus gagal), seperti audit sebelumnya.
- **Regresi bertahap**: bandingkan nama tes gagal terhadap baseline T0 setelah tiap tahap (memakai skrip potongan dari T0); tidak boleh ada kegagalan baru.
- **Anggaran query/waktu** sebagai tes (T7) agar performa tidak memburuk diam-diam.
- **Pemindai statis** (tes): kode akun hard-code, string status mentah, `::all()+preload`, `limit(50)`, dokumen tanpa entri di matriks kunci.

**Rollout:** per tahap — migrasi aditif → deploy kode dengan flag mati → perintah rekonsiliasi/backfill `--dry-run` ditinjau → `--apply` → UAT dengan skrip → hidupkan flag. T2/T5 wajib melewati akuntan/pemilik proses sebelum flag dihidupkan.
**Rollback:** flag dimatikan (perilaku lama kembali); migrasi punya `down()`; perintah data selalu menulis CSV cadangan; gabung customer & rekonsiliasi reservasi punya berkas pemulihan.

---

## 10. Risiko & asumsi

| Risiko / asumsi | Mitigasi |
|---|---|
| Data UAT/produksi berbeda dari dev (duplikat, limit, kecepatan) | Semua audit data berupa perintah read-only yang dijalankan di UAT sebelum keputusan (T0/T1) |
| T2 menyentuh stok & jurnal (dampak tertinggi) | Urutan wajib (X1 dulu), flag, rekonsiliasi sebelum aktif, ±60 tes + uji konkurensi |
| Nota Kredit menyentuh akuntansi/pajak | Peninjauan akuntan (D10) dengan jurnal contoh sebelum flag hidup |
| Banyak butir kecil di T7 (UI) | Per modul + pemindai agar tidak tumbuh lagi |
| Estimasi 47–61 hari mengasumsikan satu pengembang dan keputusan D1–D16 cepat | Tahap ∥ (T1/T4) dan rekomendasi default mengurangi waktu tunggu |
| Usulan 14 & 19 diverifikasi dari kode, bukan dari tampilan/produksi | T1 menyertakan pengukuran di UAT; tangkapan layar pola tombol diminta bila standar D13 berbeda |
| Pekerjaan paralel lain di repo (`docs/audit_dan_rencana_…`, perubahan `AppSettings`) | Periksa konflik `git status` di awal tiap tahap |

**Batasan yang saya tidak dapat verifikasi:** angka respons 8–15 detik dan data ganda customer di lingkungan UAT Anda; format resmi faktur pajak/nota retur (perlu akuntan/pajak); kebijakan bisnis pada D1–D16.


---

## Status pelaksanaan — T0 & T1 (20 September 2026)

**Cabang:** `feat/penjualan-t1-quick-wins` (13 commit di atas `chore/cleanup-artifacts`; 3 commit pembuka T0.1 di cabang itu). **Gerbang T1 lulus:** suite penuh lewat runner terpotong —
3.061 tes · 2.853 lolos · **201 gagal = seluruhnya sudah ada di baseline (0 kegagalan baru)** · 7 dilewati · 0 crash · 24,4 menit (`tests/baseline-failures.txt`).

### T0 — selesai
| Tugas | Hasil |
|---|---|
| T0.1 | 3 commit: revert mask (format Indonesia), perbaikan audit ulang Fase 1–6, dokumen; cabang kerja dibuat |
| T0.2 | Keputusan T1 (A-1, A-2, K-A…K-F, D16) tercatat; **D1–D15 masih menunggu konfirmasi sebelum T2/T3** |
| T0.3 | `App\Support\MemoryLimit::raiseTo()` (hanya menaikkan) menggantikan 5 `ini_set('memory_limit','512M')`. Suite dalam **satu proses** kini selesai tuntas (sebelumnya crash memori) |
| T0.4 | `scripts/run-tests-chunked.php` (JUnit XML, isolasi crash per berkas, `--db`, `--compare`, `--write-baseline`) + `scripts/compare-test-failures.php` + `composer test:chunked/test:compare` |
| T0.5 | 13 tes usang diperbaiki (fixture) — menghasilkan regresi baru yang berharga (mis. jurnal ongkir 43 assertion). Baseline dikunci 201 + analisis di `docs/BASELINE-TES.md` |
| T0.6 | Perintah diagnostik UAT untuk Anda jalankan (lihat di bawah) |

### T1 — selesai (8 tugas, ±110 tes baru)
| Tugas | Ringkas | Tes baru |
|---|---|---|
| T1.1 `lang/id` | validation/auth/passwords/pagination lengkap + atribut penjualan | 14 |
| T1.4 nilai DO | `DeliveryOrderValuation` (LineAmounts) dipakai observer invoice, `DeliveryOrder::total`, label pilihan, PDF DO (2 desimal); `DocumentLabels` | 8 |
| T1.5 sisa qty | kolom "Sisa Belum Dikirim" di daftar SO, batch (jumlah query tetap) | 4 |
| T1.8 teks bantu | pilihan SO form Invoice menjelaskan SO Selesai vs invoice otomatis per DO | 2 |
| T1.2 faktur pajak | aturan 16 digit + unik, aksi "Isi No. Faktur Pajak" (invoice `unpaid` tidak bisa diedit via form), kolom, filter, View, PDF; jurnal/piutang terbukti tak berubah | 11 |
| T1.3 referensi & bukti | referensi wajib Transfer/Giro/Cheque, bukti privat + rute berotorisasi, peringatan duplikat, kolom & filter "tanpa bukti"; migrasi aditif | 14 |
| T1.6 profiler | `PERF_PROFILE` (default mati), `perf:report`, panduan `.env`/opcache, opcache dev aktif | 13 |
| T1.7 audit customer | `customers:audit-duplicates` + `customers:audit-credit-limit` (read-only, terbukti oleh sidik jari data + uji mutasi) | 14 (+8 unit) |

Semua penjaga kritis diuji mutasi (dirusak → tes gagal → dipulihkan).

### Penyimpangan dari rencana & temuan baru
- **Middleware profiler dipasang global**, bukan di grup `web`: rute panel Filament memakai daftar middleware panel sendiri sehingga grup `web` tidak menjangkau halaman panel (ditemukan tes rute nyata).
- **Item DO tanpa tautan item SO bernilai Rp0** karena relasi `saleOrderItem` memakai `withDefault()` (model kosong → harga 0). Ini kemungkinan besar sumber "Rp0" pada UAT. Perilaku invoice otomatis **sengaja tidak diubah** (tidak menebak harga jual); kini ditandai `⚠ ada item tanpa tautan SO` di label + peringatan log. Perlu diputuskan bisnis bila ada DO seperti itu di UAT.
- **Tes Fase 5A & e2e** diberi `payment_reference` (aturan baru: referensi wajib untuk Transfer).
- Enum status invoice tidak memuat `canceled` (X6) — kunci aksi faktur untuk status itu diuji pada modelnya; enum ditambahkan di T5.
- PDF DO kini menampilkan uang **2 desimal** (sebelumnya 0) agar sama dengan invoice; kwitansi belum tersambung ke penerimaan → T6; ekspor PPN Keluaran ditunda (K-D).
- Satu kegagalan sporadis tunggal pada `CustomerReceiptFeatureTest::can allocate payment to single invoice` teramati sekali dan tidak dapat direproduksi dalam 13 percobaan berikutnya (termasuk gerbang penuh) — **dipantau**; jika muncul lagi, cek status static `CustomerReceiptObserver`.

### Yang perlu Anda lakukan
1. **`php artisan migrate`** di dev/UAT (satu migrasi baru: `2026_09_20_210000_add_reference_and_proof_to_customer_receipts_table`).
2. **T0.6 — diagnostik UAT** (kirim keluarannya, tanpa rahasia):
   ```bash
   php artisan about --only=environment,cache,drivers
   php -i | grep -E "^opcache\.(enable|memory_consumption|validate_timestamps)|^memory_limit|^max_execution_time"
   ls -lh storage/logs | tail -5
   php artisan tinker --execute='foreach(["customers","products","sale_orders","delivery_orders","invoices","stock_movements","journal_entries","activity_log"] as $t){ echo str_pad($t,20), DB::table($t)->count(), PHP_EOL; }'
   ```
3. **UAT manual T1** — skrip di `docs/PLAN-T0-T1-PENJUALAN.md` §7 (12 langkah).
4. **Jalankan audit di UAT** dan kirim CSV/ringkasannya: `php artisan customers:audit-duplicates` dan `php artisan customers:audit-credit-limit` (read-only) → dasar keputusan D14.
5. **Ukur performa:** `PERF_PROFILE=true` + `php artisan config:clear` di UAT beberapa hari → `php artisan perf:report --since=1d` → dasar T7.
6. **Konfirmasi D1–D15** sebelum T2 dimulai.

---

## Status pelaksanaan — T2 (21 September 2026)

**Cabang:** `feat/penjualan-t2-stok` (T2.0–T2.6, satu commit per tugas). Rincian tugas, penyimpangan, dan langkah Anda ada di **`docs/PLAN-T2-STOK-PENGIRIMAN.md` §11**.
Ringkas: usulan **1 (reservasi sejak SO Approved)**, **2 (peringatan + blokir stok kurang, Backorder)**, **3 (status item DO & satu pintu status)** dan temuan **X1–X5** dikerjakan di belakang flag `sales.stock.*` (default mati); D1–D5, D15, D17–D23 seluruhnya diterapkan. X11/X12 dicatat, tidak diubah. D6–D14 (T3/T4) masih menunggu konfirmasi.
