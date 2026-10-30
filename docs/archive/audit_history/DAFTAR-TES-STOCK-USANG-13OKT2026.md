# Daftar 37 Tes `tests/Feature/Stock` yang Gagal — Rekomendasi per Tes

Tanggal: 13 Okt 2026. Semua tes di bawah gagal karena menguji **kontrak stok lama** (reservasi DO saat "Siap Kirim", stok terpotong saat "Dikirim", Ambil Sendiri memotong stok saat SO selesai). Kode sekarang (Tahap 3, brief Ko Handy, commit `d62d4449` 9 Okt) berbeda secara sengaja:

- Stok fisik terpotong **saat DO dibuat** (`DeliveryOrderObserver::createStockMovementsForDeliveryOrder`); saat DO `approved`/`sent`/`completed` reservasi DO dilepas/dikonsumsi, tidak ada potongan kedua.
- SO **tidak memotong stok** sendiri, termasuk "Ambil Sendiri" (sekarang wajib lewat DO).
- Matriks status mengizinkan `approved → completed`.

Legenda rekomendasi:
- **TULIS ULANG** — perilaku yang dijaga masih berlaku; ubah angka/langkah ke kontrak Tahap 3.
- **PENSIUNKAN** — perilaku yang dijaga sudah tidak ada; hapus tes (atau ganti dengan tes baru yang disebut).
- **PUTUSKAN** — butuh keputusan bisnis dulu sebelum tes ditulis.

Ringkasan: **TULIS ULANG 22 · PENSIUNKAN 9 · PUTUSKAN 6**.

## Keputusan poin A–D (13 Okt 2026) — dasar kode, perlu dikonfirmasi Ko Handy

| Poin | Keputusan | Dasar |
|---|---|---|
| **A** `approved → completed` | **Dipertahankan** (diizinkan) | Disengaja sejak commit `d62d4449`; aksi Selesaikan di UI memang tampil untuk Siap Kirim. Penjaga SJ tetap berlaku. |
| **B** pemeriksaan stok fisik saat Kirim (D15) + pengecualian Owner/Super Admin | **Dipertahankan**, bukan dihapus | Untuk DO baru pemeriksaan ini tidak pernah memblokir (kebutuhan = qty − sudah keluar = 0), tetapi masih berfungsi untuk DO warisan sebelum Tahap 3 dan DO yang stoknya sudah dikembalikan lalu dikirim ulang. Tes D15 ditulis ulang memakai DO yang belum memotong stok (`trxUnshipped`). **Ditemukan bug nyata:** pengecualian Owner lolos validasi dan tercatat di log, tetapi pemotongan stok tidak pernah meneruskan `allow_negative_stock` ke penjaga stok sehingga tetap ditolak. Sudah diperbaiki di `createStockMovementsForDeliveryOrder`. |
| **C** gagal kirim dari Siap Kirim | **Stok tetap terpotong**, tidak dikembalikan | Barang belum berangkat sehingga tidak ada yang "dikembalikan"; stok kembali hanya lewat batal (`closed`) / hapus, atau saat gagal setelah Dikirim. Sesuai kode `handleStrictStatusEffects` (reverse hanya dari `sent`/`received`). Kirim ulang tidak memotong lagi. |
| **D** reservasi DO ditolak (D2) + penahanan ganda | **Kuantitas SO tetap terikat ke DO ditolak, tetapi stoknya ditahan SO** | DO ditolak boleh diajukan ulang (matriks `reject → request_stock/approved`), jadi kuantitas tidak dilepas ke SO; namun barangnya sudah kembali ke gudang sehingga `SaleOrderReservationSynchronizer` kini mengecualikan kuantitas DO ditolak dari "di luar gudang". |

## Progres (13 Okt 2026)

Selesai: **#11, #17, #20, #21, #30, #32, #33, #35, #36 dipensiunkan** (13 Okt; diganti 3 tes baru: 2 tes Ambil Sendiri lewat DO di `DeliveryOrderTransitionsTest`, 1 tes dua gudang sumber di `StockReservationLedgerTest`), **#31** (13 Okt), **#25–#29** (fixture reservasi yatim dibuat langsung sebagai data warisan, 13 Okt), **#1–#4, #7, #16, #18, #22, #34** (tulis ulang angka, 13 Okt), **#5, #6, #12, #14** (tulis ulang sepele, 13 Okt), **#19** (checker edit qty, ditulis ulang + 1 tes baru untuk edit naik), **#23** dan **#24** (E2E). Satu tes yang sebelumnya lulus karena bug penahanan ganda (`StockReservationLedgerTest` "[ledger] mengubah kuantitas item DO… edit checker") ikut ditulis ulang ke kontrak Tahap 3. **Folder `Stock`, `Sales`, dan tes DO/retur terkait: 227 lulus, 0 gagal.** Enam tes kelompok PUTUSKAN (#8, #9, #10, #13, #15, #37) sudah dikerjakan sesuai keputusan A–D di atas.

