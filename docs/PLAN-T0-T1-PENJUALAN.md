# Rencana Pelaksanaan T0 & T1 — Penjualan

> Dibuat 20 September 2026 · Turunan dari `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` (§7). **STATUS: T0 & T1 SELESAI dan lulus gerbang regresi — lihat "Status pelaksanaan — T0 & T1" di dokumen audit.** Isi di bawah adalah rencana aslinya. Pelaksanaan dimulai setelah Anda menyetujui bagian §9 (keputusan & prasyarat).
> Cakupan: **T0** (baseline, keputusan, tooling uji) dan **T1** (quick wins: usulan 10, 13, 15a, 18a, 19a, 20a, X7).

## Daftar Isi
1. [Tujuan, ruang lingkup, dan yang sengaja tidak dikerjakan](#1-tujuan-ruang-lingkup-dan-yang-sengaja-tidak-dikerjakan)
2. [Koreksi terhadap dokumen audit (hasil pemeriksaan lanjutan)](#2-koreksi-terhadap-dokumen-audit-hasil-pemeriksaan-lanjutan)
3. [Urutan pengerjaan & estimasi](#3-urutan-pengerjaan--estimasi)
4. [T0 — rincian tugas](#4-t0--rincian-tugas)
5. [T1 — rincian tugas](#5-t1--rincian-tugas)
6. [Rencana tes & regresi](#6-rencana-tes--regresi)
7. [Skrip UAT manual T1](#7-skrip-uat-manual-t1)
8. [Rencana commit, cabang, rollout & rollback](#8-rencana-commit-cabang-rollout--rollback)
9. [Keputusan & prasyarat yang saya butuhkan](#9-keputusan--prasyarat-yang-saya-butuhkan)
10. [Risiko](#10-risiko)

---

## 1. Tujuan, ruang lingkup, dan yang sengaja tidak dikerjakan

**Tujuan T0:** memastikan pekerjaan T1–T8 dapat dibuktikan tidak merusak apa pun — suite uji bisa dijalankan tuntas, ada daftar "gagal-yang-sudah-ada" yang terkunci, repo bersih, dan keputusan D1–D16 tercatat.

**Tujuan T1:** memberi perbaikan yang langsung terlihat oleh pengguna tanpa menyentuh logika stok/jurnal: pesan berbahasa Indonesia, No. Faktur Pajak di invoice penjualan, referensi & bukti transfer pada penerimaan, angka & label yang benar di layar, alat ukur performa, dan laporan audit data customer (read-only).

| Di dalam ruang lingkup | Sengaja **tidak** dikerjakan (tahap lain) |
|---|---|
| Tooling uji (batas memori, runner terpotong, baseline) | Memperbaiki ±216 tes lama non-penjualan (dicatat saja) |
| `lang/id/*` (validation, auth, passwords, pagination) | Pemetaan label status 25 kolom (T7) |
| Faktur pajak: form, daftar, filter, View, PDF, aksi pengisian setelah terbit | Ekspor PPN Keluaran (opsional, lihat §5 T1.2) |
| Referensi & bukti transfer penerimaan (kolom, form, View, daftar) | Cetak kwitansi (T6 — kwitansi belum tersambung ke penerimaan) |
| Nilai DO yang benar (satu sumber), label pilihan, kolom "Sisa Qty" di daftar SO | Perubahan reservasi/stok/jurnal (T2) |
| Profiler request + laporan, panduan `.env`, opcache dev | Menurunkan `Log::info` massal & anggaran query (T7, berdasarkan data ukur) |
| `customers:audit-duplicates`, `customers:audit-credit-limit` (read-only) | Gabung customer & kode baru (T4) |

---

## 2. Koreksi terhadap dokumen audit (hasil pemeriksaan lanjutan)

Pemeriksaan kode untuk menyusun rencana ini menemukan hal yang mengubah sebagian butir T1. Dokumen audit sudah saya perbarui agar konsisten.

| # | Temuan baru | Dampak pada rencana |
|---|---|---|
| K-1 | Invoice yang **dibuat otomatis** dari DO bertatus **`unpaid`** (bukan `draft`), sehingga `InvoicePolicy::update` (hanya draft) **menolak** pengeditan. Jika field faktur pajak hanya ada di form Edit, **invoice otomatis tidak akan pernah bisa diberi nomor faktur**. | T1.2 menambah **aksi khusus "Isi No. Faktur Pajak"** (bukan field keuangan → tidak memicu re-posting jurnal; `InvoiceObserver` hanya memantau `subtotal,total,ppn_rate,invoice_date,other_fee`). |
| K-2 | **Kwitansi belum tersambung ke penerimaan** — `pdf/kwitansi*.blade.php` tidak dipakai oleh resource/route mana pun. | "Tampil di kwitansi" dikeluarkan dari T1.3 → T6. |
| K-3 | `.env.production.example` sudah `LOG_LEVEL=error`; `.env` dev `debug`; `LOG_LEVEL=info` **tidak** menghilangkan `Log::info`. Level yang benar-benar menurunkan volume: `warning`/`error`. | T1.6: panduan `.env` UAT (`warning`), profiler dulu, **tidak** menurunkan `Log::info` secara massal di T1. |
| K-4 | **Tiga** perhitungan nilai DO berbeda: `DeliveryOrder::getTotalAttribute` (naif), PDF DO (`TaxService::compute`, pembulatan lain), dan observer invoice (`LineAmounts`, benar). | T1.4: satu `DeliveryOrderValuation` berbasis `LineAmounts`, dipakai label, PDF DO, dan (dengan tes paritas) observer. |
| K-5 | Workflow CI (`.github/workflows/tests.yml`) adalah bawaan Laravel: menyalin `.env.example` (tidak ada), tanpa MySQL → **tidak dapat berjalan**. | Dicatat sebagai temuan; tidak diperbaiki di T0 (perlu keputusan infrastruktur). Runner terpotong T0 dibuat agar bisa dipakai CI kelak. |
| K-6 | Ada perintah `audit:inventory-consistency` (qty_available vs riwayat gerakan). | Dicatat untuk T2 (invarian reservasi akan melengkapinya, bukan duplikat). |
| K-7 | Baseline gagal ±216 tes terkonsentrasi: 36 tes `RekonsiliasiBankPage` (halaman **sudah tidak ada**), 33 `PurchaseInvoiceResourceTest` (tanda tangan closure), ±40 Order Request/PO — hampir semuanya **bukan penjualan**; tetapi 9 tes reservasi stok + 5 `InvoiceObserverPostSalesTest` + beberapa tes penjualan **relevan dengan T2**. | T0.5: triase; hanya yang relevan T2/penjualan diselidiki. |

---

## 3. Urutan pengerjaan & estimasi

Satu pengembang; jam efektif (h) termasuk tes & dokumentasi. Panah = dependensi.

| Urut | Kode | Tugas | Est. | Bergantung pada |
|---|---|---|---|---|
| 1 | T0.1 | Rapikan repo (commit revert + perbaikan audit + dokumen) | 1 h | persetujuan §9-A |
| 2 | T0.2 | Tetapkan keputusan D1–D16 di dokumen audit | 1 h | §9-B |
| 3 | T0.3 | `MemoryLimit::raiseTo()` menggantikan 5 `ini_set` | 2 h | — |
| 4 | T0.4 | Runner suite terpotong + pembanding + `baseline-failures.txt` | 4 h | T0.3 |
| 5 | T0.5 | Triase baseline (fokus tes penjualan/stok) | 4–8 h | T0.4 |
| 6 | T0.6 | Diagnostik lingkungan UAT (perintah untuk Anda jalankan) | 1 h | — (paralel) |
| — | **Gerbang T0** | Suite penuh tuntas + baseline terkunci | | |
| 7 | T1.1 | `lang/id/*` | 3 h | Gerbang T0 |
| 8 | T1.4 | `DeliveryOrderValuation` + label + PDF DO + kolom paritas | 5 h | |
| 9 | T1.5 | Kolom "Sisa Qty" daftar SO (batch) | 2 h | |
| 10 | T1.8 | Teks bantu form Invoice (X7) | 1 h | T1.4 |
| 11 | T1.2 | Faktur pajak (form, aksi, daftar, filter, View, PDF) | 6 h | |
| 12 | T1.3 | Referensi & bukti transfer penerimaan | 6 h | |
| 13 | T1.6 | Profiler request + `perf:report` + panduan env + opcache dev | 6 h | |
| 14 | T1.7 | `customers:audit-duplicates` + `customers:audit-credit-limit` | 8 h | |
| — | **Gerbang T1** | Regresi vs baseline (0 kegagalan baru) + UAT §7 | 3 h | |

**Total: T0 ≈ 13–17 jam (≈ 2 hari) · T1 ≈ 40 jam (≈ 5 hari) · gerbang & dokumentasi ≈ 1 hari → ≈ 7–8 hari kerja** (audit sebelumnya memperkirakan 3–4 hari T1 + 0,5–1 hari T0; rencana rinci ini lebih realistis karena K-1, K-4, dan profiler).

---

## 4. T0 — rincian tugas

### T0.1 Rapikan repo (1 jam) — **butuh persetujuan Anda (§9-A)**
Keadaan saat ini (cabang `chore/cleanup-artifacts`): revert `8eedb05` **staged** (`AppServiceProvider.php` kembali ke mask Indonesia `$money($input, ',', '.', 2)`), plus perubahan tak ter-commit dari audit ulang Fase 1–6, dan dua dokumen baru.

Rencana commit (tiga commit terpisah agar mudah ditinjau/dibatalkan):
1. `revert: kembalikan mask $money ke format Indonesia` — hanya `AppServiceProvider.php` (yang sudah staged).
2. `fix(penjualan): audit ulang fase 1-6 — flag AR penerimaan basi, cakupan soft-delete, memo laporan, izin tes API, uji e2e` — file `app/Observers/CustomerReceiptObserver.php`, `app/Services/{QuotationService,DeliveryOrderSourceValidator}.php`, `app/Filament/Pages/SalesReportPage.php`, `tests/Feature/*` (Phase 2/3/5A/6, Api, e2e), `docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md`.
3. `docs(penjualan): audit 20 usulan & rencana T0-T1` — kedua dokumen baru.
Lalu buat cabang kerja `feat/penjualan-t1-quick-wins` (lihat §8).
**Selesai bila:** `git status` bersih; tiga commit tercatat; tes fase (200) masih lolos.

### T0.2 Keputusan (1 jam)
Salin tabel D1–D16 ke bagian baru "Keputusan (final)" di `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` dengan kolom "Diputuskan oleh/tanggal". Yang **wajib** sebelum T1: K-A…K-D di §9-B (khusus T1). D1–D15 wajib sebelum T2/T3 (bukan penghambat T1).

### T0.3 Batas memori tidak lagi menimpa (2 jam)
- **Masalah (X9):** `IncreaseMemoryLimit` middleware dan 4 API controller memanggil `ini_set('memory_limit','512M')` — di proses uji ini **menurunkan** `-d memory_limit=-1` menjadi 512M sehingga suite penuh crash (terbukti pada baseline maupun tree saat ini). Di produksi, bila `php.ini` lebih besar dari 512M, request juga **diturunkan** (bukan tujuan aslinya).
- **Perubahan:**
  - Baru `app/Support/MemoryLimit.php`: `raiseTo(string $target = '512M'): void` — membaca `ini_get('memory_limit')`; `-1`/tak terbatas → tidak diubah; bila batas saat ini < target → `ini_set`. Helper `toBytes()` (K/M/G).
  - Ganti `ini_set('memory_limit','512M')` di `app/Http/Middleware/IncreaseMemoryLimit.php` dan `Api/{PurchaseOrder,SaleOrder,OrderRequest,Quotation}ApiController.php` menjadi `MemoryLimit::raiseTo('512M')`. (Perintah CLI `Legacy*` yang memakai `1024M` tidak disentuh.)
- **Tes:** `tests/Unit/MemoryLimitTest.php` — `toBytes` (`512M`, `1G`, `-1`, `256K`, angka polos); `raiseTo` tidak menurunkan; menaikkan bila lebih rendah (memakai `ini_set` di dalam tes lalu dipulihkan).
- **Perubahan perilaku produksi (dicatat):** bila `php.ini` menetapkan > 512M, request kini mempertahankan nilai itu (sebelumnya diturunkan ke 512M). Bila = 256M, tetap dinaikkan ke 512M seperti sebelumnya.
- **Selesai bila:** `php -d memory_limit=-1 vendor/bin/pest` (satu proses) tidak lagi crash karena batas 512M — atau, bila masih ada kebocoran memori nyata di suite, penyebabnya teridentifikasi (dicatat; runner terpotong T0.4 tetap jalan keluar).

### T0.4 Runner suite terpotong + pembanding + baseline (4 jam)
- **Baru `scripts/run-tests-chunked.php`:** opsi `--size=30` (berkas/potongan), `--dir=tests` , `--out=storage/test-runs/<cap-waktu>`, `--compare=tests/baseline-failures.txt`, `--db=NAMA_test` (opsional; menyetel `DB_DATABASE` untuk proses anak — **wajib berakhiran `_test`** sesuai penjaga `tests/TestCase.php`; ini memungkinkan dua run paralel di DB berbeda seperti yang saya lakukan saat audit). Langkah: `config:clear` → daftar berkas `*Test.php` terurut → potong → jalankan tiap potongan **berurutan** sebagai proses `php -d memory_limit=-1 vendor/bin/pest --log-junit=<out>/chunk-NN.xml <berkas…>` → gabungkan hasil.
- **Parsing memakai JUnit XML** (bukan teks terminal) → nama tes stabil: `Kelas :: nama tes`; status gagal/error/skip; potongan yang crash (tanpa XML) dilaporkan sebagai "CRASH: <berkas>" alih-alih hilang diam-diam.
- **Baru `scripts/compare-test-failures.php <baseline> <sekarang>`:** mencetak *kegagalan baru*, *sudah diperbaiki*, dan *tetap gagal*; exit code ≠ 0 bila ada kegagalan baru.
- **Composer:** `test:chunked`, `test:compare` (alias). Dokumentasi singkat di `TESTING_SAFETY.md` (tambahan: jangan jalankan dua runner pada DB yang sama).
- **Baseline:** jalankan pada commit T0.1 → simpan **`tests/baseline-failures.txt`** (nama tes gagal, terurut; header berisi hash commit + tanggal + versi PHP/Pest). Diperkirakan ±216 baris (hasil audit: 216 gagal di tree ini, 212 di c1c4c72).
- **Tes runner:** `tests/Unit/CompareTestFailuresTest.php` (fixture kecil: baru/diperbaiki/tetap; parsing JUnit contoh).
- **Selesai bila:** satu perintah menghasilkan ringkasan tuntas seluruh suite (~20 menit) dan pembandingan otomatis; baseline ter-commit.

### T0.5 Triase baseline (4–8 jam, dibatasi waktu)
Klasifikasi ±216 kegagalan (hasil awal dari audit, akan dikonfirmasi setelah runner baru):

| Kelompok | Jumlah | Penyebab awal | Tindakan T0 |
|---|---|---|---|
| `RekonsiliasiBankPage*Test` | 36 | Halaman `RekonsiliasiBankPage` **tidak ada di `app/`** — tes usang | Catat "usang"; usul: hapus/skip beralasan (**keputusan Anda**, di luar penjualan) |
| `PurchaseInvoiceResourceTest` | 33 | Tanda tangan closure `$get` berubah | Catat "Pembelian" — tidak disentuh |
| `OrderRequest*`, `PurchaseOrder*`, `PurchaseReturn*`, QC | ±60 | Pembelian; approve action, FK, dll. | Catat "Pembelian" |
| **Reservasi stok**: `StockReservationServiceTest` (5), `StockReservationFlowTest` (4) | 9 | "Stok tidak mencukupi… Tersedia: 0" — dugaan fixture: pembuatan produk sudah membuat baris `InventoryStock` (nol) sehingga `InventoryStock::create` menghasilkan baris kedua; tes membaca yang salah (dilihat juga di probe audit) | **Selidiki** — relevan langsung untuk T2 (reservasi) |
| `InvoiceObserverPostSalesTest` | 5 | mis. "Jurnal tidak seimbang: Debit AR 111.000.000 ≠ Kredit 11.000.000" | **Selidiki** — bisa cacat nyata jurnal invoice penjualan |
| `BranchInheritanceFlowTest` (3), `SalesInvoiceShippingCostJournalTest` (2), `SaleOrderFeatureTest` (2), `InvoiceEditAndDeliveryOrderTest` (3), `UATUIUXAuditVerificationTest` (3), Api (5) | 18 | Invoice Rp0, mock `Auth::id()`, ID keras, dll. | **Selidiki** — perbaiki tes bila cacat fixture; bila cacat kode penjualan → masuk backlog T2/T5 |
| Lain-lain (aset, deposit, journal, dll.) | sisanya | beragam | Catat saja |

Keluaran: tabel `docs/BASELINE-TES.md` (kelompok → penyebab → status: *usang / pembelian / diperbaiki / backlog T2*), dan baseline diperbarui bila ada tes yang diperbaiki. **Batas waktu 1 hari**; yang tidak selesai tetap tercatat sebagai "belum dianalisis".
**Selesai bila:** semua tes penjualan/stok yang gagal punya status jelas; tidak ada tes yang **bergantung urutan** di area penjualan (tes `UATUIUX…` yang memakai `cabang_id => 1` diperbaiki agar membuat datanya sendiri).

### T0.6 Diagnostik lingkungan UAT (1 jam; **Anda yang menjalankan**)
Agar T1.6/T7 berbasis data, mohon jalankan di server UAT dan kirimkan hasilnya (tanpa rahasia):
```bash
php artisan about --only=environment,cache,drivers
php -i | grep -E "^opcache\.(enable|memory_consumption|validate_timestamps)|^memory_limit|^max_execution_time"
ls -lh storage/logs | tail -5
php artisan tinker --execute='foreach(["customers","products","sale_orders","delivery_orders","invoices","stock_movements","journal_entries","activity_log"] as $t){ echo str_pad($t,20), DB::table($t)->count(), PHP_EOL; }'
```
(Hasil: mode debug, driver sesi/cache/antrean, opcache aktif atau tidak, ukuran log, ukuran tabel.)

### Gerbang T0
Suite penuh tuntas lewat runner; `tests/baseline-failures.txt` ter-commit; keputusan tercatat; repo bersih. **Tanpa gerbang ini T1 tidak dimulai.**

---

## 5. T1 — rincian tugas

Semua tugas dikerjakan di cabang `feat/penjualan-t1-quick-wins`, **satu commit per tugas**, tiap tugas diakhiri: tes barunya lolos → tes area terkait lolos → `pint` pada berkas baru → (di akhir) regresi vs baseline.

### T1.1 Terjemahan Indonesia bawaan Laravel (usulan 15a) — 3 jam
- **Akar masalah (terverifikasi):** `lang/` hanya berisi `vendor/` (Filament); tidak ada `lang/id/`. Laravel hanya mengirim terjemahan Inggris (`vendor/laravel/framework/src/Illuminate/Translation/lang/en`); `trans('validation.required')`, `auth.failed`, `passwords.reset`, `pagination.next` semuanya mengembalikan kunci mentah (diperiksa via tinker). Komponen `app/Livewire/Auth/*` memakai `auth.failed/throttle/password` dan status `passwords.*`.
- **Berkas baru:** `lang/id/validation.php` (mengikuti 147 kunci Laravel 12, termasuk kelompok `array/file/numeric/string` bertipe, plus `attributes` untuk field umum: `name`, `email`, `password`, `customer_id`→"customer", `product_id`→"produk", `quantity`→"kuantitas", `unit_price`→"harga satuan", `invoice_date`, `due_date`, `total_payment`, `payment_method`, `coa_id`→"akun", `tax_invoice_number`→"nomor faktur pajak", `payment_reference`→"nomor referensi", dst.), `lang/id/auth.php`, `lang/id/passwords.php`, `lang/id/pagination.php`.
- **Tidak diubah:** 469 `validationMessages([...])` yang sudah ada tetap berlaku (mereka menimpa pesan bawaan); terjemahan Filament di `lang/vendor` tidak disentuh.
- **Tes:** `tests/Unit/LangIndonesianTest.php`
  1. setiap kunci di berkas `en` Laravel ada di `id` dan **berbeda dari kunci mentahnya** (`trans($key) !== $key`) — untuk validation/auth/passwords/pagination;
  2. `Validator::make(['x'=>null], ['x'=>'required'])` → pesan berbahasa Indonesia mengandung nama atribut;
  3. atribut kustom (`customer_id`) terpakai;
  4. pesan berparameter (`min.string`, `between.numeric`) terisi;
  5. tes pemindai kecil: tidak ada string `validation.` mentah pada respons form Filament kosong untuk satu resource sampel (mis. buat Customer tanpa isian) — memastikan tidak ada kunci mentah di layar.
- **Selesai bila:** tes lolos; login salah/limit → pesan Indonesia; form tanpa pesan kustom tidak menampilkan `validation.*`.
- **Risiko/rollback:** sangat rendah; hapus `lang/id/*` mengembalikan perilaku lama.

### T1.4 Satu sumber nilai DO + label yang informatif (usulan 18a, K-4) — 5 jam
- **Baru `app/Services/DeliveryOrderValuation.php`:** `forDeliveryOrder(DeliveryOrder $do): array` → `{lines[], dpp, ppn, total_lines, additional_cost, total}`; memakai `SalesInvoiceTaxResolver` + `SalesInvoiceLineBuilder`/`LineAmounts` **persis seperti** `DeliveryOrderObserver::createInvoiceForCompletedDeliveryOrder` (harga & diskon% & tarif & tipe pajak dari item SO; fallback `product.sell_price` bila tanpa item SO). Versi batch `forDeliveryOrders(iterable)` (eager-load sekali).
- **Perubahan:**
  1. `DeliveryOrder::getTotalAttribute()` → mendelegasikan ke valuation (`->total`), agar semua pemakai lama otomatis benar. (Tautan/harga hilang → 0 dengan `Log::warning`, bukan diam.)
  2. `SalesInvoiceResource` (opsi DO ~baris 262): label `DO-… · 12 Sep · Rp 183.208,83`; **opsi SO** (~baris 141–149): `SO-00004 · PT X · 12 Sep 2026 · Rp …`; **Quotation** di `SaleOrderResource` (~522): `QO-… · Customer · valid s.d. …`.
  3. `resources/views/pdf/delivery-order.blade.php` (blok @php baris ~135–175): ganti perhitungan `TaxService::compute` dengan `DeliveryOrderValuation` (tampilan kolom sama; **angka** kini konsisten dengan invoice).
  4. `DeliveryOrderObserver` (invoice otomatis) memakai valuation yang sama **hanya bila** tes paritas §Tes lolos; jika ada selisih pembulatan, observer **tidak diubah** dan selisih dicatat (angka invoice yang sudah benar tidak boleh bergeser).
- **Tes:** `tests/Feature/DeliveryOrderValuationTest.php`
  1. DO 12 dari SO 20 × Rp8.687 diskon 5% PPN 11% eksklusif → nilai = 12/20 × 183.208,83 (pembulatan LineAmounts) dan **= total invoice otomatis** dari DO yang sama (paritas);
  2. SO inklusif; SO tanpa pajak; diskon 0; DO dengan `additional_cost`;
  3. item DO tanpa item SO → fallback `sell_price`; tanpa keduanya → 0 + warning;
  4. label opsi invoice memuat customer/tanggal/nilai; pilihan SO memuat nama customer;
  5. teks PDF DO memuat total yang sama dengan valuation (parse teks PDF/HTML render);
  6. batch: 20 DO = jumlah query tetap (bukan N).
- **Selesai bila:** tidak ada lagi label DO "Rp0" untuk DO yang punya item bertaut; tiga tempat menampilkan angka identik.
- **Risiko:** sedang-rendah (PDF DO & label). Rollback: `git revert` commit tugas ini.

### T1.5 Kolom "Sisa Qty" di daftar SO (usulan 18a) — 2 jam
- `SaleOrderDeliveryProgress::forSaleOrders(iterable $ids): array` (baru; **2–3 query** total: id item → `forItems` → agregasi per SO) — *tidak* mengubah logika `forItems`.
- `SaleOrderResource::table()`: `TextColumn::make('remaining_qty')` "Sisa Belum Dikirim", nilai dari memo per-halaman (`getTableRecords()` → sekali hitung), default terlihat, `toggleable`; SO `completed/closed/canceled` menampilkan `–`.
- **Tes:** `tests/Feature/SaleOrderListRemainingQtyTest.php` — SO 20 dengan DO 12 terkirim → "8"; 25 SO di satu halaman → jumlah query kolom konstan (uji batas query); SO tanpa DO → sama dengan qty.
- **Selesai bila:** kolom benar dan tidak menambah N+1 (dibuktikan tes batas query).

### T1.8 Teks bantu form Invoice (X7) — 1 jam
- Di `SalesInvoiceResource` bagian pilihan SO: teks bantu "Hanya SO berstatus *Selesai* yang muncul di sini. Untuk pengiriman bertahap, invoice terbit **otomatis per DO** saat DO selesai." + placeholder bila daftar kosong. Tes: render form → teks ada (Livewire `assertSee`).

### T1.2 No. Faktur Pajak pada invoice penjualan (usulan 13, K-1) — 6 jam
- **Sudah ada:** kolom `invoices.tax_invoice_number` (fillable), validasi duplikat versi pembelian di `PurchaseInvoiceAccountingService` (global, mengecualikan diri sendiri & soft-delete).
- **Baru:** `app/Rules/TaxInvoiceNumber.php` — menerima 16 digit dengan pemisah opsional (`010.000-26.12345678` maupun `0100002612345678`), menyimpan bentuk **ternormalisasi `000.000-00.00000000`**; kosong = lolos (peringatan, bukan blokir — **D16**).
- **Perubahan (`SalesInvoiceResource` & halaman):**
  1. Form (draft): `TextInput::make('tax_invoice_number')` di section header setelah `invoice_date` — label "No. Faktur Pajak", placeholder, aturan `TaxInvoiceNumber` + cek duplikat sisi-server (global, kecuali diri sendiri).
  2. **Aksi baru `set_tax_invoice_number`** ("Isi No. Faktur Pajak") di tabel & header halaman View, tampil untuk invoice **non-draft dan bukan canceled**; modal 1 field; menyimpan langsung (tanpa menyentuh field keuangan → tidak memicu re-posting jurnal — dibuktikan tes). Otorisasi: `InvoicePolicy::updateTaxNumber($user,$invoice)` = izin **`update invoice`** (izin khusus menyusul di T3, **§9-B K-C**).
  3. Daftar: kolom `tax_invoice_number` (toggleable, terlihat default, dapat dicari); filter **"Faktur pajak"** = *Sudah ada / Belum ada (PKP ber-PPN)* — "Belum ada" hanya invoice dengan `ppn_amount > 0` (atau `tipe_pajak ≠ None`), status ≠ draft/canceled.
  4. View: entry "No. Faktur Pajak" (+ badge merah "Belum diisi" bila PPN > 0 dan kosong).
  5. PDF `sale-order-invoice.blade.php`: baris "No. Faktur Pajak: …" bila terisi.
  6. **Opsional (0,5 hari, bila disetujui §9-B K-D):** ekspor CSV "Daftar PPN Keluaran" (no. faktur, tanggal, customer, NPWP, DPP, PPN) memakai pola `app/Exports/*` yang ada.
- **Tes:** `tests/Feature/SalesInvoiceTaxNumberTest.php`
  1. format sah/tak sah (16 digit; huruf; terlalu pendek) & normalisasi;
  2. duplikat ditolak (sesama penjualan dan terhadap invoice pembelian); mengedit invoice sendiri tidak dianggap duplikat;
  3. aksi mengisi nomor pada invoice **`unpaid`** hasil DO otomatis berhasil, **jurnal & AR tidak berubah** (jumlah baris jurnal sama, total sama);
  4. aksi tidak tampil untuk draft/canceled dan untuk pengguna tanpa izin;
  5. filter "Belum ada" hanya menampilkan invoice ber-PPN tanpa nomor; kolom & View menampilkan nomor;
  6. PDF memuat nomor.
- **Selesai bila:** invoice otomatis dapat diberi nomor; filter membantu rekonsiliasi; tidak ada efek samping jurnal.
- **Risiko:** rendah-sedang (aksi baru di dokumen berjurnal) — dibatasi hanya satu kolom non-keuangan, tercatat di `activity_log` (model sudah `LogsGlobalActivity`).

### T1.3 Referensi transfer & bukti pada Penerimaan (usulan 10, K-2) — 6 jam
- **Migrasi baru (aditif):** `customer_receipts`: `payment_reference` (string 100, nullable, index), `bank_name` (string 100, nullable), `proof_path` (string, nullable). `down()` menghapusnya. Tambahkan ke `$fillable` `CustomerReceipt`.
- **Penyimpanan bukti:** disk **privat** (`local`) direktori `customer-receipts/proofs`, **bukan** `public` (pola `VendorPaymentResource` memakai disk publik — bukti transfer memuat data rekening; sengaja tidak ditiru). Unduh/pratinjau lewat rute berautentikasi baru `customer-receipts/{receipt}/proof` (middleware `auth` + `can:view,receipt`), mengembalikan `Storage::disk('local')->response(...)`.
- **Form (`CustomerReceiptResource`):**
  - `payment_reference` — **wajib** bila `payment_method ∈ {Transfer, Giro, Cheque}` (`Cash` dan `Deposit` tidak perlu); label dinamis ("No. Referensi Transfer" / "No. Giro" / "No. Cek").
  - `bank_name` — tampil untuk non-tunai.
  - `proof_path` — `FileUpload` (pdf/jpg/png, ≤ 2 MB, disk privat), **opsional** (D-lihat §9-B K-B) tetapi daftar memberi penanda "tanpa bukti".
  - Peringatan (bukan blokir) duplikat: kombinasi `payment_reference + coa_id + total_payment` sudah pernah tercatat pada penerimaan lain → notifikasi kuning; pengecekan sisi-server saat simpan.
  - Halaman Edit ikut (satu form). `CreateCustomerReceipt::mutateFormDataBeforeCreate` tidak membuang kolom baru.
- **Tampilan:** View (`payment_reference`, `bank_name`, tautan "Lihat bukti" bila ada); daftar: kolom `payment_reference` (toggleable, dapat dicari) dan filter "Tanpa bukti" (non-tunai tanpa `proof_path`).
- **Tes:** `tests/Feature/CustomerReceiptReferenceProofTest.php`
  1. Transfer tanpa referensi → galat validasi; Cash/Deposit tanpa referensi → lolos;
  2. referensi tersimpan; `proof_path` tersimpan di disk privat (`Storage::fake('local')`), **tidak** ada di disk `public`;
  3. rute bukti: pengguna berizin 200; tanpa izin 403; tamu → login;
  4. peringatan duplikat muncul, simpan tetap berhasil;
  5. penerimaan lama (kolom kosong) tetap tampil normal; filter "Tanpa bukti" benar;
  6. **regresi:** seluruh `CustomerReceiptControlsPhase5ATest` tetap lolos (alokasi, deposit, cabang).
- **Selesai bila:** transfer tanpa referensi ditolak; bukti dapat diunggah/dilihat hanya oleh yang berizin; tes Fase 5A tidak berubah.
- **Risiko:** sedang-rendah (menyentuh form penerimaan berbasis JavaScript ber-state). Mitigasi: field baru berada di section terpisah; tes Livewire `CreateCustomerReceipt` penuh dijalankan.

### T1.6 Alat ukur performa + panduan lingkungan (usulan 19a, K-3) — 6 jam
Prinsip: **ukur di UAT dulu, baru optimasi.** Aman untuk data UAT karena tidak menjalankan aksi apa pun sendiri — hanya mencatat apa yang pengguna lakukan.
- **`config/perf.php`:** `enabled` (`PERF_PROFILE`, default `false`), `min_ms` (`PERF_MIN_MS`, 300), `min_queries` (`PERF_MIN_QUERIES`, 40), `sample` (1.0), `channel` (`perf`).
- **`config/logging.php`:** channel `perf` (daily, `storage/logs/perf.log`, retensi 7 hari, level info).
- **`app/Http/Middleware/ProfileRequest.php`** (grup `web` dan `api` di `bootstrap/app.php`; **tidak melakukan apa pun** bila nonaktif): bila aktif → `DB::listen` menghitung query + waktu; di `terminate()` menulis satu baris JSON bila `durasi ≥ min_ms` atau `query ≥ min_queries`: `route`, `method`, **untuk Livewire/Filament** `component` + `calls[].method` (+ argumen pertama bila string pendek, mis. nama aksi) diurai aman dari payload `/livewire/update`, `ms`, `queries`, `db_ms`, `peak_mb`, `user_id`, `cabang_id`, 5 query terlambat (SQL dipotong 200 karakter, **tanpa nilai binding** agar data tak bocor), dan deteksi N+1 (SQL ternormalisasi yang berulang ≥ 10×).
- **`php artisan perf:report {--since=1d} {--top=20} {--action=}`:** membaca `perf-*.log`, mengelompokkan per `component::method`, mencetak tabel: jumlah, rata-rata/p95/maks ms, rata-rata query, SQL berulang teratas. Membantu memilih target T7 secara objektif.
- **Panduan `.env` (`docs/production-deployment.md` + `.env.production.example`):** UAT/produksi `APP_DEBUG=false`, `LOG_LEVEL=warning` (produksi: `error`), `SESSION_DRIVER`/`CACHE_STORE` = `file` atau `redis` (bukan `database` bila tidak perlu), `QUEUE_CONNECTION` sesuai, opcache aktif; **catatan bahwa `LOG_LEVEL=info` tidak mengurangi `Log::info`**. `docker/dev/php.ini`: `opcache.enable=1`, `opcache.memory_consumption=256`, `opcache.max_accelerated_files=20000`, `opcache.revalidate_freq=2`, `realpath_cache_size=4096K` (dev; `validate_timestamps=1` tetap agar perubahan kode terbaca).
- **Tes:** `tests/Feature/ProfileRequestTest.php` — nonaktif → tidak ada log & tidak memasang listener; aktif + ambang → satu baris JSON dengan field lengkap; payload Livewire diurai (component + method); binding tidak ada di log; deteksi N+1; `perf:report` mengagregasi fixture log.
- **Selesai bila:** Anda dapat mengaktifkan `PERF_PROFILE=true` di UAT, menjalankan aksi lambat (Tandai Selesai, Approve DO, dll.), lalu `perf:report` menampilkan penyebabnya (mis. jumlah query, N+1) — **dasar keputusan T7**.
- **Risiko:** rendah (default mati; overhead hanya saat aktif). Rollback: `PERF_PROFILE=false` atau hapus middleware.

### T1.7 Audit data customer (usulan 20a) — read-only, 8 jam
Semua perintah **hanya membaca**; satu-satunya keluaran tulis adalah CSV di `storage/app/audits/`. Customer tidak memakai `CabangScope` (global), sehingga hasilnya lintas cabang.
- **`php artisan customers:audit-duplicates {--min-score=0.88} {--no-csv}`**
  - Normalisasi nama & perusahaan: huruf kecil, buang aksen/tanda baca, buang bentuk badan (`pt`, `cv`, `ud`, `pd`, `tbk`, `persero`, `koperasi`), rapikan spasi.
  - Grup duplikat (union-find) berdasarkan: (a) nama ternormalisasi identik; (b) NPWP/NIK sama (hanya digit, ≥ 15); (c) telepon/HP sama (≥ 8 digit terakhir); (d) kemiripan nama ≥ `min-score` (`similar_text`, hanya dalam ember huruf awal yang sama agar cepat dan tidak membandingkan semua pasangan).
  - Per anggota: id, kode, nama, perusahaan, NIK/NPWP, telepon, cabang, **jumlah transaksi** (`quotations`, `sale_orders`, `account_receivables`, `customer_receipts`, `customer_returns`, `other_sales`), **piutang berjalan**, **saldo deposit**, tanggal dibuat, penanda **"kode menyerupai NIK"** (kode berupa ≥ 12 digit angka), dan **saran survivor** (transaksi terbanyak, lalu tertua).
  - Ringkasan layar: jumlah grup, jumlah customer terlibat, jumlah kode menyerupai NIK, jumlah customer tanpa NPWP/NIK; CSV `customers-duplicates-YYYYMMDD-HHMM.csv`.
- **`php artisan customers:audit-credit-limit {--threshold=1000000000}`**
  - Daftar customer `Kredit` dengan `kredit_limit = 0` (kini dianggap tak terbatas oleh `CreditValidationService`), limit ≥ ambang (kemungkinan "tak terbatas", mis. 999.999.999.999), tempo 0, dan yang **piutangnya sudah melebihi limit**; kolom: limit, piutang berjalan (`CreditValidationService::getCurrentCreditUsage`), persentase, jumlah invoice jatuh tempo. CSV serupa. Catatan tegas di keluaran: **koreksi angka adalah keputusan bisnis** — perintah tidak mengubah data.
- **Tes:** `tests/Feature/CustomerAuditCommandsTest.php` — fixture: "PT Daya Teknik Medika" ×3 dengan variasi ("DAYA TEKNIK MEDIKA, PT", "P.T. Daya Teknik Medika"), NPWP sama, telepon sama, satu kode 16 digit; hasilnya satu grup dengan survivor benar; customer berbeda tidak tergabung (mis. "Daya Teknik" vs "Daya Sentosa"); **tidak ada baris di DB yang berubah** (hitung sebelum/sesudah, termasuk `updated_at`); `credit-limit`: kasus limit 0, 999.999.999.999, sudah melebihi limit; CSV terbentuk.
- **Selesai bila:** dijalankan di UAT menghasilkan CSV yang dapat Anda serahkan ke bisnis untuk memilih survivor (**D14**); tidak ada data berubah.
- **Risiko:** sangat rendah (read-only). Batas: kemiripan nama bersifat heuristik — hasil adalah *kandidat* untuk ditinjau manusia.

### Gerbang T1
Semua tes tugas lolos → **regresi lengkap vs baseline (0 kegagalan baru)** → UAT manual §7 → dokumen status pelaksanaan di `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` → PR.

---

## 6. Rencana tes & regresi

**Tes baru (perkiraan 45–55 tes, ±9 berkas):**

| Berkas | Fokus | Tugas |
|---|---|---|
| `tests/Unit/MemoryLimitTest.php` | parser & aturan hanya-menaikkan | T0.3 |
| `tests/Unit/CompareTestFailuresTest.php` | pembanding & parser JUnit | T0.4 |
| `tests/Unit/LangIndonesianTest.php` | kelengkapan & isi `lang/id` | T1.1 |
| `tests/Feature/DeliveryOrderValuationTest.php` | nilai DO, paritas invoice, label, PDF, batch | T1.4 |
| `tests/Feature/SaleOrderListRemainingQtyTest.php` | kolom sisa qty & batas query | T1.5 |
| `tests/Feature/SalesInvoiceTaxNumberTest.php` | faktur pajak, aksi, filter, PDF | T1.2 (+T1.8) |
| `tests/Feature/CustomerReceiptReferenceProofTest.php` | referensi/bukti/izin/duplikat | T1.3 |
| `tests/Feature/ProfileRequestTest.php` | profiler & `perf:report` | T1.6 |
| `tests/Feature/CustomerAuditCommandsTest.php` | audit duplikat & limit | T1.7 |

**Uji mutasi** (rusakkan penjaga → tes harus gagal) untuk: hanya-menaikkan memori, wajib referensi non-tunai, cek duplikat faktur pajak, aksi faktur tidak menyentuh jurnal, `read-only` audit customer.

**Regresi per tugas (cepat):** Fase 1–6 (200 tes) + berkas yang bersinggungan — T1.2: `SalesInvoice*`, `InvoiceLineBreakdownPhase5BTest`, `InvoiceObserver*`; T1.3: `CustomerReceipt*`, `CustomerReceiptControlsPhase5ATest`; T1.4: `DeliveryOrder*`, `SalesFlowEndToEndFase1to6Test`; T1.5: `SaleOrder*`, `SaleOrderDeliveryProgressPhase2Test`.
**Regresi penuh (gerbang):** `composer test:chunked -- --compare=tests/baseline-failures.txt` → **0 kegagalan baru**; kegagalan yang hilang (diperbaiki) dicatat dan baseline diperbarui di commit terpisah.

---

## 7. Skrip UAT manual T1

Data contoh: gunakan SO 20 × Rp8.687, diskon 5%, PPN 11% eksklusif (angka yang sama dengan audit sebelumnya). Total SO Rp183.208,83.

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka form buat Customer, kosongkan **Nama** tanpa pesan kustom, kirim | Pesan berbahasa Indonesia (tidak ada teks `validation.…`) |
| 2 | Login dengan sandi salah | Pesan Indonesia ("Kredensial tidak cocok…") |
| 3 | Penjualan → SO: lihat kolom baru **Sisa Belum Dikirim** untuk SO yang sudah dikirim sebagian | Angka = qty − terkirim (mis. 8 dari 20) |
| 4 | Invoice baru → pilih customer → lihat pilihan SO dan DO | SO: nomor · customer · tanggal · nilai. DO: nomor · tanggal · **nilai benar** (bukan Rp0) |
| 5 | Selesaikan DO → invoice otomatis terbit (status Belum Dibayar) → aksi **Isi No. Faktur Pajak** | Nomor tersimpan dan tampil di daftar/View/PDF; **jurnal & piutang tidak berubah** |
| 6 | Masukkan nomor yang sama pada invoice lain | Ditolak: sudah dipakai invoice lain |
| 7 | Filter daftar invoice **Faktur pajak → Belum ada** | Hanya invoice ber-PPN tanpa nomor |
| 8 | Penerimaan baru, metode **Transfer**, kosongkan referensi | Ditolak: referensi wajib. Isi referensi + unggah bukti → tersimpan |
| 9 | Buka View penerimaan → "Lihat bukti" (sebagai pengguna berizin dan tanpa izin) | Terbuka / ditolak. URL langsung tanpa login → halaman login |
| 10 | Penerimaan kedua dengan referensi+akun+nominal sama | Peringatan duplikat, tetap dapat disimpan |
| 11 | Set `PERF_PROFILE=true`; lakukan "Tandai Selesai" pada jadwal; `php artisan perf:report --since=1h` | Baris `…callMountedTableAction` dengan ms, jumlah query, SQL berulang |
| 12 | `php artisan customers:audit-duplicates` dan `customers:audit-credit-limit` di UAT | CSV terbentuk; **data tidak berubah** (jumlah baris customer sama) |

---

## 8. Rencana commit, cabang, rollout & rollback

- **Cabang:** T0.1 di `chore/cleanup-artifacts` (3 commit); lalu cabang baru `feat/penjualan-t1-quick-wins`. T0.3–T0.5 (tooling & baseline) masuk cabang T1 sebagai commit pertama (`chore(test): ...`) — atau cabang sendiri bila Anda ingin merge lebih awal.
- **Satu commit per tugas** (`feat(penjualan): T1.2 no. faktur pajak …`), pesan menyebut usulan & tes; catatan atribusi standar.
- **Rollout:** migrasi aditif hanya satu (T1.3); `php artisan migrate` di UAT lalu deploy. Tanpa flag fitur (perubahan aman/aditif); profiler default **mati**; perintah audit hanya dijalankan manual.
- **Rollback:** `git revert` commit tugas terkait; migrasi T1.3 punya `down()`; `lang/id` cukup dihapus; profiler `PERF_PROFILE=false`. Tidak ada data yang diubah oleh T1 (kecuali data baru dari fitur yang dipakai).

---

## 9. Keputusan & prasyarat yang saya butuhkan

### A. Untuk memulai T0
| Kode | Pertanyaan | Rekomendasi |
|---|---|---|
| **A-1** | Boleh saya meng-commit **revert `8eedb05` (mask Indonesia)** + perbaikan audit + dokumen menjadi 3 commit di `chore/cleanup-artifacts`, lalu membuat cabang `feat/penjualan-t1-quick-wins`? | **Ya** (mask Indonesia konsisten dengan seluruh repo dan Fase 1; revert memang Anda jalankan sendiri) |
| **A-2** | Tes usang `RekonsiliasiBankPage*` (36 tes; halaman tidak ada) — hapus/skip, atau dibiarkan di baseline? | **Biarkan di baseline** di T0; putuskan terpisah (di luar penjualan) |

### B. Untuk T1 (jawaban default dipakai bila tidak dijawab)
| Kode | Keputusan | Default |
|---|---|---|
| **K-A** | **D16** — nomor faktur pajak wajib? | **Peringatan saja** (kosong diizinkan); format & duplikat divalidasi bila diisi |
| **K-B** | Bukti transfer wajib diunggah? | **Opsional**; referensi **wajib** untuk Transfer/Giro/Cheque; daftar menandai "tanpa bukti" |
| **K-C** | Izin untuk aksi "Isi No. Faktur Pajak" | **Memakai `update invoice`** (izin khusus di T3) |
| **K-D** | Ekspor CSV "Daftar PPN Keluaran" di T1.2? | **Tunda** ke T5/T6 kecuali Anda ingin sekarang (+0,5 hari) |
| **K-E** | `LOG_LEVEL` UAT | **`warning`** (produksi `error`) |
| **K-F** | Ambang "limit kredit mencurigakan" | **≥ Rp1.000.000.000** (dapat diubah lewat opsi `--threshold`) |

### C. Yang perlu Anda kerjakan/berikan
1. Jalankan diagnostik **T0.6** di UAT dan kirimkan keluarannya.
2. Setelah T1.7 selesai: jalankan dua perintah audit di UAT dan kirimkan CSV (atau ringkasannya) agar keputusan **D14** (gabung customer) berbasis data.
3. Setelah T1.6: aktifkan `PERF_PROFILE=true` di UAT beberapa hari, lalu kirimkan hasil `perf:report`.
4. Konfirmasi keputusan D1–D15 sebelum T2 dimulai (bukan penghambat T1).

---

## 10. Risiko

| Risiko | Peluang | Dampak | Mitigasi |
|---|---|---|---|
| Runner terpotong tak selaras dengan format JUnit Pest versi terpasang | Sedang | Rendah | Diuji dengan fixture + satu run nyata sebelum baseline dikunci; fallback parser teks |
| Suite penuh masih crash karena kebocoran memori nyata (bukan hanya batas) | Sedang | Sedang | Runner terpotong tetap jalan; kebocoran dicatat sebagai temuan T0.5 |
| Aksi faktur pajak dipakai mengubah nomor yang sudah dilaporkan ke pajak | Rendah | Sedang | Tercatat di `activity_log`; perubahan nomor terisi meminta konfirmasi; izin dibatasi (T3 memperketat) |
| Form penerimaan (JS ber-state) terganggu field baru | Rendah | Sedang | Section terpisah; tes Livewire penuh + seluruh tes Fase 5A |
| Refactor nilai DO menggeser angka invoice otomatis yang sudah benar | Rendah | Tinggi | Observer **tidak diubah** kecuali tes paritas 100% lolos; selisih dicatat |
| Heuristik duplikat menghasilkan positif palsu | Sedang | Rendah | Hasil hanya kandidat + alasan per grup; manusia memutuskan (D14) |
| Estimasi meleset karena triase baseline lebih panjang | Sedang | Rendah | T0.5 dibatasi 1 hari; sisanya "belum dianalisis" |
