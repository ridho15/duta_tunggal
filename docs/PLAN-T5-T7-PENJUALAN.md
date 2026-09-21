# Rencana Pelaksanaan T5–T7 — Nota Kredit & Retur Uang, Dokumen Cetak, UX Konsisten

> Dibuat 22 September 2026 · Turunan dari `docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md` §7 (T5, T6, T7) · Usulan **5, 4, 11, 14–19** + temuan **X5, X6**.
> Keputusan **D10, D12, D13 disetujui** ("setuju D10-D13", 22 September 2026). Keputusan turunan di §2.2 memakai **rekomendasi**; semuanya di balik flag sehingga mudah diubah.
> Prasyarat: T3/T4 selesai (cabang `feat/penjualan-t3-kontrol`). Cabang kerja: `feat/penjualan-t5-koreksi` (dari `feat/penjualan-t3-kontrol`).

## Daftar Isi
1. [Ruang lingkup](#1-ruang-lingkup)
2. [Keputusan](#2-keputusan)
3. [Hasil pemeriksaan kode](#3-hasil-pemeriksaan-kode)
4. [Rancangan T5 — Nota Kredit](#4-rancangan-t5--nota-kredit)
5. [Rancangan T6 — Dokumen cetak](#5-rancangan-t6--dokumen-cetak)
6. [Rancangan T7 — UX konsisten & performa](#6-rancangan-t7--ux-konsisten--performa)
7. [Urutan & estimasi](#7-urutan--estimasi)
8. [Tes, rollout, risiko](#8-tes-rollout-risiko)

---

## 1. Ruang lingkup
| T5 (akuntansi & pajak 🧾) | T6 (dokumen cetak) | T7 (UX & performa) |
|---|---|---|
| **5** Nota Kredit: pembatalan penuh, koreksi sebagian, tanpa menghapus invoice | **11** Kop (nama legal, NPWP, alamat, rekening), tanda tangan, tempo, watermark pada Invoice, DO, Kwitansi, Nota Kredit, Retur | **17** pencarian sisi-server, batas 50; **19b** anggaran query & klik ganda; **16** konfirmasi berdampak; **14** pola aksi (D13); **15b** label status terpusat; **18b** info penting di layar |
| **4** Retur penjualan dengan jalur uang (keputusan "Refund / Nota Kredit") | | |
| **X6** status `cancelled` invoice (enum + label + kueri) | | |

## 2. Keputusan
### 2.1 Sudah diputuskan (22 September 2026)
**D10** balik PPN di jurnal; sisa terbayar → Deposit Customer; refund tunai lewat proses berapproval 🧾 · **D12** kop dokumen per Cabang, fallback global 🧾 · **D13** 2–3 aksi utama langsung + menu "Lainnya".

### 2.2 Keputusan turunan (rekomendasi berlaku kecuali Anda ubah)
| Kode | Pertanyaan | Keputusan (default) |
|---|---|---|
| **D34** | Flag | `sales.controls.credit_notes` (default mati) untuk Nota Kredit; T6/T7 tidak berisiko sehingga tanpa flag (kecuali pemindai/label) |
| **D35** | Urutan penyelesaian Nota Kredit | **Piutang invoice dulu**, kelebihan (bagian yang sudah dibayar customer) → **Deposit Customer** (jurnal Cr Deposit). AR tidak pernah negatif. **Refund tunai** memakai aksi "Kembalikan Saldo" Deposit yang sudah ada (bukan pengajuan pembayaran baru — `PaymentRequest` khusus supplier); persetujuan bertingkat refund menyusul pada aksi itu |
| **D36** | Status Nota Kredit | `draft → issued`. Yang sudah `issued` **final** (tidak ada "hapus/batalkan"); koreksi = Nota Kredit baru. Hanya draft yang dapat dihapus |
| **D37** | Pajak | Jurnal membalik **PPN Keluaran** sebesar PPN baris yang dikreditkan; nomor **Nota Retur Pajak** diisi manual (`tax_document_number`, format bebas) — tidak ada integrasi e-Faktur; ekspor PPN tetap ditunda (K-D) |
| **D38** | Keputusan retur baru | `credit` = "Refund / Nota Kredit": barang kembali ke stok (sama seperti "Penggantian" untuk stok & jurnal HPP yang SUDAH ADA, tidak digandakan) + Nota Kredit tipe *retur* dibuat draft dari item itu, kuantitas ≤ (qty invoice − sudah dikreditkan) |
| **D39** | Pembatalan invoice | = Nota Kredit **penuh** (semua sisa qty + biaya pengiriman); invoice → `cancelled` (penulisan mengikuti pekerjaan pembatalan invoice pembelian pihak lain di working tree) |
| **D40** | Persetujuan Nota Kredit | Jenis dokumen baru `credit_note` di `approval_rules` (≤ Rp10 jt: Finance Manager/Sales Manager/Admin/Owner/Super Admin; di atasnya peran puncak); pemisahan tugas pembuat≠penerbit; override beralasan (T3.1) |
| **D41** | Kop dokumen | Kolom baru pada `cabangs`: `nama_legal`, `npwp`, `alamat_pajak`, `rekening` (JSON daftar bank); fallback ke pengaturan global (`app_settings`) lalu ke data lama |

## 3. Hasil pemeriksaan kode
| ID | Temuan | Konsekuensi |
|---|---|---|
| **H1** | Jurnal invoice penjualan: Dr Piutang total; Cr Pendapatan per item (akun produk, bersih diskon = `subtotal`); Cr PPN Keluaran; Cr Biaya Pengiriman; + HPP terpisah | Jurnal Nota Kredit = cermin bagian yang dikreditkan: Dr Retur Penjualan (akun produk `sales_return_coa_id`, fallback kunci `sales_returns`), Dr PPN Keluaran, Dr Biaya Pengiriman (bila ikut), Cr Piutang / Cr Deposit |
| **H2** | `CustomerReturnService` sudah membalik **stok + HPP** untuk `repair`/`replace` (tanpa penjualan/PPN/piutang); nilainya memakai `net_unit_price` | `credit` diperlakukan seperti `replace` di layanan itu (tanpa menggandakan); sisi uang dikerjakan Nota Kredit |
| **H3** | `DepositObserver::updated` **menulis ulang** jurnal `DEP-{id}` saat `amount` berubah | Nota Kredit tidak mengubah `amount` lewat model berobserver: `updateQuietly` + log deposit + jurnal sendiri |
| **H4** | Enum status invoice belum memuat `cancelled`; pihak lain sedang menambahkannya (belum commit) | Migrasi T5 **idempoten** (menambah enum/kolom hanya bila belum ada) dan tidak menyentuh berkas mereka |
| **H5** | Laporan penjualan (Fase 6) mengecualikan `draft`; belum mengecualikan `cancelled` dan belum mengurangi Nota Kredit | Ditambahkan; angka bersih = invoice − Nota Kredit |
| **H6** | `DocumentLock` memuat matriks Invoice; `InvoicePolicy` masih di-WIP pihak lain | Aksi "Nota Kredit" ditambahkan sebagai aksi baru (tidak mengubah `InvoicePolicy`); policy `CreditNotePolicy` baru |

## 4. Rancangan T5 — Nota Kredit
- **Data (migrasi aditif):** `credit_notes` (nomor via T4 `CN-…`, tipe `pembatalan|retur|koreksi`, status, `invoice_id`, `customer_return_id`, tanggal, alasan wajib, DPP/PPN/total/biaya kirim, bagian ke piutang & deposit, `tax_document_number`, pembuat/penerbit) + `credit_note_items` (item invoice, qty, harga bersih, DPP, PPN, total). Enum `invoices.status` + `cancelled` & kolom `cancelled_*` (idempoten). `customer_return_items.decision` + `credit`. Izin baru (idempoten).
- **`CreditNoteService`:** `draftFromInvoice` (kuantitas ≤ sisa: qty invoice − dikreditkan, harga = `net_unit_price`), `draftCancellation`, `draftFromReturn`; `issue` (kunci invoice+AR, persetujuan T3.1, jurnal seimbang, AR/deposit, invoice `cancelled` bila penuh, atomik & idempoten); `deleteDraft`.
- **Aturan AR:** `AR.total −= CN`, `AR.remaining −= min(CN, remaining)`, `AR.paid −= kelebihan` (→ Deposit); invarian Σ(invoice − CN − penerimaan) = piutang.
- **UI:** `CreditNoteResource` (daftar, View, form draft dari invoice), aksi di Invoice ("Buat Nota Kredit", "Batalkan Invoice"), aksi "Terbitkan", nomor Nota Retur Pajak; retur: keputusan "Refund / Nota Kredit" + tombol "Buat Nota Kredit dari Retur".
- **Laporan/pilihan:** invoice `cancelled` tidak muncul di pilihan invoice/penerimaan dan tidak dihitung di laporan; Nota Kredit mengurangi penjualan bersih.

## 5. Rancangan T6 — Dokumen cetak
`DocumentPrintBuilder` (data dirakit di PHP, Blade hanya tampilan) + partial `company-header`, `doc-meta`, `signature-block`, `bank-accounts`; Cabang mendapat nama legal/NPWP/alamat pajak/rekening (D41). Dokumen: Invoice, DO (blok tanda tangan pengirim/penerima/gudang), Kwitansi, Nota Kredit, Retur; watermark DRAFT/DIBATALKAN; tes isi teks PDF; pemeriksaan visual A4 (Anda).

## 6. Rancangan T7 — UX konsisten & performa
`StatusLabels` terpusat + pemindai bahasa (15b); endpoint/makro pencarian sisi-server menggantikan `Customer::all()`+preload dan batas 50 (17); anggaran query per aksi (tes) & penjaga klik ganda server (19b); `ImpactPreview` pada aksi berefek (16); helper `DocumentActions` (14, D13); info stok/limit/sisa pada layar (18b).

## 7. Urutan & estimasi
| Urut | Tugas | Isi | Jam |
|---|---|---|---|
| 1 | **T5.1** | Migrasi + model + `CreditNoteService` (draft, terbit, jurnal, AR/deposit, pembatalan) + persetujuan + nomor `CN` | 24 |
| 2 | **T5.2** | Retur `credit` + `draftFromReturn` + laporan/pilihan mengecualikan `cancelled` dan mengurangi Nota Kredit | 12 |
| 3 | **T5.3** | UI (resource, aksi invoice, aksi retur, terbit) + `CreditNotePolicy` + `DocumentLock` | 14 |
| 4 | **T6.1** | Kolom kop Cabang + `DocumentPrintBuilder` + partial | 10 |
| 5 | **T6.2** | Template Invoice, DO, Kwitansi, Nota Kredit, Retur + tes isi PDF | 14 |
| 6 | **T7.1** | `StatusLabels` + pemindai bahasa + perbaikan kolom | 10 |
| 7 | **T7.2** | Pencarian sisi-server + batas 50 | 10 |
| 8 | **T7.3** | Anggaran query + penjaga klik ganda + `ImpactPreview` + `DocumentActions` | 16 |

## 8. Tes, rollout, risiko
- **T5:** retur 3 dari 12 pcs mengurangi piutang tepat 3/12 (DPP+PPN); retur > sisa ditolak; pembatalan penuh menyeimbangkan jurnal/AR/laporan; invoice terbayar → kelebihan ke Deposit, AR tidak negatif; dua kali terbit = satu efek; persetujuan (peran × nominal × pembuat); skenario "invoice salah → nota kredit → invoice pengganti"; mutation check.
- **Rollout T5:** flag `SALES_CONTROLS_CREDIT_NOTES` dihidupkan **setelah** akuntan meninjau jurnal contoh 🧾 (disediakan di UAT).
- **Risiko:** akuntansi & pajak (tertinggi) → flag + tinjauan akuntan; enum invoice dibagi dengan pekerjaan pihak lain → migrasi idempoten; deposit → bypass observer yang menulis ulang jurnal.

## 9. Status pelaksanaan

**Cabang:** `feat/penjualan-t5-koreksi` — satu commit per tugas: T5.1 `d18ae61`, T5.2 `7055622`, (baseline `e8a8292`), T5.3 `c28b450`.
Flag `sales.controls.credit_notes` **default mati**: tanpa menyalakannya tidak ada menu/aksi Nota Kredit, opsi keputusan retur "Refund / Nota Kredit" tidak ditawarkan, dan laporan tidak menambah kueri per baris (angka identik dengan perilaku lama).

| Tugas | Hasil | Tes baru |
|---|---|---|
| T5.1 | Migrasi `credit_notes`/`credit_note_items` (+ enum `invoices.status` `cancelled`, `customer_return_items.decision` `credit`, aturan persetujuan `credit_note`, izin — semuanya idempoten); `CreditNoteService` (draf berbatas sisa qty, terbit atomik & idempoten: jurnal cermin Dr Retur/PPN/Biaya Kirim, Cr Piutang/Deposit; AR tidak pernah negatif; pembatalan penuh → invoice `cancelled`); nomor `CN-…` terpusat | 11 |
| T5.2 | Keputusan retur `credit` (stok & HPP tetap dari `CustomerReturnService`, tidak digandakan) + `draftFromReturn`; `SalesReportService` mengecualikan `cancelled` dan menghitung **bersih** Nota Kredit (DPP/PPN/total/qty; HPP turun hanya untuk retur fisik); Surat Jalan dari invoice `cancelled` dapat ditagih ulang | 8 |
| T5.3 | `CreditNoteResource` (daftar + View), `CreditNotePolicy`, `CreditNoteActions` (Buat Nota Kredit, Batalkan Invoice, dari Retur, Terbitkan + No. Nota Retur Pajak + override beralasan, Hapus Draf), `CabangScope`, `DocumentLock`, kartu hub; label/filter `cancelled` di Invoice | 11 |

Semua penjaga kritis diuji **mutasi** (dirusak → tes gagal → dipulihkan); 26 mutan T5.2/T5.3, semua mati setelah tes penutup ditambahkan.

### Penyimpangan dari rencana & temuan baru
- **Regresi T5.3 menangkap cacat T5.1**: izin `credit note` dibuat migrasi tetapi belum ada di `HelperController::listPermission()` (sumber seeder) → `PermissionsConsistencyTest` gagal; diperbaiki di T5.3. Pelajaran: tiap migrasi yang menambah izin harus menambah daftar itu.
- **Suite penuh HEAD T4** (3315 tes): tidak ada kegagalan baru yang nyata. 44 "baru" adalah artefak snapshot (`public/build` Vite tidak ikut `git archive`; semuanya lolos di working tree). Baseline dikurangi 14 tes yang kini lolos (commit `e8a8292`).
- **D35 (refund tunai)** tetap lewat aksi "Kembalikan Saldo" Deposit yang sudah ada; persetujuan bertingkat refund menyusul di aksi itu.
- **`InvoicePolicy` tetap tidak disentuh** (WIP pihak lain): aksi Nota Kredit memakai izin `create credit note` + status invoice, bukan policy Invoice.
- **Nota Kredit tidak dapat diedit** (D36): kesalahan pada draf → hapus dan buat ulang.

### Yang perlu Anda lakukan
1. `php artisan migrate` (migrasi baru `2026_09_23_100000_create_credit_notes_tables`) lalu `php artisan db:seed --class=PermissionSeeder` bila izin dikelola lewat seeder.
2. **Tinjau jurnal contoh dengan akuntan 🧾** (Nota Kredit: Dr Retur Penjualan + PPN Keluaran, Cr Piutang/Deposit) sebelum menyalakan `SALES_CONTROLS_CREDIT_NOTES=true`.
3. UAT: retur → keputusan "Refund / Nota Kredit" → Selesaikan → Buat Nota Kredit → Terbitkan (invoice belum dibayar, sebagian, lunas) dan "Batalkan Invoice".