Penulisan tes menemukan **tiga bug nyata** (sudah diperbaiki):
1. **Kebocoran stok saat checker mengurangi qty DO.** Edit qty item/sumber gudang tidak mengembalikan stok fisik yang sudah terpotong (urutan Filament: item dulu, baru sumber). Kini ada `DeliveryOrderObserver::reconcileStockMovementsForDeliveryOrder` yang memotong/mengembalikan selisihnya, dipanggil dari hook item dan sumber gudang.
2. **Penahanan ganda saat edit qty.** Edit qty DO `approved` membuat ulang reservasi level-DO padahal stok sudah terpotong; hook itu dihapus.
3. **Penahanan ganda di reservasi SO** (flag `reserve_on_so_approve`): setelah DO dibuat, SO menahan seluruh sisa (mis. 18 dari 18 fisik, stok bebas semu 0), padahal sebagian sudah keluar lewat DO. Target reservasi SO kini = kebutuhan − kuantitas DO terbuka.

## 1. Adversarial (4)

| # | Tes | Penyebab gagal | Rekomendasi |
|---|---|---|---|
| 1 ✅ | `DeliveryOrderStateTransitionAdversarialTest` ST-04 — reservasi dilepas saat DO approved dibatalkan | Mengharapkan stok bebas kembali via pelepasan reservasi; sekarang yang kembali adalah stok fisik (reversal) | **TULIS ULANG**: assert stok fisik kembali ke awal dan reservasi 0 setelah `closed` (sebagian sudah dicakup `Tahap3StockDeductionTimingTest` #5) |
| 2 ✅ | `StockConcurrencyAdversarialTest` CC-02 — persetujuan berulang tidak menggandakan potongan/reservasi | Angka reservasi (8) basi | **TULIS ULANG**: approve dua kali → potongan fisik tetap satu kali |
| 3 ✅ | `StockConcurrencyAdversarialTest` CC-03 — stok retur langsung tersedia untuk DO berikutnya | Angka stok basi (20 vs 10): potongan terjadi saat DO dibuat | **TULIS ULANG**: hitung ulang saldo dengan potongan di pembuatan DO |
| 4 ✅ | `StockThreeWayReconciliationTest` RC-02 — nol reservasi phantom di tiap siklus DO | Mengharapkan reservasi DO tertahan di tengah siklus | **TULIS ULANG**: invarian baru — `qty_reserved` = Σ reservasi aktif (SO saja) dan fisik = awal − bersih terkirim |

## 2. `DeliveryOrderTransitionsTest` (11)

| # | Tes | Penyebab gagal | Rekomendasi |
|---|---|---|---|
| 5 ✅ | matriks transisi — dataset `approved → completed = false` | Kode mengizinkan `approved → completed` | **TULIS ULANG**: ubah dataset jadi `true` (konfirmasi dulu bahwa memang disengaja, lihat catatan A) |
| 6 ✅ | transisi di luar matriks ditolak dengan pesan Indonesia | Memakai pasangan `approved → completed` sebagai contoh ilegal | **TULIS ULANG**: pakai pasangan ilegal lain, mis. `approved → received` |
| 7 ✅ | Kirim: stok fisik keluar sekali, reservasi DO dikonsumsi, item "sent", log | Mengharapkan reservasi 12 sebelum Kirim; stok sudah terpotong sejak dibuat | **TULIS ULANG**: stok 18 sejak DO dibuat, Kirim tidak memotong lagi, 1 gerakan, log tercatat |
| 8 ✅ | D15: stok fisik kurang menolak Kirim | Sekarang DO tidak bisa **dibuat** bila stok kurang (gagal di fixture) | **PUTUSKAN** (catatan B) |
| 9 ✅ | D15: pengecualian Owner/Super Admin + alasan tercatat | Sama dengan #8 | **PUTUSKAN** (catatan B) |
| 10 ✅ | D15 berlaku di semua pintu: update model langsung ditolak | Sama dengan #8 | **PUTUSKAN** (catatan B) |
| 11 🗑️ | D15: SO "Ambil Sendiri" tidak dapat diselesaikan bila stok fisik kurang | SO tidak lagi memotong stok; `SaleOrderObserver::updating` kini `return` | **PENSIUNKAN**; ganti dengan tes "Ambil Sendiri lewat DO ditolak bila stok kurang" |
| 12 ✅ | `updateStatus` mengikuti matriks dan menyimpan komentar log | Memakai `approved → completed` sebagai transisi ilegal | **TULIS ULANG**: pasangan ilegal lain |
| 13 ✅ | gagal kirim dari Siap Kirim (stok belum keluar) tidak menyentuh stok | Stok sudah keluar sejak dibuat | **PUTUSKAN** (catatan C): apakah gagal kirim sebelum Dikirim mengembalikan stok? |
| 14 ✅ | D5: jadwal tidak bisa ditandai selesai dari pending | Mengharapkan 0 gerakan stok; ada 1 gerakan dari pembuatan DO | **TULIS ULANG** (sepele): ekspektasi 1 gerakan, tidak bertambah |
| 15 ✅ | D15 di jadwal: Mulai ditolak bila stok fisik kurang | Sama dengan #8 | **PUTUSKAN** (catatan B) |

## 3. `DeliveryOrderWarehouseConfirmationFlowTest` (2)

| # | Tes | Penyebab gagal | Rekomendasi |
|---|---|---|---|
| 16 ✅ | semua WC dikonfirmasi → DO otomatis Siap Kirim dan reservasi DO terbentuk | Reservasi DO tidak lagi terbentuk | **TULIS ULANG**: status `approved` + `stkReserved` DO = 0, stok fisik terpotong |
| 17 🗑️ | Ambil Sendiri tidak perlu DO: stok keluar saat SO selesai | Ambil Sendiri sekarang wajib DO | **PENSIUNKAN** (bersama #11) |

## 4. `SaleOrderReservationTest` (5)

| # | Tes | Penyebab gagal | Rekomendasi |
|---|---|---|---|
| 18 ✅ | SO→DO tanpa selisih: DO 12 disetujui → total tertahan 20 | Reservasi DO tidak ada; fisik sudah 18 | **TULIS ULANG**: DO dibuat → fisik 18, reservasi SO sisa 8, reservasi DO 0 (flag `reserve_on_so_approve` masih dipakai) |
| 19 ✅ | edit kuantitas item DO (checker) saat Siap Kirim menjaga total tertahan = kebutuhan | Berhubungan langsung dengan bagian D laporan UAT ("Checker Edit Qty") | **TULIS ULANG, prioritas tinggi**: edit 12→5 → fisik 25, sisa SO terhitung benar, tanpa reservasi DO ganda (lihat catatan D) |
| 20 🗑️ | Ambil Sendiri: reservasi SO dikonsumsi saat selesai | Jalur dihapus | **PENSIUNKAN** |
| 21 🗑️ | [flag mati] approve SO tidak menahan stok dan DO memakai jalur reservasi lama | "Jalur reservasi lama" tidak ada lagi | **PENSIUNKAN** |
| 22 ✅ | F2: DO tidak terblokir reservasi SO-nya sendiri; reservasi SO lain mengurangi stok bebas | Angka basi; aturannya masih berlaku (`freeForSaleOrders` menambah reservasi SO sendiri) | **TULIS ULANG** |

## 5. `StockE2ETest` (2)

| # | Tes | Rekomendasi |
|---|---|---|
| 23 ✅ | alur penuh SO → DO → jadwal → diterima → selesai | **TULIS ULANG, prioritas tinggi**: satu-satunya tes alur penuh di folder ini; ubah linimasa stok ke Tahap 3 (potong saat DO dibuat) |
| 24 ✅ | SO stok kurang ditolak; backorder; DO Siap Kirim dibatalkan; SO dibatalkan | **TULIS ULANG**: bagian reservasi SO tetap; bagian DO diganti reversal stok fisik |

## 6. `StockReconcileCommandTest` (5)

Perintah rekonsiliasi reservasi yatim masih ada dan berguna. Yang usang hanya cara tes **membuat** data yatim (dulu lewat jalur reservasi lama).

| # | Tes | Rekomendasi |
|---|---|---|
| 25 ✅ | dry-run melaporkan yatim (12 + 3) tanpa mengubah apa pun | **TULIS ULANG**: buat baris `StockReservation` yatim langsung (insert), bukan lewat alur DO |
| 26 ✅ | `--apply` menghapus HANYA yatim | **TULIS ULANG** (fixture yang sama) |
| 27 ✅ | `qty_reserved` tak terjelaskan hanya dilaporkan (D17) | **TULIS ULANG** |
| 28 ✅ | `--sale-order` membatasi pembersihan ke satu SO | **TULIS ULANG** |
| 29 ✅ | DO/SO terhapus juga dianggap yatim | **TULIS ULANG** |

## 7. `StockReservationLedgerTest` (4)

| # | Tes | Rekomendasi |
|---|---|---|
| 30 🗑️ | [ledger] DO Siap Kirim → reservasi DO terpetakan ke item SO; Dikirim → dikonsumsi | **PENSIUNKAN**: reservasi DO tidak lagi bertahan di Siap Kirim |
| 31 ✅ | [ledger] DO ditutup/ditolak/dihapus melepas reservasi; SO dibatalkan melepas semua | **TULIS ULANG**: pertahankan bagian SO dibatalkan; bagian DO jadi assert reversal stok fisik |
| 32 🗑️ | [ledger] DO dua gudang sumber menahan per gudang; qty turun memotong sumber TERAKHIR lebih dulu | **PENSIUNKAN** bagian reservasi; **tulis tes baru** untuk potongan stok per gudang sumber dan pengurangan qty (sudah sebagian di `DeliveryOrderSourceHardeningTest`) |
| 33 🗑️ | [flag mati] reservasi DO/edit kuantitas berperilaku lama | **PENSIUNKAN** |

## 8. Lain-lain (4)

| # | Tes | Rekomendasi |
|---|---|---|
| 34 ✅ | `StockReservationSpecTest` — tidak ada double reservasi: DO Siap Kirim memindahkan reservasi SO | **TULIS ULANG**: tidak ada double penahanan — SO menahan sisa, fisik terpotong di DO, total tidak berlipat |
| 35 🗑️ | `StockScenarioProbeTest` [flag mati] reservasi baru terbentuk di tahap DO Approved | **PENSIUNKAN** (karakterisasi perilaku lama) |
| 36 🗑️ | `StockScenarioProbeTest` [flag mati] jadwal "Tandai Selesai" dari pending… reservasi yatim (X1) | **PENSIUNKAN** |
| 37 ✅ | `StockScenarioProbeTest` [flag mati] DO gagal setelah Dikirim → dikirim ulang memotong stok dua kali (F6) | **PUTUSKAN** (catatan C): tes ini mendokumentasikan bug lama F6; verifikasi dulu apakah sudah tidak terjadi lalu ganti dengan tes anti-regresi |

## Catatan yang perlu diputuskan atau diselidiki

**A. `approved → completed` diizinkan.** Konfirmasi dengan Ko Handy bahwa "Selesaikan" langsung dari Siap Kirim memang disengaja (aksi Selesaikan di UI memang tampil untuk `approved`). Jika ya, dataset #5 menjadi `true`.

**B. Pemeriksaan stok fisik saat Kirim (D15) dan pengecualian Owner.** Karena stok kini dipotong saat DO dibuat, pemeriksaan "stok kurang saat Kirim" praktis tidak tercapai. Pilihan: (1) hapus `assertPhysicalStock` dan fitur pengecualian di Kirim, pindahkan penolakan ke pembuatan DO (sudah berlaku via penjaga `StockMovementObserver`); (2) pindahkan fitur pengecualian Owner/Super Admin ke pembuatan DO (meta `allow_negative_stock` sudah ada). Empat tes (#8, #9, #10, #15) mengikuti keputusan ini.

**C. Gagal kirim sebelum Dikirim.** Bila DO `approved` (stok sudah terpotong) lalu `delivery_failed`, apakah stok dikembalikan? Perilaku sekarang belum jelas dari tes; #13 dan #37 menunggu keputusan.

**D. Potensi masalah nyata (bukan sekadar tes usang).** Di `DeliveryOrderItem::booted` (updated), edit kuantitas item DO saat `approved` dengan flag `sales.stock.ledger` aktif memanggil `DeliveryOrderReservations::sync`, yang **membuat ulang reservasi DO** padahal stok fisik sudah terpotong — risiko penahanan ganda. Flag default mati, tetapi `Tahap3StockDeductionTimingTest` menyalakannya. Perlu dicek bersama tes #19 sebelum menulis ulang.

**D2. DO ditolak (`reject`) masih memegang kuantitas SO.** Ditemukan saat mengerjakan #31: `RELEASED_STATUSES` hanya berisi `closed`, jadi DO `reject` tetap dihitung "in_process" untuk SO, padahal stok fisiknya sudah dikembalikan. Akibatnya reservasi SO tidak menahan kuantitas itu dan stok bebas tampak lebih besar dari kebutuhan nyata, sampai DO diajukan ulang atau dihapus. Perlu keputusan: lepaskan kuantitas DO ditolak ke SO (tambahkan `reject` ke `RELEASED_STATUSES`) atau pertahankan sebagai tahanan sementara.

**E. Kode mati.** `SaleOrderObserver::handleStockReductionForSelfPickup` tampaknya tidak terpanggil lagi karena `updating()` langsung `return`. Pensiunkan bersama tes #11, #17, #20.

## Urutan pengerjaan yang disarankan

1. #19 dan #23 (checker edit qty dan alur penuh): bernilai tinggi dan terkait bagian D laporan UAT.
2. Tulis ulang sepele: #5, #6, #12, #14.
3. Tulis ulang angka: #1–#4, #7, #16, #18, #22, #24, #34.
4. Fixture yatim: #25–#29.
5. Pensiunkan: #11, #17, #20, #21, #30, #32, #33, #35, #36.
6. Setelah keputusan A–C: #8, #9, #10, #13, #15, #37.
