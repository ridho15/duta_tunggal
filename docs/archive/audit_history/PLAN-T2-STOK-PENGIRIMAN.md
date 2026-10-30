# Rencana Pelaksanaan T2 — Stok & Pengiriman

> Dibuat 21 September 2026 · Turunan dari `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` §7 (T2) · Usulan **1, 2, 3** + temuan **X1–X5**.
> **Status: DILAKSANAKAN (21 September 2026)** — T2.0–T2.6 selesai di cabang `feat/penjualan-t2-stok`; lihat **§11 Status pelaksanaan**.
> Semua perilaku baru berada di balik flag `sales.stock.*` (default **mati**). Prasyarat: T0 & T1 selesai (baseline 201 gagal terkunci).

## Daftar Isi
1. [Tujuan dan ruang lingkup](#1-tujuan-dan-ruang-lingkup)
2. [Keputusan](#2-keputusan)
3. [Hasil pemeriksaan lanjutan (mengubah rencana)](#3-hasil-pemeriksaan-lanjutan-mengubah-rencana)
4. [Rancangan teknis](#4-rancangan-teknis)
5. [Urutan pengerjaan & estimasi](#5-urutan-pengerjaan--estimasi)
6. [Rincian tugas T2.1–T2.6](#6-rincian-tugas-t21t26)
7. [Tes, regresi, dan tes yang harus diubah](#7-tes-regresi-dan-tes-yang-harus-diubah)
8. [Skrip UAT manual T2](#8-skrip-uat-manual-t2)
9. [Rollout, flag, rollback](#9-rollout-flag-rollback)
10. [Risiko](#10-risiko)
11. [Status pelaksanaan](#11-status-pelaksanaan)

---

## 1. Tujuan dan ruang lingkup

**Tujuan:** stok yang dilaporkan **benar** (tidak bocor, tidak negatif diam-diam), barang **tidak terjual ganda**, dan pengiriman punya **satu pintu status** yang menyinkronkan stok, item, log, dan invoice — sehingga keluhan UAT #1, #2, #3 (dan #16 sebagian) terselesaikan dari akarnya.

| Di dalam ruang lingkup | Sengaja di luar (tahap lain / dicatat) |
|---|---|
| Buku besar reservasi tunggal + perbaikan reservasi yatim (X1) | Desain ulang stok per-rak (dipertahankan: jumlah per produk×gudang) |
| `StockAvailability` (stok bebas per cabang, D3) menggantikan `freeQtyFor` di jalur penjualan | Perbaikan `qty_reserved` milik Retur Pembelian (X11 — Pembelian; hanya dilaporkan) |
| Reservasi saat SO Approved dan perpindahannya ke DO (D1) | Kredit/persetujuan bertingkat (T3), penomoran (T4) |
| Peringatan stok + blokir approve + backorder beralasan (D2) | Modal konfirmasi berdampak (T7 — preview akan memakai logika yang dibangun di sini) |
| `DeliveryOrderTransitions`: satu pintu status DO, aksi Kirim/Diterima/Batalkan, label "Siap Kirim" (D4), jadwal wajib Mulai dulu (D5), larangan stok negatif saat Dikirim (D15) | Pengaturan `do_approval_required` yang ternyata tidak dipakai di mana pun (X12 — dicatat) |

---

## 2. Keputusan

### 2.1 Sudah diputuskan (21 September 2026: "setuju D1-D5")
| Kode | Keputusan |
|---|---|
| **D1** | Stok mulai ditahan saat **SO Approved**; reservasi **dipindahkan** ke DO saat DO disetujui (bukan ditambah) |
| **D2** | SO stok kurang: **blokir approve kecuali backorder beralasan** oleh Sales Manager+; menyimpan draft tetap boleh dengan peringatan |
| **D3** | Stok lintas cabang **mengikuti `Cabang.lihat_stok_cabang_lain`** (default: hanya gudang cabang SO) |
| **D4** | Kunci `approved` dipertahankan tetapi berlabel **"Siap Kirim"** (tanpa migrasi data) + aksi **Kirim** dan **Konfirmasi Diterima** |
| **D5** | Jadwal: **harus "Mulai Pengiriman" dulu**; "Tandai Selesai" hanya dari *Dalam Perjalanan* |

### 2.2 Keputusan turunan — **DISETUJUI 21 September 2026 ("setuju": D15, D17–D23 sesuai rekomendasi)**
Pemeriksaan lanjutan (§3) memunculkan keputusan turunan. Yang bertanda ⚠ **wajib** dijawab karena mengubah desain.

| Kode | Pertanyaan | Rekomendasi (default) |
|---|---|---|
| **D15** ⚠ | Stok negatif saat DO **Dikirim** | **Blokir** (pesan jelas; izin khusus Owner/Super Admin untuk mengecualikan dengan alasan). *Tidak termasuk dalam "D1-D5" sehingga saya minta konfirmasi eksplisit* |
| **D17** ⚠ | `qty_reserved` **tak terjelaskan** (di dev: 1.509 pada 54 baris stok, padahal `stock_reservations` kosong — berasal dari impor legacy dan Retur Pembelian) | **Hanya dilaporkan** (CSV per produk×gudang); tidak diubah otomatis. Pembersihan `--apply` per daftar yang Anda setujui |
| **D18** | DO gagal dikirim **setelah Dikirim** (stok sudah keluar) | **Stok dikembalikan** (gerakan masuk berjejak) dan reservasi tetap ada untuk penjadwalan ulang |
| **D19** | DO **Siap Kirim** yang harus dibatalkan (saat ini tidak ada jalannya di UI → reservasi terkunci) | Tambah aksi **Batalkan DO** (alasan wajib, izin `response delivery order`) dari status Siap Kirim → melepas reservasi |
| **D20** | Jadwal ditandai **Gagal** setelah Dalam Perjalanan (saat ini DO tetap *Sedang Dikirim*, stok keluar) | DO terkait otomatis **Pengiriman Gagal** (D18) |
| **D21** | Backorder: reservasi **parsial** (sebesar stok bebas saat ini) atau nol; tambah otomatis saat stok masuk | **Parsial + isi ulang otomatis** berurutan menurut waktu approve (FIFO) lewat `sales:top-up-reservations` (terjadwal) dan tombol "Coba reservasi ulang" |
| **D22** | Item SO **tanpa gudang** (API/React tidak mengisi gudang; 9 dari 130 item di dev) | Penempatan reservasi **otomatis**: alokasi item → gudang item → gudang cabang dengan stok bebas terbesar. Tidak mengubah data item |
| **D23** | Makna **Diterima** vs **Selesai** (invoice/jurnal terbit di mana) | `received` = customer menerima (tanggal + nama penerima dicatat); `completed` = dokumen selesai (**invoice & jurnal tetap terbit saat completed**, seperti sekarang). "Tandai Selesai" di jadwal melewati `received` otomatis (keduanya tercatat di log) |

D6–D14 (T3/T4) tidak menghambat T2.

---

## 3. Hasil pemeriksaan lanjutan (mengubah rencana)

Bukti dari kode dan data dev (read-only). Angka data UAT bisa berbeda — perintah rekonsiliasi akan mengukurnya.

| # | Temuan | Dampak pada rencana |
|---|---|---|
| **F1** | **Beberapa subsistem menulis `qty_reserved` tanpa baris `stock_reservations`**: Retur Pembelian (`PurchaseReturnAutomationService`: `decrement qty_available` **dan** `increment qty_reserved` — barang dihitung dua kali — tanpa pernah dilepas) dan impor legacy (`qty_reserved` disalin apa adanya dari sistem lama). Dev: Σ`qty_reserved` = 1.509 pada 54 baris; `stock_reservations` = 0 baris | Invarian "qty_reserved = Σ reservasi" **tidak boleh dipaksakan**. Rekonsiliasi (a) menghapus baris reservasi **yatim** dan (b) **melaporkan** selisih tak terjelaskan (D17). Retur Pembelian dicatat sebagai temuan **X11** |
| **F2** | **Semua validasi stok DO memakai `freeQtyFor`** (stok bebas = fisik − reservasi): `CreateDeliveryOrder:281`, `EditDeliveryOrder:177`, `DeliveryOrderResource:372/638/665`, `SaleOrder::hasInsufficientStock`, `SalesOrderService::approve:284`. Begitu SO menahan stok (D1), **reservasi SO itu sendiri akan menghalangi DO-nya** ("stok tidak mencukupi") | Semua titik itu wajib memakai stok bebas **+ reservasi milik SO itu** (`StockAvailability::freeForSaleOrder`) — bagian inti T2.2, bukan opsional |
| **F3** | `SaleOrderStockApprovalGuardTest` **sengaja** menetapkan "approval tetap berhasil walau stok kurang" (2 tes) | Perilaku lama dibalik oleh D2. Kedua tes ditulis ulang (mode flag mati = perilaku lama tetap lolos; mode baru: diblokir, dan lolos lewat backorder) — bukan dihapus |
| **F4** | `StockReservationObserver::updated` hanya menangani **kenaikan** kuantitas; penurunan memakai `decrement` manual di `DeliveryOrderService` | Buku besar tunggal menangani naik & turun; observer diperbaiki agar simetris |
| **F5** | DO **Siap Kirim** (`approved`) **tidak bisa dibatalkan** lewat UI: `closed` hanya dari `request_close`, dan `request_close` hanya dari draft/request_approve. `approved` hanya bisa "Pengiriman Gagal" | Aksi **Batalkan DO** (D19) |
| **F6** | `mark_delivery_failed` pada DO `sent` **tidak mengembalikan stok** dan tidak melepas/menyesuaikan reservasi; penjadwalan ulang (`failed → sent`) membuat gerakan `sales` **kedua** (tanpa penjaga idempoten) → stok terpotong ganda | D18 + penjaga idempoten di `createStockMovementsForShippingStart` |
| **F7** | Jadwal `set_failed` tidak menyentuh DO; form jadwal juga punya `Select('status')` sehingga status dapat diubah langsung | D20 + penjaga di observer jadwal (bukan hanya tombol) |
| **F8** | SO dibuat lewat **React/API tidak mengisi gudang**; alokasi multi-gudang hanya di form Filament dan **0 alokasi** di seluruh data dev | D22 (penempatan otomatis) |
| **F9** | Data dev: **32 baris stok bernilai negatif**; jalur "Ambil Sendiri" (`SaleOrderObserver::handleStockReductionForSelfPickup`) dan DO `sent` tidak memeriksa | D15 berlaku untuk DO `sent` **dan** penyelesaian pickup; data lama dilaporkan, tidak diubah |
| **F10** | `SalesOrderService::confirm()` (mati) + tes `StockReservationFlowTest` (4), `StockReservationServiceTest` (5), `SaleOrderMultiWarehouseTest` 10b adalah **spesifikasi asli** reservasi-saat-SO-dikonfirmasi; gagal karena fixture (baris stok ganda dari auto-stok produk) | Ditulis ulang di T2 sebagai tes reservasi resmi; `confirm()` dihapus setelah logikanya dipindah |
| **F11** | Pengurangan stok "Ambil Sendiri" sudah dirancang **melepas reservasi SO** setelah gerakan (`handleStockReductionForSelfPickup`) — cocok dengan D1 | Tidak berubah; hanya diberi penjaga stok negatif (D15) |
| **F12** | Status SO `confirmed` (5 baris) dan `received` (5) ada di data dev meski `confirm()` mati | Reservasi berlaku untuk `approved`, `confirmed`, `partially_delivered`, `request_close` (sampai ditutup); `received` tidak disentuh |
| **F13** | DO dapat menggabung beberapa SO (pivot `delivery_sales_orders`); item DO menaut `sale_order_item_id` | Reservasi dikunci per **item SO** (kolom baru `sale_order_item_id`) |
| **F14** | Aksi `approve` SO dan DO sudah memakai `wire:loading.attr=disabled`; aksi **jadwal** belum | Penjaga klik ganda ditambah pada aksi jadwal; server tetap idempoten (X5) |

---

## 4. Rancangan teknis

### 4.1 Model data (semua migrasi aditif)
| Tabel | Perubahan |
|---|---|
| `stock_reservations` | + `sale_order_item_id` (nullable, index) — kunci mapping item SO |
| `stock_reservation_events` (baru, append-only) | `id, stock_reservation_id?, sale_order_id?, sale_order_item_id?, delivery_order_id?, product_id, warehouse_id, quantity, event (reserved / adjusted / moved_to_delivery / consumed / released / reconciled), reason, actor_id, created_at` — sejarah "kenapa stok tertahan/terlepas" tanpa mengubah semantik baris (baris tetap dihapus saat lepas, sehingga kode Material Issue tidak terpengaruh) |
| `sale_orders` | + `is_backorder` (bool, default false), `backorder_reason` (text), `backorder_approved_by` (FK users), `backorder_approved_at` |
| `delivery_orders` | + `received_at`, `received_by_name` (D23) |

### 4.2 Komponen baru
| Komponen | Tanggung jawab |
|---|---|
| **`StockReservationLedger`** | **Satu-satunya penulis** reservasi penjualan: `reserve/adjust/consume/release`; transaksi + `lockForUpdate` baris stok (urut id agar tidak deadlock); memilih baris stok deterministik (rak cocok → rak null → terkecil id); menulis `stock_reservation_events`; tidak pernah membuat `qty_reserved` negatif |
| **`StockAvailability`** | Gudang yang boleh dipakai suatu cabang (D3); stok bebas per gudang/produk; `freeForSaleOrder()` = bebas + reservasi milik SO itu; `check(SaleOrder)` → per item `{diminta, tersedia, kurang, gudang}`; varian **batch** untuk daftar (menghapus N+1 badge) |
| **`SaleOrderReservationSynchronizer`** | Model **keadaan-yang-diinginkan** (sama pola Fase 2): per item SO `kebutuhan = qty − terkirim (DO sent+)`; reservasi DO terbuka menutup sebagian kebutuhan; sisanya ditahan di level SO (alokasi → gudang item → penempatan otomatis, dibatasi stok bebas). **Idempoten** — dipanggil ulang pada setiap peristiwa; sekaligus dasar `stock:reconcile-reservations` dan `sales:backfill-so-reservations` |
| **`DeliveryOrderTransitions`** | Satu pintu status DO: matriks transisi, `lockForUpdate` + baca ulang status (idempoten), cek stok saat `sent` (D15), efek (gerakan stok, konsumsi reservasi, invoice/jurnal, progres SO, status item, `DeliveryOrderLog`), alasan wajib untuk batal/gagal. Dipanggil oleh aksi DO, aksi jadwal, observer jadwal, dan `WarehouseConfirmation` |

### 4.3 Matriks transisi DO (usulan)
| Dari → Ke | Aksi / pemicu | Efek stok & reservasi |
|---|---|---|
| draft → request_stock | pembuatan DO (WC otomatis) | — |
| request_stock → **approved** ("Siap Kirim") | semua WC confirmed / approve manual | **SO→DO**: pindahkan reservasi SO ke DO (net nol) |
| request_stock/request_approve → reject | tolak (alasan) | tidak ada reservasi DO; sinkron SO |
| approved → **sent** ("Dikirim") | aksi **Kirim** atau jadwal Mulai | cek stok fisik (D15) → gerakan `sales` (idempoten) → **konsumsi** reservasi DO |
| approved → **closed** | aksi **Batalkan DO** (D19, alasan) | lepas reservasi DO → kembali ke reservasi SO (kebutuhan naik) |
| sent → **received** | aksi **Konfirmasi Diterima** (tgl + penerima) | — |
| sent/received → **completed** | jadwal Selesai / aksi Selesaikan | jurnal DO + invoice otomatis (tetap) |
| sent → **delivery_failed** | tombol/jadwal Gagal (D20) | **kembalikan stok** (gerakan masuk berjejak) + reservasi DO dibuat ulang (D18) |
| delivery_failed → approved | jadwalkan ulang | — |
Transisi di luar tabel ditolak dengan pesan jelas. Item DO mengikuti status DO (`requested→confirmed→sent→received`).

### 4.4 Aturan reservasi (ringkas)
- SO `approved/confirmed/partially_delivered/request_close`: `kebutuhan(item) = qty − Σ DO {sent,received,completed}`.
- Reservasi DO terbuka (status `approved`) **menggantikan** bagian kebutuhan yang sama; total tertahan per item ≤ kebutuhan.
- Saat stok bebas kurang: tertahan = sebesar yang tersedia (D21), kekurangan = backorder; **isi ulang** FIFO.
- SO `canceled/closed/reject/completed`: semua reservasi dilepas; DO `closed/reject/delivery_failed(sebelum sent)`: reservasi DO dikembalikan ke SO.
- "Ambil Sendiri": reservasi SO dikonsumsi saat SO completed (perilaku yang sudah ada + penjaga D15).

### 4.5 Flag konfigurasi (`config/sales.php`, default **false** → perilaku sekarang utuh)
`stock.ledger` (T2.1, buku besar + konsumsi saat sent), `stock.reserve_on_so_approve` (T2.4), `stock.block_short_approval` (T2.5), `stock.strict_dispatch` (T2.3: matriks transisi, D5, D15, D18–D20). Setiap flag punya tes mode-mati (regresi) dan mode-hidup (fitur).

---

## 5. Urutan pengerjaan & estimasi

Urutan **diubah** dari audit awal karena dependensi (§3): transisi DO harus menjadi satu pintu *sebelum* reservasi SO dihidupkan (reservasi bereaksi pada setiap transisi).

| Urut | Kode | Tugas | Est. (jam) | Flag |
|---|---|---|---|---|
| 1 | T2.0 | Persiapan: config flag, tes "probe §2 audit" sebagai regresi permanen, fixture stok bersama | 4 | — |
| 2 | T2.1 | **Buku besar reservasi + X1** (yatim) + `stock:reconcile-reservations` (A: hapus yatim, B: laporan tak-terjelaskan, C: laporan stok negatif) | 14 | `stock.ledger` |
| 3 | T2.2 | **`StockAvailability`** (D3) + validasi DO/SO/badge reservasi-sadar (F2) | 10 | — (aman: sama bila belum ada reservasi SO) |
| 4 | T2.3 | **`DeliveryOrderTransitions`** + aksi Kirim/Diterima/Batalkan + label "Siap Kirim" + D5 + D15 + D18–D20 + status item | 22 | `stock.strict_dispatch` |
| 5 | T2.4 | **Reservasi SO** (D1): synchronizer, hook SO/DO, `sales:backfill-so-reservations`, hapus `confirm()` | 18 | `stock.reserve_on_so_approve` |
| 6 | T2.5 | **Peringatan + blokir + backorder** (D2, D21, D22): service, aksi Backorder, kolom, badge, React/API `warnings[]`, `sales:top-up-reservations` | 18 | `stock.block_short_approval` |
| 7 | T2.6 | Integrasi: tulis ulang tes lama, e2e T2, skrip UAT, dokumen status, regresi penuh | 12 | — |
| | | **Total** | **≈ 98 jam ≈ 12 hari kerja** | |

Audit awal memperkirakan 8–10 hari; rencana rinci 11–12 hari karena F2 (validasi DO reservasi-sadar), F5–F7 (batal/gagal/jadwal), dan penulisan ulang tes.

---

## 6. Rincian tugas T2.1–T2.6

Semua di cabang baru `feat/penjualan-t2-stok` (dari `feat/penjualan-t1-quick-wins`); **satu commit per tugas**; tiap tugas: tes baru → tes area terkait → uji mutasi penjaga → `pint` berkas baru.

### T2.0 Persiapan (4 jam)
- `config/sales.php`: kunci `stock.*` (§4.5), semuanya `env()`-able, default false.
- `tests/Feature/Stock/StockScenarioProbeTest.php`: skenario probe audit (stok 30 → SO 35; DO approved; jadwal selesai) dengan **asersi perilaku baru** ditandai per flag — awalnya mengunci perilaku lama (flag mati), lalu diperbarui tiap tugas.
- `tests/Support/StockFixtures.php` (helper Pest): membuat produk + **satu** baris stok bernilai tertentu (mengatasi sumber gagalnya `StockReservation*Test`: auto-stok nol dari `Product::created`).

### T2.1 Buku besar reservasi + perbaikan reservasi yatim (14 jam)
- **Migrasi:** `stock_reservations.sale_order_item_id`; tabel `stock_reservation_events`.
- **`StockReservationLedger`** (§4.2). `StockReservationObserver::updated` diperbaiki agar simetris (naik dan turun, F4); pemilihan baris stok deterministik (menggantikan `->first()` acak).
- **Konsumsi saat Dikirim (X1):** `DeliveryOrderObserver::handleReservationReleaseStatus` — bila `stock.ledger`: gerakan `sales` **dan** konsumsi reservasi DO dalam satu transaksi, **idempoten** (tidak membuat gerakan kedua bila sudah ada — F6). Pelepasan pada `closed`, `reject`, dan DO dihapus lewat ledger (menggantikan `DeliveryOrderService::releaseStockReservations` yang mati).
- **`php artisan stock:reconcile-reservations {--apply} {--csv}`:**
  - **A. Yatim (dapat diperbaiki):** baris reservasi penjualan yang DO-nya `sent/received/completed/closed/reject` atau sudah terhapus, atau SO-nya `canceled/closed/completed`/terhapus → dihapus lewat ledger dengan alasan `reconcile`. Dry-run default; `--apply` menulis CSV cadangan (baris + `inventory_stocks` sebelum/sesudah) lebih dulu.
  - **B. Tak terjelaskan (hanya laporan, D17):** per produk×gudang `qty_reserved − Σ reservasi aktif` > 0, dengan petunjuk sumber (ada Retur Pembelian/QC reject? impor legacy?). Tidak diubah.
  - **C. Stok negatif (hanya laporan, F9).**
- **Tes:** ledger (reserve/adjust/consume/release; tidak pernah negatif; deterministik; event tercatat; dua proses memperebutkan stok terakhir — uji konkurensi); observer simetris; skenario probe: stok 30 → kirim 12 → **`qty_reserved` = 0, stok bebas 18** (bukan 6); `failed→sent` tidak menggandakan gerakan; rekonsiliasi: yatim terdeteksi & dibersihkan, tak-terjelaskan hanya dilaporkan, `--apply` menulis cadangan, tidak menyentuh reservasi Material Issue.
- **Selesai bila:** invarian "tak ada reservasi penjualan yang DO/SO-nya sudah berakhir" bernilai 0 setelah `--apply`; uji mutasi: matikan konsumsi → tes probe gagal.

### T2.2 `StockAvailability` + validasi reservasi-sadar (10 jam)
- **`StockAvailability`** (D3): `accessibleWarehouseIds(cabang)` membaca `Cabang.lihat_stok_cabang_lain`; `freeByWarehouse`, `freeForSaleOrder`, `check(SaleOrder)`, `checkMany([...])` (batch).
- **Ganti pemakai `freeQtyFor` pada jalur penjualan:** `CreateDeliveryOrder:281`, `EditDeliveryOrder:177`, `DeliveryOrderResource:372/638/665` → `freeForSaleOrder` (menambah kembali reservasi SO/DO milik SO itu); `SaleOrder::hasInsufficientStock/getInsufficientStockItems` + badge daftar → `checkMany` (menghapus N+1); `SalesOrderService::approve` (ditangani lebih lanjut di T2.5). Form SO Filament (`SaleOrderResource` 1046–1181) tetap: SO draft belum punya reservasi.
- **Tes:** kebijakan cabang (hanya cabang SO / + cabang lain bila flag); `freeForSaleOrder` menambah kembali reservasi sendiri **dan tidak** milik SO lain; validasi DO **lolos** untuk SO yang menahan stok (skenario F2) dan **gagal** bila gudang lain menahan; daftar SO 25 baris → jumlah query badge konstan.
- **Selesai bila:** tanpa reservasi SO perilaku tidak berubah (regresi Fase 2/DO lolos); dengan reservasi SO, DO-nya tidak terblokir oleh reservasinya sendiri.

### T2.3 `DeliveryOrderTransitions` (22 jam) — flag `stock.strict_dispatch`
- **Layanan** (matriks §4.3): `to($do, $status, ['reason'=>…, 'received_by'=>…, 'actor'=>…])`; `lockForUpdate` + baca ulang status di dalam transaksi (klik ganda/dua proses = satu efek, X5); pesan galat berbahasa Indonesia; mencatat `DeliveryOrderLog` (alasan) dan menyinkronkan `deliveryOrderItem.status`.
- **Semua pintu memanggilnya:** `DeliveryOrderService::updateStatus` (delegasi), `DeliveryScheduleService::start/completeRelatedDeliveryOrders`, `WarehouseConfirmation → updateStatusFromWarehouseConfirmations`, aksi tabel `mark_delivery_failed`.
- **Aksi DO baru (View + tabel):** **Kirim** (approved→sent), **Konfirmasi Diterima** (sent→received; tanggal + nama penerima, D23), **Selesaikan** (received/sent→completed), **Batalkan DO** (approved→closed, alasan wajib, D19). Izin: `response delivery order`. Label **"Siap Kirim"** untuk `approved` (`DeliveryOrder::STATUS_LABELS`).
- **Jadwal (D5, D20, F7):** `set_delivered` hanya dari `on_the_way`/`partial_delivered`; `DeliveryScheduleObserver::updating` menolak `pending → delivered` **di model** (form status juga tercakup); `failed` memicu DO `delivery_failed` (D18: stok kembali); klik ganda dijaga (`wire:loading.attr`) pada aksi jadwal (F14).
- **Stok negatif (D15/F9):** saat `sent` dan penyelesaian "Ambil Sendiri" — cek stok **fisik** (`qty_available`) per gudang sumber; kurang → ditolak dengan rincian ("Produk X: butuh 12, stok fisik Gudang K01 hanya 8"); pengecualian beralasan untuk Owner/Super Admin tercatat.
- **`php artisan delivery-orders:resync-item-status {--apply}`:** memperbaiki status item DO lama dari status DO (yang kini `requested` padahal sudah dikirim).
- **Tes:** tabel data matriks (status × aksi → boleh/ditolak + efek); idempotensi (dua panggilan `to(sent)` = satu gerakan stok); jadwal `pending→delivered` ditolak (UI **dan** model); `sent→delivery_failed` mengembalikan stok tepat sekali dan `→sent` ulang memotong sekali; `Batalkan DO` melepas reservasi; stok fisik kurang menolak `sent`; status item selalu mengikuti DO di semua pintu; log terisi; **probe audit:** "Tandai Selesai" dari *pending* kini ditolak dan item tidak lagi `requested`.
- **Selesai bila:** semua pintu menghasilkan status item & log yang sama; tidak ada transisi di luar matriks; regresi tes DO/jadwal/invoice lolos.

### T2.4 Reservasi saat SO Approved (18 jam) — flag `stock.reserve_on_so_approve`
- **`SaleOrderReservationSynchronizer::sync($so)`** (§4.2/4.4), dipanggil dari: `SaleOrderObserver` (status → approved/confirmed/partially_delivered/request_close/closed/canceled/completed), `DeliveryOrderTransitions` (setiap transisi), pembatalan/penutupan SO, dan perintah. Bekerja dalam transaksi dengan kunci baris SO; hasilnya lewat ledger + event. **D22:** penempatan otomatis bila item tanpa gudang.
- **Perpindahan SO→DO (D1):** saat DO `approved`, kebutuhan ditutup reservasi DO dan reservasi SO berkurang sama besar (total tertahan per item tak pernah melebihi kebutuhan — tes invarian).
- **`sales:backfill-so-reservations {--apply}`:** untuk SO berjalan tanpa reservasi (data lama), berurutan menurut waktu approve; dry-run melaporkan yang bisa ditahan penuh/sebagian/tidak; `--apply` menulis cadangan CSV. **Wajib** dijalankan setelah `stock:reconcile-reservations --apply` dan **sebelum** flag dihidupkan di UAT.
- **Hapus `SalesOrderService::confirm()`** (logikanya dipindah ke synchronizer); status `confirmed` tetap dikenali.
- **Tes:** SO 20/stok 30 → bebas 10; SO kedua 15 (tanpa backorder) tidak dapat ditahan penuh; DO 12 disetujui → total tertahan tetap 20, setelah Dikirim 8 & stok fisik −12; cancel/close/reject melepas; DO batal mengembalikan kebutuhan ke SO; Ambil Sendiri dikonsumsi saat completed; multi-SO dalam satu DO; spesifikasi lama (`StockReservationFlowTest`, `…ServiceTest`, `MultiWarehouse 10b`) **diaktifkan** kembali dengan fixture baru; invarian: per item Σ tertahan ≤ kebutuhan.
- **Selesai bila:** skenario UAT §8 langkah 1–6 lolos dan `stock:reconcile-reservations` melaporkan 0 yatim setelah alur penuh.

### T2.5 Peringatan stok, blokir approve, backorder (18 jam) — flag `stock.block_short_approval`
- **Approve (`SalesOrderService::approve`, dipakai UI & API):** `StockAvailability::check` → kurang & bukan backorder → `ValidationException` ("Produk X: diminta 35, stok bebas 30 (kurang 5) di Gudang K01"). Item tanpa alokasi **kini juga diperiksa** (akar celah probe).
- **Backorder (D2):** aksi **"Setujui sebagai Backorder"** pada SO menunggu persetujuan bila stok kurang: modal ringkasan kekurangan + **alasan wajib**; hanya peran Sales Manager+ (memakai `ApprovalControlService`; SoD tetap berlaku); menyimpan `is_backorder`, alasan, penyetuju, waktu; reservasi **parsial** (D21); badge **"Backorder"** di daftar/View.
- **Isi ulang:** `php artisan sales:top-up-reservations` (dijadwalkan tiap 30 menit di `routes/console.php`, `withoutOverlapping`) + tombol "Coba reservasi ulang" di View SO — FIFO menurut waktu approve; mencatat event.
- **Peringatan saat input:** form Filament (banner per item bila qty > stok bebas); **React**: baris item menampilkan "⚠ melebihi stok bebas (X)" — `dependencies` API kini mengembalikan stok bebas **per cabang** (`StockAvailability`, bukan jumlah semua cabang); API `store/update` mengembalikan `warnings[]` (tidak memblokir simpan draft). Badge daftar "STOK KURANG" memakai data batch T2.2.
- **Tes:** SO 35/stok 30 ditolak; lolos lewat backorder beralasan oleh Sales Manager, ditolak untuk Sales biasa & untuk pembuat sendiri (SoD); reservasi parsial 30, kekurangan 5; isi ulang setelah stok masuk (pembelian 10) → 35 tertahan penuh, urutan FIFO antar dua SO; API `warnings[]`; `dependencies` per cabang; tes lama `SaleOrderStockApprovalGuardTest` diubah sesuai F3; mutasi: lepas pemeriksaan → tes gagal.
- **Selesai bila:** tak ada jalur (UI/API/service) yang menyetujui SO stok kurang tanpa backorder tercatat.

### T2.6 Integrasi & penutupan (12 jam)
- Tulis ulang tes lama sesuai §7; `InvoiceEditAndDeliveryOrderTest` (3, memanggil metode WC yang sudah tak ada) diganti alur DO baru.
- **E2E T2** (memperluas `SalesFlowEndToEndFase1to6Test`): SO → approve (reservasi) → DO approved (pindah) → Kirim (stok keluar, reservasi terkonsumsi) → Diterima → Selesai (invoice/jurnal) → cek stok, reservasi, status item, log, laporan; alur gagal-kirim & batal.
- Dokumen: status pelaksanaan, panduan rollout flag, catatan X11/X12; perbarui `BASELINE-TES.md` (tes yang kini lolos).
- Regresi penuh `composer test:chunked -- --compare=tests/baseline-failures.txt` → **0 kegagalan baru**; kegagalan yang diperbaiki dicatat & baseline diperbarui di commit terpisah.

---

## 7. Tes, regresi, dan tes yang harus diubah

**Tes baru (perkiraan 90–110 tes, ±12 berkas):** ledger, rekonsiliasi, `StockAvailability`, matriks transisi, idempotensi/konkurensi, synchronizer (invarian), backfill, backorder & isi ulang, API/React (`warnings[]`, `dependencies`), jadwal (model + UI), e2e T2. Setiap penjaga kritis diuji **mutasi**.

**Tes yang PERILAKUNYA sengaja berubah** (bukan regresi):
| Tes | Perubahan |
|---|---|
| `SaleOrderStockApprovalGuardTest` (2 tes "approval tetap berhasil walau stok kurang") | Mode flag mati: tetap lolos. Mode baru: diblokir; lolos hanya dengan backorder (F3) |
| `StockReservationFlowTest` (4), `StockReservationServiceTest` (5), `SaleOrderMultiWarehouseTest` 10b | Baseline gagal → **diaktifkan** dengan fixture stok tunggal; menjadi spesifikasi reservasi |
| `InvoiceEditAndDeliveryOrderTest` (3) | Diganti alur DO baru (metode WC lama sudah dihapus) |
| `SalesOrderSelfPickupApprovedTest` | Diselaraskan dengan reservasi SO (F11/F12) |
| `DeliveryScheduleTest`, `DeliveryScheduleInventoryFixTest`, `CompleteDeliveryOrderFlowTest`, `SalesOrderToDeliveryOrderCompleteTest` | Jadwal wajib Mulai dulu (D5); assertion stok/status item diperbarui |
| Tes yang menegaskan label DO "Disetujui" | Menjadi "Siap Kirim" |

**Regresi wajib:** Fase 1–6 (200) + T1, `DeliveryOrder*`, `DeliverySchedule*`, `SuratJalan*`, `SaleOrder*`, `Invoice*`, `MaterialIssue*` (memakai model reservasi yang sama — tidak boleh berubah), `StockMovement*`, `Inventory*`.
**Gerbang:** suite penuh vs baseline → 0 kegagalan baru; `stock:reconcile-reservations` = 0 yatim setelah e2e.

---

## 8. Skrip UAT manual T2

Data: produk X stok fisik **30** di Gudang K01; dua SO: **A = 20**, **B = 15** (harga bebas). Aktifkan flag `stock.*` di UAT setelah `stock:reconcile-reservations --apply` dan `sales:backfill-so-reservations --apply`.

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Approve SO A (20) | Sukses; stok bebas X = **10**; SO A menampilkan "Tertahan 20" |
| 2 | Approve SO B (15) sebagai Sales Manager tanpa backorder | **Ditolak**: "diminta 15, stok bebas 10 (kurang 5) di Gudang K01" |
| 3 | Sales biasa menyetujui SO B sendiri lewat backorder | Ditolak (bukan peran/SoD) |
| 4 | Sales Manager lain: "Setujui sebagai Backorder" + alasan | SO B disetujui, badge **Backorder**, tertahan **10**, kurang **5** |
| 5 | Buat DO 12 dari SO A → gudang menyetujui | DO **Siap Kirim**; total tertahan tetap 20 (bukan 32); DO tidak terblokir "stok tidak cukup" |
| 6 | Jadwal → **langsung** "Tandai Selesai" dari Menunggu | **Ditolak** ("Mulai Pengiriman dulu") |
| 7 | Jadwal → Mulai Pengiriman | DO **Dikirim**; stok fisik **18**; tertahan 8 (SO A) + 10 (SO B) = 18; item DO **Sedang Dikirim** |
| 8 | Ulangi "Mulai" cepat 2× | Efek satu kali (tidak ada gerakan ganda) |
| 9 | Pembelian masuk 10 unit X | `sales:top-up-reservations` (atau tombol) → SO B tertahan **15** penuh |
| 10 | DO → Konfirmasi Diterima (tanggal + penerima) → Selesai | Item **Diterima**; invoice & jurnal terbit; SO A "Dikirim Sebagian" (sisa 8) |
| 11 | DO kedua 8 → Kirim → tandai **Gagal** | Stok kembali 8; DO **Pengiriman Gagal**; reservasi tetap; jadwalkan ulang → Dikirim → stok terpotong **sekali** |
| 12 | DO lain **Siap Kirim** → **Batalkan DO** (alasan) | Reservasi dilepas, kembali ke kebutuhan SO |
| 13 | Batalkan SO B | Reservasi terlepas; stok bebas naik |
| 14 | `php artisan stock:reconcile-reservations` | **0 yatim**; tak-terjelaskan dilaporkan (bila ada) |

---

## 9. Rollout, flag, rollback

1. Deploy kode + `php artisan migrate` (semua aditif) dengan **semua flag mati** → perilaku sama dengan sekarang.
2. `php artisan stock:reconcile-reservations` (dry-run) → tinjau **CSV**: yatim (A) dan tak-terjelaskan (B, D17) — bawa ke gudang/akuntansi; `--apply` hanya untuk A.
3. Hidupkan **`stock.ledger`** → uji alur pengiriman (langkah 7–8).
4. Hidupkan **`stock.strict_dispatch`** (matriks transisi, D5, D15, D18–D20).
5. `sales:backfill-so-reservations` (dry-run → `--apply`) lalu **`stock.reserve_on_so_approve`**.
6. Hidupkan **`stock.block_short_approval`** (D2) — terakhir, setelah tim sales dilatih soal backorder.
7. Skrip UAT §8 penuh; setelah stabil: hapus flag (T8) dan kode jalur lama.
**Rollback:** matikan flag terkait (perilaku lama kembali); migrasi punya `down()`; `--apply` rekonsiliasi/backfill selalu menulis CSV cadangan (baris yang dihapus + stok sebelum/sesudah); `stock_reservation_events` menjelaskan setiap perubahan.

---

## 10. Risiko

| Risiko | Peluang | Dampak | Mitigasi |
|---|---|---|---|
| T2 menyentuh stok dan jurnal (dampak tertinggi proyek) | — | Tinggi | Flag bertahap; buku besar tunggal + event; uji konkurensi; rekonsiliasi & backfill dry-run + cadangan CSV |
| Reservasi SO menghalangi DO-nya sendiri (F2) bila ada titik validasi terlewat | Sedang | Tinggi | Daftar titik `freeQtyFor` di T2.2 (DO: 5, model SO: 2 metode, `approve`: 1) + tes pemindai yang gagal bila `freeQtyFor` dipakai di jalur penjualan tanpa add-back |
| `qty_reserved` tak terjelaskan (F1/D17) disalahartikan sebagai kebocoran | Tinggi | Sedang | Laporan terpisah A (yatim) vs B (tak terjelaskan) + sumber; tidak diubah otomatis |
| Tim sales terbiasa "approve walau stok kurang" (F3) | Tinggi | Sedang | Jalur backorder beralasan tetap memungkinkan penjualan pre-order; pelatihan; flag D2 dihidupkan terakhir |
| Klik ganda/balapan pada transisi | Sedang | Sedang | `lockForUpdate` + baca ulang status; tes dua-panggilan = satu-efek |
| Baris stok ganda per produk×gudang (rak) membuat baris-level `free_qty` negatif walau total benar | Sedang | Rendah | Pemilihan baris deterministik; jumlah per produk×gudang tetap sumber kebenaran; laporan C |
| Estimasi 12 hari meleset karena banyak tes lama yang berubah | Sedang | Rendah | Daftar §7; tes diperbarui bersama tugasnya |

---

## 11. Status pelaksanaan

**Cabang:** `feat/penjualan-t2-stok` — satu commit per tugas (T2.0 `fa4a5d5`, T2.1 `04fc29c`, T2.2 `11a325f`, T2.3 `60c7272`, T2.4 `6572123`, T2.5 `489ea04`, T2.6 di commit penutup).
Semua flag `sales.stock.*` **default mati** → tanpa menyalakan flag, perilaku sama dengan sebelum T2 (dibuktikan tes karakterisasi `[flag mati]` di setiap tugas).

| Tugas | Hasil | Tes baru |
|---|---|---|
| T2.0 | flag `stock.*`, fixture stok bersama (`tests/Support/StockFixtures.php`), probe audit sebagai regresi permanen | 5 |
| T2.1 | buku besar reservasi tunggal (`StockReservationLedger`) + event append-only; reservasi DO dikonsumsi saat Dikirim (menutup reservasi yatim X1); pengiriman idempoten (`netShipped`); `stock:reconcile-reservations` | 22 |
| T2.2 | `StockAvailability` (stok bebas sadar-reservasi, kebijakan cabang D3, batch tanpa N+1) dipakai form/validasi DO, approve, badge daftar SO | 15 |
| T2.3 | `DeliveryOrderTransitions` (matriks, kunci baris, idempoten, atomik), label **Siap Kirim**, D5 (jadwal wajib Mulai), D15 (stok fisik kurang menolak Kirim + pengecualian Owner/Super Admin), D18/D20 (gagal kirim mengembalikan stok sekali), D19 (Batalkan DO), D23 (Diterima + Selesaikan), `delivery-orders:resync-item-status` | 53 |
| T2.4 | `SaleOrderReservationSynchronizer` (keadaan-yang-diinginkan, idempoten; SO→DO tanpa selisih; D21 parsial; D22 penempatan otomatis), `sales:backfill-so-reservations` | 19 |
| T2.5 | blokir approve stok kurang (D2) untuk SEMUA item, `approveAsBackorder` (alasan, SoD, Sales Manager+), badge Backorder, `sales:top-up-reservations` (FIFO, dijadwalkan 30 menit), `warnings[]` API + peringatan React/Filament | 15 |
| T2.6 | penulisan ulang tes usang (lihat bawah), E2E T2, `SalesOrderService::confirm()` + `InsufficientStockException` dihapus, `cancel()` lewat buku besar, dokumen ini | 12 (spec 7 + E2E 2 + alur WC 3) + 12 tes lama ditulis ulang |

Semua penjaga kritis diuji **mutasi** (dirusak → tes gagal → dipulihkan): matriks transisi, idempotensi, D15, pengembalian gagal-kirim, penjaga model, D5, pemeriksaan stok saat Mulai, penjaga Ambil Sendiri, pengurangan kebutuhan oleh DO, batas stok bebas, penempatan D22, blokir approve, alasan & SoD backorder, urutan FIFO, `warnings[]` API, visibilitas aksi.

### Tes usang yang diganti / diaktifkan (T2.6)
| Tes lama | Sebab gagal | Tindakan |
|---|---|---|
| `StockReservationServiceTest` TC-SR-001/002/006 (Material Issue) | baris stok ganda (`Product::created` membuat baris nol; `InventoryStock::create` menambah baris kedua) | fixture memakai `stkSetStock` → **lolos** |
| TC-SR-003…005 dan seluruh `StockReservationFlowTest` | memakai `SalesOrderService::confirm()` dan semantik lama "`qty_available` berkurang saat reservasi" | diganti `Stock/StockReservationSpecTest` (semantik: `qty_available` = stok fisik, `qty_reserved` = tertahan) |
| `SaleOrderMultiWarehouseTest` 3–6, 10b | memakai `confirm()` | ditulis ulang ke synchronizer (per alokasi, parsial, single-gudang, Ambil Sendiri tanpa double-deduct) |
| `SalesOrderSelfPickupApprovedTest` | mengharapkan status `confirmed` (WC otomatis sudah dihapus) + baris stok ganda + akun HPP tidak ada | status `approved`, fixture stok & akun diperbaiki → **lolos** |
| `InvoiceEditAndDeliveryOrderTest` (3 tes DO) | memanggil `createDeliveryOrderForConfirmedWarehouseConfirmation()` yang sudah dihapus | diganti `Stock/DeliveryOrderWarehouseConfirmationFlowTest` (alur DO-sentris) |

### Penyimpangan dari rencana & temuan baru
- **`reserve_on_so_approve` dan `strict_dispatch` otomatis menyalakan buku besar** (`StockReservationLedger::enabled()`): reservasi SO dan DO harus terpetakan ke item SO agar tidak terhitung ganda; menyalakan salah satu flag cukup.
- **Efek status DO dijalankan observer, bukan hanya layanan:** status item, log, dan pengembalian stok gagal-kirim terjadi di *semua* pintu (termasuk kode lama yang memanggil `$do->update(['status' => …])`); model menolak transisi di luar matriks dan Kirim dengan stok fisik kurang.
- **"Mulai Pengiriman" jadwal kini ditolak bila DO terkait belum Siap Kirim** (mis. masih menunggu konfirmasi gudang) atau stok fisik kurang — sebelumnya jadwal berjalan sementara DO diam-diam tetap di status lama. Perlu disampaikan ke tim logistik.
- **Log DO** kini menyimpan komentar/alasan (sebelumnya `createLog` menerima tetapi membuang komentar). `confirmed_by = 0` berarti proses sistem (jadwal/konsol).
- **Konfirmasi gudang terlambat** tidak lagi menarik DO yang sudah Dikirim/Selesai kembali ke Siap Kirim (perilaku lama menimpa status).
- **Dry-run `sales:backfill-so-reservations` / `sales:top-up-reservations`** menjalankan logika yang sama di dalam transaksi yang dibatalkan, sehingga hasilnya identik dengan `--apply`.
- **Penempatan otomatis D22** memecah reservasi ke gudang berikutnya bila gudang terbesar tidak cukup; data item SO tidak diubah.
- **X11** (Retur Pembelian menaikkan `qty_reserved` tanpa baris) **hanya dilaporkan** (`stock:reconcile-reservations` bagian B), tidak diperbaiki — milik Pembelian. **X12** (`AppSetting::doApprovalRequired` tidak dipakai di mana pun) dicatat, tidak diubah.
- **`composer test:chunked`** sebelumnya mati pada 300 detik (batas proses Composer) — kini `disableProcessTimeout`. Jangan menjalankan dua runner/`pest` pada database uji yang sama; gunakan `--db=` atau `DB_DATABASE=` berbeda (berakhiran `_test`).
- `AssetPurchaseWorkflowTest::asset purchase flow with pre set signature` sempat gagal satu kali saat mesin sangat terbebani (baris `inventory_stocks` produk aset) tetapi lolos bila dijalankan sendiri — modul aset, tidak tersentuh T2; **dipantau**.

### Yang perlu Anda lakukan
1. **`php artisan migrate`** — tiga migrasi aditif: `…100000_add_item_mapping_and_events_to_stock_reservations`, `…110000_add_received_fields_to_delivery_orders_table`, `…120000_add_backorder_fields_to_sale_orders_table`.
2. **Deploy dengan semua flag mati**, lalu jalankan urutan §9: `php artisan stock:reconcile-reservations` (tinjau CSV yatim/tak-terjelaskan) → `--apply` untuk yatim → `sales:backfill-so-reservations` (dry-run, tinjau) → `--apply`.
3. **Nyalakan flag bertahap di `.env`** (setiap langkah uji dengan skrip UAT §8): `SALES_STOCK_LEDGER=true` → `SALES_STOCK_STRICT_DISPATCH=true` → `SALES_STOCK_RESERVE_ON_SO_APPROVE=true` → `SALES_STOCK_BLOCK_SHORT_APPROVAL=true` (terakhir, setelah tim sales dilatih soal Backorder). Lalu `php artisan config:clear`.
4. **Pastikan scheduler berjalan** (`php artisan schedule:work` di dev / cron `schedule:run` di server) agar `sales:top-up-reservations` berjalan tiap 30 menit.
5. **Jalankan `php artisan delivery-orders:resync-item-status`** (dry-run, lalu `--apply`) untuk merapikan status item DO lama.
6. **UAT manual T2** — skrip §8 (14 langkah); kirim temuan.
7. **Konfirmasi D6–D14** sebelum T3/T4 dimulai.
