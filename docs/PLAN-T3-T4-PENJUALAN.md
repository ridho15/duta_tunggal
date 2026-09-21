# Rencana Pelaksanaan T3 & T4 — Kontrol Internal, Setting Akuntansi, Penomoran, Master Customer

> Dibuat 21 September 2026 · Turunan dari `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` §7 (T3, T4) · Usulan **6, 7, 8, 9, 12, 20b–d** + temuan **X8, X10**.
> Keputusan **D6–D14 sudah disetujui** ("setuju D6-D14", 21 September 2026). Keputusan turunan di §2.2 memakai **rekomendasi** dan tercatat; bila Anda tidak sependapat, cukup bilang — semuanya berada di balik flag sehingga mudah diubah.
> Prasyarat: T2 selesai (cabang `feat/penjualan-t2-stok`). Cabang kerja: `feat/penjualan-t3-kontrol` (dari `feat/penjualan-t2-stok`).

## Daftar Isi
1. [Tujuan dan ruang lingkup](#1-tujuan-dan-ruang-lingkup)
2. [Keputusan](#2-keputusan)
3. [Hasil pemeriksaan kode (mengubah rencana)](#3-hasil-pemeriksaan-kode)
4. [Rancangan teknis](#4-rancangan-teknis)
5. [Urutan & estimasi](#5-urutan--estimasi)
6. [Tes dan regresi](#6-tes-dan-regresi)
7. [Rollout, flag, rollback](#7-rollout-flag-rollback)
8. [Risiko & koordinasi](#8-risiko--koordinasi)

---

## 1. Tujuan dan ruang lingkup

| T3 — Kontrol internal & Setting Akuntansi | T4 — Penomoran terpusat & master customer |
|---|---|
| **6** Aturan persetujuan (ambang & peran) dapat diatur, berlaku juga untuk Quotation, ditegakkan **di service** | **12** `DocumentNumberService` + `document_sequences` atomik, satu format (D11) untuk seluruh dokumen penjualan |
| **7** Kebijakan kredit + paparan (piutang + SO belum ditagih) + Info Customer untuk semua tipe | **20b** `customers:merge` (dry-run, CSV, cadangan, uji sebelum/sesudah) |
| **8** `DocumentLock` sebagai sumber tunggal kunci dokumen (policy, aksi Filament, API); jalur koreksi, bukan "buka kunci" | **20c** kode `CUST-00001`, `legacy_code` |
| **9** Halaman Pengaturan Akuntansi (11 kunci akun) + migrasi titik hard-code alur penjualan | **20d / X10** `CustomerService::create` tunggal (dedup) untuk semua form |
| **X8** Peran fiktif "Super Sales" di `DeliveryOrderPolicy` | |

**Di luar ruang lingkup (dicatat):** Nota Kredit/retur uang & pembatalan invoice (T5 — lihat koordinasi §8), dokumen cetak (T6), pembelian.

---

## 2. Keputusan

### 2.1 Sudah diputuskan (21 September 2026: "setuju D6-D14")
D6 ambang & peran dapat diatur, nilai awal = perilaku sekarang, berlaku juga Quotation · D7 Kredit = blokir, COD/Bebas = informasi, limit 0 pada Kredit = tidak diizinkan · D8 hanya jalur koreksi · D9 11 kunci akun, Finance Manager + Super Admin, tercatat 🧾 · D10 (T5) · D11 `{PREFIX}-{KODECABANG}-{YYMM}-{SEQ4}`, reset bulanan per cabang · D12 (T6) · D13 (T7) · D14 gabung customer dipilih bisnis dari CSV, di luar jam kerja dengan cadangan.

### 2.2 Keputusan turunan (rekomendasi berlaku kecuali Anda ubah)
| Kode | Pertanyaan | Keputusan (default) |
|---|---|---|
| **D24** | Override persetujuan oleh Owner/Super Admin | **Alasan wajib** (≥ 10 karakter) + tercatat di tabel audit `approval_overrides` (dokumen, pengguna, aturan yang dilewati, alasan). Tanpa alasan → ditolak |
| **D25** | Dokumen yang memakai `approval_rules` | Quotation, Sales Order (T3); Pengajuan Pembayaran tetap memakai konstanta lama sampai T5/T7 (tidak diubah agar tidak melebar); Nota Kredit/refund (T5) |
| **D26** | Pembuat = penyetuju pada Quotation | Berlaku pemisahan tugas seperti SO; override Owner/Super Admin sesuai D24 |
| **D27** | Definisi paparan kredit | `paparan = Σ piutang berjalan (AR belum lunas) + Σ nilai SO Approved/Dikirim Sebagian yang belum ditagih (total SO − invoice terbit) + nilai SO yang sedang disetujui`. Tagihan jatuh tempo tetap memblokir (perilaku sekarang) |
| **D28** | Limit 0 pada tipe Kredit | Tidak diizinkan menyetujui SO (bukan lagi "tak terbatas"). **Berlaku hanya setelah flag dihidupkan**, dan setelah `customers:audit-credit-limit` ditinjau bisnis (angka 999.999.999.999 tidak diubah otomatis) |
| **D29** | Penerimaan customer yang sudah berjurnal | Tidak dapat diedit/dihapus; tersedia aksi **Batalkan Penerimaan** (alasan wajib) yang membuat jurnal balik dan mengembalikan piutang. Pola sama untuk DO/Jadwal/Retur |
| **D30** | Kunci akun yang tidak diisi | **Fallback ke `config/coa.php` + kode saat ini** (perilaku sekarang) sehingga tidak ada perubahan mendadak; `master:readiness` menandai kunci yang belum diisi eksplisit |
| **D31** | Prefiks dokumen (D11) | `QO` Quotation, `SO`, `DO`, `SJ` Surat Jalan, `SCH` Jadwal, `INV` (kode Cabang pajak/non-pajak dipakai bila terisi), `RTN` Retur, `KW` Penerimaan, `DEP` Deposit. Nomor lama **tidak diubah** |
| **D32** | Kode customer | `CUST-00001` global (bukan per cabang); customer lama diberi kode berurutan menurut id; kode/nomor impor lama disimpan di `legacy_code` |
| **D33** | Aturan gabung customer | Survivor = kolom `survivor_id` dari CSV yang disetujui bisnis; semua referensi dipindah dalam satu transaksi; customer yang digabung di-soft-delete dengan `merged_into`; dijalankan di luar jam kerja dengan cadangan CSV |

---

## 3. Hasil pemeriksaan kode

| ID | Temuan | Konsekuensi |
|---|---|---|
| **G1** | `SalesOrderService::approve()` dan `QuotationService::approve()` **tidak memeriksa** siapa yang menyetujui; penegakan hanya di policy/aksi (`ApprovalControlService::canApproveSaleOrder` di aksi Filament) | Penegakan pindah ke service (memakai `Auth::user()`), aksi/API/tes lama yang memanggil service langsung akan ikut diperiksa → tes lama yang memanggil `approve()` tanpa peran perlu fixture (flag mati = tidak berubah) |
| **G2** | Aturan saat ini hard-code: `TIER_1_MAX_AMOUNT`, `SALES_TIER_1_ROLES`, `TOP_TIER_ROLES` | `approval_rules` diberi nilai awal identik; pemindahan ke tabel tidak mengubah hasil (tes ekuivalensi matriks lama vs baru) |
| **G3** | `CreditValidationService`: pemakaian = Σ AR `remaining` UNPAID saja; SO Approved belum ditagih tidak dihitung; `kredit_limit ≤ 0` = valid (lolos) | Paparan (D27) dihitung satu layanan (`CreditExposure`); pemeriksaan tetap di dalam `lockForUpdate` customer |
| **G4** | Jalur tulis yang menghindari kunci: `DeliveryOrderResource` tabel `EditAction` tanpa `visible`; `CustomerReceipt` edit/delete hanya cek izin; jadwal tanpa kunci setelah `delivered`; Retur hanya izin | `DocumentLock` menjadi satu-satunya sumber; tes berbasis data (dokumen × status × aksi) |
| **G5** | `DeliveryOrderPolicy` memakai peran `Super Sales` yang tidak ada di `RoleSeeder` (X8) | Dirapikan: kondisi berbasis peran yang benar-benar ada + izin |
| **G6** | Titik hard-code kode akun di alur penjualan: `InvoiceObserver` (9), `CustomerReturnService` (9), `DeliveryOrderObserver` (4), `SalesInvoiceResource` (4), + `LedgerPostingService`, Deposit | Migrasi bertahap ke `AccountingSettings::coa()`; pemindai tes mencegah kode akun baru di file alur penjualan |
| **G7** | Generator nomor tersebar (SO, QO, DO, INV, SCH, SJ, Retur, Deposit, Penerimaan) memakai "MAX lalu cek" tanpa kunci; kolom `Cabang.kode_invoice_*` ada tetapi tak dipakai | `DocumentNumberService` + tabel urutan terkunci; benih dari MAX; nomor lama utuh |
| **G8** | **Koordinasi:** di working tree ada perubahan **belum di-commit** milik pihak lain (mis. `Invoice.php`, `InvoicePolicy.php`, migrasi `…add_cancellation_to_invoices_table`, `PaymentRequest*`, `Console/Kernel.php`) — tampaknya awal pembatalan invoice (T5) | T3 **tidak menyentuh** berkas itu; kunci Invoice di `DocumentLock` didefinisikan dan diuji, tetapi pemasangannya ke `InvoicePolicy` dilakukan setelah perubahan tersebut di-commit |

---

## 4. Rancangan teknis

### 4.1 T3.1 — Aturan persetujuan (usulan 6)
- Tabel `approval_rules` (`document_type`, `min_amount`, `max_amount` nullable, `roles` JSON, `require_reason_on_override`, `is_active`) + `approval_overrides` (audit).
- `ApprovalRule::forDocument($type, $amount)`; `ApprovalControlService::canApprove(User, Model)` generik: izin dokumen → pemisahan tugas (D26) → aturan nominal. `canApproveSaleOrder/…` tetap ada dan mendelegasikan (kompatibel).
- **Penegakan di service:** `SalesOrderService::approve`/`approveAsBackorder`, `QuotationService::approve` memanggil `canApprove` (flag `sales.controls.approval_rules`); override: opsi `override_reason`.
- Halaman Filament "Aturan Persetujuan" (Super Admin & Finance Manager; perubahan tercatat).
- Nilai awal (seeder/migrasi data): Quotation & SO ≤ Rp10 jt → Sales Manager/Top; > Rp10 jt → Top (identik dengan sekarang).

### 4.2 T3.2 — Kebijakan kredit (usulan 7)
- `CreditExposure::for(Customer)`: piutang, SO belum ditagih, jatuh tempo, umur tertua, deposit, SO terbuka (satu tempat).
- `CreditValidationService` memakai paparan bila flag `sales.controls.credit_policy`; kebijakan per tipe (D7/D28); pengecualian beralasan oleh peran tinggi (D24); tetap dalam `lockForUpdate`.
- Endpoint ringkas `GET /api/v1/customers/{id}/credit-summary` → Info Customer React (semua tipe) dan teks bantu Filament.
- Uji balapan: dua SO bersamaan memperebutkan sisa limit.

### 4.3 T3.3 — Kunci dokumen (usulan 8, X8)
- `DocumentLock::check(Model, action)` → `{locked, reason, correctionPath}`; matriks dokumen × status × aksi (Quotation, SO, DO, Surat Jalan, Jadwal, Invoice, Penerimaan, Retur).
- Dipakai `Policy::update/delete`, `visible()` aksi Filament, dan validasi API. Flag `sales.controls.doc_lock` (default mati = perilaku lama).
- **Batalkan Penerimaan** (D29): alasan wajib, jurnal balik, AR dikembalikan, alokasi dilepas, tercatat.
- Rapikan peran fiktif (X8).

### 4.4 T3.4 — Pengaturan Akuntansi (usulan 9)
- Tabel `accounting_settings` (`key`, `coa_id`, `updated_by`) + log perubahan; `AccountingSettings::coa($key)` memvalidasi (akun ada, aktif, **detail**, tipe sesuai), fallback `config/coa.php` (D30).
- 11 kunci: `accounts_receivable`, `sales_revenue`, `sales_discount`, `sales_returns`, `sales_output_vat`, `customer_deposit`, `goods_in_transit` (barang terkirim), `cogs`, `inventory`, `cash_bank_default`, `sales_shipping`.
- Halaman "Pengaturan Akuntansi" (Finance Manager + Super Admin); migrasi titik hard-code alur penjualan (G6) satu per satu dengan tes jurnal identik; hapus pilihan COA bebas untuk akun internal; `master:readiness` diperluas.

### 4.5 T4.1 — Penomoran terpusat (usulan 12)
- `DocumentNumberService::next(type, cabangId, date)` + `document_sequences` (`type`, `cabang_id`, `period`, `last_number`) dengan `lockForUpdate`; format D11/D31; invoice memakai `Cabang.kode_invoice_*`.
- Benih: `documents:seed-sequences` (dry-run/apply) dari MAX nomor lama per jenis/cabang/periode; generator lama didelegasikan; nomor lama tidak diubah.
- `documents:check-numbering` (laporan celah/duplikat, informasi). Flag `sales.controls.central_numbering`.

### 4.6 T4.2–T4.3 — Master customer
- `CustomerService::create` tunggal (dedup NIK/NPWP/nama+telepon) dipakai form Customer, SO, Quotation, API (X10); kode `CUST-00001`, `legacy_code`.
- `customers:merge {--csv=} {--apply}`: dry-run default, cadangan CSV, memindahkan semua referensi dalam satu transaksi, soft-delete + `merged_into`, uji sebelum/sesudah (Σ AR, deposit, jumlah dokumen per customer).

---

## 5. Urutan & estimasi

| Urut | Tugas | Isi | Jam | Flag |
|---|---|---|---|---|
| 1 | **T3.0** | Cabang kerja, flag `sales.controls.*`, fixture `ctl*` | 3 | — |
| 2 | **T3.1** | Aturan persetujuan (tabel, service generik, penegakan di service, halaman, audit override) | 20 | `approval_rules` |
| 3 | **T3.2** | `CreditExposure`, kebijakan kredit, endpoint + Info Customer semua tipe | 18 | `credit_policy` |
| 4 | **T3.3** | `DocumentLock` + Batalkan Penerimaan + X8 + tes berbasis data | 24 | `doc_lock` |
| 5 | **T3.4** | Pengaturan Akuntansi + migrasi titik hard-code + pemindai | 24 | `accounting_settings` |
| 6 | **T3.5** | Integrasi T3: E2E, dokumen status, gerbang | 8 | — |
| 7 | **T4.1** | `DocumentNumberService` + benih + migrasi generator + `documents:check-numbering` | 22 | `central_numbering` |
| 8 | **T4.2** | `CustomerService`, kode customer, `legacy_code`, form seragam | 12 | — |
| 9 | **T4.3** | `customers:merge` + uji sebelum/sesudah | 12 | — |
| 10 | **T4.4** | Integrasi T4 & dokumen status | 6 | — |

Satu commit per tugas; tiap tugas: tes baru + uji mutasi penjaga kritis + pint pada berkas baru + **regresi terarah** (suite penuh berjalan di latar belakang dan tidak menahan tugas berikutnya).

---

## 6. Tes dan regresi
- **T3.1:** matriks (peran × nominal × pembuat × dokumen) — ekuivalen dengan perilaku lama saat aturan awal; ubah ambang di tabel → berlaku tanpa deploy; override wajib alasan + tercatat; penegakan di service (API/aksi tidak bisa menghindar).
- **T3.2:** paparan menghitung SO belum ditagih; limit 0 pada Kredit ditolak (flag hidup); COD/Bebas hanya informasi; dua SO bersamaan aman; endpoint per tipe.
- **T3.3:** tes berbasis data mengiterasi dokumen × status dan menegaskan `update/delete/aksi` sesuai matriks (dokumen baru yang lupa dikunci → gagal); Batalkan Penerimaan menyeimbangkan jurnal & AR.
- **T3.4:** mengganti akun mengubah akun jurnal berikutnya; akun induk/nonaktif ditolak; pemindai: tidak ada kode akun hard-code baru pada berkas alur penjualan.
- **T4.1:** dua proses paralel tidak menghasilkan nomor kembar; nomor lama tetap; benih dari MAX; format per jenis/cabang/periode.
- **T4.3:** total AR/deposit/jumlah dokumen per customer identik sebelum–sesudah gabung.
- **Regresi wajib:** Fase 1–6, T1, T2 (Stock/*), `SaleOrder*`, `Quotation*`, `CustomerReceipt*`, `Invoice*`, `DeliveryOrder*`, `Customer*`.

---

## 7. Rollout, flag, rollback
Semua flag di `config/sales.php` → `controls` (default **mati**): `approval_rules`, `credit_policy`, `doc_lock`, `accounting_settings`, `central_numbering`. Urutan menyalakan: `approval_rules` → `doc_lock` → `credit_policy` (setelah audit limit ditinjau) → `accounting_settings` → `central_numbering` (setelah `documents:seed-sequences --apply`). Rollback: matikan flag; migrasi aditif dengan `down()`; perintah `--apply` menulis CSV cadangan.

## 8. Risiko & koordinasi
| Risiko | Mitigasi |
|---|---|
| Menyentuh siapa boleh apa (T3.1/T3.3) | Nilai awal identik dengan perilaku sekarang + tes ekuivalensi; flag |
| Kebijakan kredit menolak SO yang selama ini lolos (limit 0/999 M) | Flag terpisah; audit limit ditinjau bisnis; pengecualian beralasan |
| Migrasi kode akun mengubah jurnal | Tiap titik dimigrasi dengan tes jurnal identik (fallback = kode sekarang) |
| Penomoran menyentuh data hidup | Benih dari MAX; nomor lama tak diubah; dry-run + cadangan |
| Bentrok dengan pekerjaan pembatalan invoice yang belum di-commit (G8) | T3 tidak menyentuh berkas itu; pemasangan kunci Invoice menyusul |
